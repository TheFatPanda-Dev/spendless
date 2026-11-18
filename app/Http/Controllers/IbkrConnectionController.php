<?php

namespace App\Http\Controllers;

use App\Jobs\SyncIbkrConnectionJob;
use App\Models\BankConnection;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connects an Interactive Brokers account through a Flex Query + Flex Web Service token.
 */
class IbkrConnectionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Wallet::class);

        $validated = $request->validate($this->rules());

        $connection = DB::transaction(function () use ($request, $validated): BankConnection {
            $wallet = Wallet::query()->create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'] ?? 'Interactive Brokers',
                'type' => 'investment',
                'currency' => 'EUR',
            ]);

            return BankConnection::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $request->user()->id,
                'provider' => BankConnection::PROVIDER_IBKR,
                'institution_name' => 'Interactive Brokers',
                'aspsp_name' => 'Interactive Brokers',
                'aspsp_country' => 'ZZ',
                'state' => (string) Str::uuid(),
                'status' => BankConnection::STATUS_SYNCING,
                'authorized_at' => now(),
                'metadata' => ['query_id' => $validated['query_id']],
                'credentials_encrypted' => ['token' => $validated['token']],
            ]);
        });

        // IBKR needs a little while to build the report; this runs after the page is sent.
        SyncIbkrConnectionJob::dispatchAfterResponse($connection->id, 'connected');

        return to_route('wallets.show', $connection->wallet_id)
            ->with('success', 'Interactive Brokers connected. Fetching your report, which can take up to a minute.');
    }

    public function update(Request $request, BankConnection $bankConnection): RedirectResponse
    {
        Gate::authorize('update', $bankConnection);
        abort_unless($bankConnection->provider === BankConnection::PROVIDER_IBKR, Response::HTTP_NOT_FOUND);

        $validated = $request->validate($this->rules(requireToken: false));

        $credentials = $bankConnection->credentials_encrypted ?? [];

        if (($validated['token'] ?? null) !== null) {
            $credentials['token'] = $validated['token'];
        }

        $bankConnection->forceFill([
            'metadata' => [...($bankConnection->metadata ?? []), 'query_id' => $validated['query_id']],
            'credentials_encrypted' => $credentials,
            'status' => BankConnection::STATUS_SYNCING,
            'last_sync_error' => null,
            'sync_failures' => 0,
        ])->save();

        SyncIbkrConnectionJob::dispatchAfterResponse($bankConnection->id, 'manual');

        return back()->with('success', 'IBKR details updated. Fetching your report…');
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(bool $requireToken = true): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'query_id' => ['required', 'string', 'regex:/^\d{3,12}$/'],
            'token' => [$requireToken ? 'required' : 'nullable', 'string', 'min:10', 'max:200', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }
}
