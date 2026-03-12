<?php

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\StartEnableBankingConnectionRequest;
use App\Jobs\SyncEnableBankingConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Services\EnableBanking\EnableBankingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnableBankingConnectionController extends Controller
{
    public function destroy(Request $request, BankConnection $bankConnection): RedirectResponse
    {
        abort_if($bankConnection->user_id !== $request->user()->id, 403);

        $bankConnection->delete();

        return to_route('dashboard')->with('success', 'Bank connection deleted.');
    }

    public function institutions(EnableBankingClient $client): JsonResponse
    {
        $country = (string) config('services.enable_banking.country', 'SI');

        try {
            $aspsps = $client->getAspsps($country);
        } catch (Throwable $exception) {
            return response()->json([
                'configured' => false,
                'country' => $country,
                'preferred' => [],
                'institutions' => [],
                'message' => 'Enable Banking is not configured for this environment.',
            ]);
        }

        $mapped = collect($aspsps)
            ->map(fn (array $aspsp): array => [
                'name' => (string) Arr::get($aspsp, 'name'),
                'country' => (string) Arr::get($aspsp, 'country', $country),
            ])
            ->filter(fn (array $aspsp): bool => $aspsp['name'] !== '')
            ->values();

        $preferred = $mapped
            ->filter(fn (array $aspsp): bool => Str::contains(Str::lower($aspsp['name']), ['delavska', 'revolut']))
            ->values();

        return response()->json([
            'configured' => true,
            'country' => $country,
            'preferred' => $preferred,
            'institutions' => $mapped,
        ]);
    }

    public function start(StartEnableBankingConnectionRequest $request, EnableBankingClient $client): Response
    {
        $validated = $request->validated();

        try {
            $resolvedAspspName = $this->resolveAspspName(
                client: $client,
                country: $validated['aspsp_country'],
                requestedName: $validated['aspsp_name'],
            );
        } catch (Throwable $exception) {
            return to_route('dashboard')
                ->with('error', 'Enable Banking is not configured for this environment.');
        }

        if ($resolvedAspspName === null) {
            return to_route('dashboard')->with('error', 'Selected bank is not available in Enable Banking sandbox.');
        }

        $connection = BankConnection::create([
            'user_id' => $request->user()->id,
            'aspsp_name' => $resolvedAspspName,
            'aspsp_country' => $validated['aspsp_country'],
            'state' => (string) Str::uuid(),
            'status' => 'pending',
            'consent_valid_until' => now()->addDays((int) config('services.enable_banking.consent_days', 10)),
        ]);

        try {
            $response = $client->startAuthorization([
                'access' => [
                    'valid_until' => $connection->consent_valid_until?->toIso8601String(),
                ],
                'aspsp' => [
                    'name' => $connection->aspsp_name,
                    'country' => $connection->aspsp_country,
                ],
                'state' => $connection->state,
                'redirect_url' => route('banking.callback', absolute: true),
                'psu_type' => $validated['psu_type'],
            ]);
        } catch (Throwable $exception) {
            $connection->delete();

            return to_route('dashboard')->with('error', 'Could not start bank authorization.');
        }

        $authorizationUrl = (string) Arr::get($response, 'url', '');

        if ($authorizationUrl === '') {
            $connection->delete();

            return to_route('dashboard')->with('error', 'Could not start bank authorization.');
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($authorizationUrl);
        }

        return redirect()->away($authorizationUrl);
    }

    private function resolveAspspName(EnableBankingClient $client, string $country, string $requestedName): ?string
    {
        $requestedNormalized = Str::of($requestedName)->lower()->trim()->toString();

        if ($requestedNormalized === '') {
            return null;
        }

        $aspsps = $client->getAspsps($country);

        $exactMatch = collect($aspsps)->first(function (array $aspsp) use ($requestedNormalized): bool {
            $name = Str::of((string) Arr::get($aspsp, 'name'))->lower()->trim()->toString();

            return $name === $requestedNormalized;
        });

        if ($exactMatch) {
            return (string) Arr::get($exactMatch, 'name');
        }

        $containsMatch = collect($aspsps)->first(function (array $aspsp) use ($requestedNormalized): bool {
            $name = Str::of((string) Arr::get($aspsp, 'name'))->lower()->trim()->toString();

            return Str::contains($name, $requestedNormalized) || Str::contains($requestedNormalized, $name);
        });

        if ($containsMatch) {
            return (string) Arr::get($containsMatch, 'name');
        }

        return null;
    }

    public function callback(Request $request, EnableBankingClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $connection = BankConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('state', $validated['state'])
            ->first();

        if (! $connection) {
            return to_route('dashboard')->with('error', 'Bank authorization state is invalid.');
        }

        try {
            $session = $client->authorizeSession($validated['code']);
            $sessionId = (string) Arr::get($session, 'session_id', '');

            if ($sessionId === '') {
                throw new \RuntimeException('Session ID was missing in Enable Banking response.');
            }

            $connection->update([
                'session_id' => $sessionId,
                'status' => 'active',
                'authorized_at' => now(),
                'last_sync_error' => null,
                'next_sync_at' => now(),
            ]);

            collect(Arr::get($session, 'accounts', []))
                ->filter(fn (array $account): bool => Arr::get($account, 'uid') !== null)
                ->each(function (array $account) use ($connection): void {
                    BankAccount::updateOrCreate(
                        [
                            'bank_connection_id' => $connection->id,
                            'external_uid' => (string) Arr::get($account, 'uid'),
                        ],
                        [
                            'name' => Arr::get($account, 'name'),
                            'iban' => Arr::get($account, 'identification.iban'),
                            'currency' => Arr::get($account, 'currency'),
                            'raw_payload' => $account,
                        ],
                    );
                });
        } catch (Throwable $exception) {
            $connection->delete();

            return to_route('dashboard')->with('error', 'Could not finish bank authorization.');
        }

        SyncEnableBankingConnectionJob::dispatch($connection->id);

        return to_route('dashboard')->with('success', 'Bank account connected. Transactions will sync continuously.');
    }
}
