<?php

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankConnection;
use App\Models\Wallet;
use App\Services\Banking\BankActivityLogger;
use App\Services\Plaid\PlaidClient;
use App\Services\Plaid\PlaidException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connect (or reconnect) a UK bank with Plaid Link, inside the same popup the Enable
 * Banking flow uses. UK banks log in through OAuth: the bank sends the user back to
 * /banking/plaid/oauth, where Link resumes with the same link token.
 */
class PlaidConnectionController extends Controller
{
    private const SESSION_KEY = 'plaid_link';

    /** Plaid link tokens live for 4 hours. */
    private const LINK_TOKEN_TTL_SECONDS = 4 * 3600;

    /** Give Plaid time to fetch the history before the first (counted) sync call. */
    private const FIRST_SYNC_DELAY_MINUTES = 2;

    public function show(Request $request, PlaidClient $client): InertiaResponse
    {
        Gate::authorize('create', Wallet::class);

        $connection = null;

        if ($request->filled('reconnect')) {
            $connection = BankConnection::query()->findOrFail($request->integer('reconnect'));
            Gate::authorize('update', $connection);
            abort_unless($connection->provider === BankConnection::PROVIDER_PLAID, Response::HTTP_NOT_FOUND);
        }

        if (! $client->isConfigured()) {
            return $this->page(error: 'UK bank connections are not set up on this server yet (PLAID_CLIENT_ID and PLAID_SECRET are missing).');
        }

        try {
            $link = $client->createLinkToken(
                $request->user(),
                $connection !== null ? (string) Arr::get($connection->credentials_encrypted ?? [], 'access_token') : null,
            );
        } catch (PlaidException $exception) {
            report($exception);

            return $this->page(error: 'Plaid could not start: '.($exception->displayMessage ?? $exception->getMessage()));
        }

        $request->session()->put(self::SESSION_KEY, [
            'token' => $link['link_token'],
            'connection_id' => $connection?->id,
            'created_at' => now()->timestamp,
        ]);

        return $this->page(linkToken: $link['link_token'], connection: $connection);
    }

    /**
     * The bank's OAuth login sends the user back here; Link continues where it stopped.
     */
    public function oauth(Request $request): InertiaResponse
    {
        $link = $this->pendingLink($request);

        if ($link === null) {
            return $this->page(error: 'This connection attempt has expired. Close this window and start again.');
        }

        $connection = $link['connection_id'] !== null ? BankConnection::query()->find($link['connection_id']) : null;

        return $this->page(linkToken: $link['token'], connection: $connection, isOAuthReturn: true);
    }

    public function exchange(Request $request, PlaidClient $client, BankActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'public_token' => ['required', 'string', 'max:200'],
            'institution_id' => ['nullable', 'string', 'max:100'],
            'institution_name' => ['nullable', 'string', 'max:150'],
        ]);
        $link = $this->pendingLink($request);
        $user = $request->user();

        if ($link === null) {
            return $this->finish($request, false, 'This connection attempt has expired. Close this window and start again.');
        }

        // Update mode: same Item, same access token; the bank login is fixed.
        if ($link['connection_id'] !== null) {
            $connection = BankConnection::query()->findOrFail($link['connection_id']);
            Gate::authorize('update', $connection);

            $connection->forceFill([
                'status' => BankConnection::STATUS_SYNCING,
                'last_sync_error' => null,
                'sync_failures' => 0,
                'authorized_at' => now(),
            ])->save();
            SyncPlaidConnectionJob::dispatchAfterResponse($connection->id, 'manual');
            $activityLogger->log($user, 'bank_reconnected', ['connection_id' => $connection->id, 'provider' => 'plaid']);

            return $this->finish($request, true, sprintf('%s is reconnected. Syncing now…', $connection->institution_name));
        }

        $institutionId = $validated['institution_id'] ?? null;
        $institutionName = trim((string) ($validated['institution_name'] ?? '')) ?: 'UK bank';

        try {
            $tokens = $client->exchangePublicToken($validated['public_token']);
        } catch (PlaidException $exception) {
            report($exception);

            return $this->finish($request, false, 'Plaid could not finish connecting: '.($exception->displayMessage ?? $exception->getMessage()));
        }

        $duplicate = $institutionId !== null && BankConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', BankConnection::PROVIDER_PLAID)
            ->where('metadata->institution_id', $institutionId)
            ->exists();

        if ($duplicate) {
            // Don't leave a second login to the same bank active at Plaid.
            self::removeQuietly($client, $tokens['access_token']);

            return $this->finish($request, false, sprintf('%s is already connected. If it stopped syncing, use Reconnect on its wallet instead.', $institutionName));
        }

        $connection = DB::transaction(function () use ($user, $tokens, $institutionId, $institutionName): BankConnection {
            $wallet = Wallet::query()->create([
                'user_id' => $user->id,
                'name' => $institutionName,
                'type' => 'bank',
                'currency' => 'GBP',
            ]);

            return BankConnection::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'provider' => BankConnection::PROVIDER_PLAID,
                'institution_name' => $institutionName,
                'aspsp_name' => $institutionName,
                'aspsp_country' => 'GB',
                'state' => (string) Str::uuid(),
                'status' => BankConnection::STATUS_CONNECTED,
                'authorized_at' => now(),
                'next_sync_at' => now()->addMinutes(self::FIRST_SYNC_DELAY_MINUTES),
                'credentials_encrypted' => ['access_token' => $tokens['access_token']],
                'metadata' => [
                    'item_id' => $tokens['item_id'],
                    'institution_id' => $institutionId,
                    'cursor' => null,
                ],
            ]);
        });

        SyncPlaidConnectionJob::dispatch($connection->id, 'connected')
            ->delay(now()->addMinutes(self::FIRST_SYNC_DELAY_MINUTES));
        $activityLogger->log($user, 'account_added', ['connection_id' => $connection->id, 'provider' => 'plaid']);

        return $this->finish($request, true, sprintf('%s is connected. Your accounts and transactions appear in a few minutes.', $institutionName));
    }

    public function complete(Request $request): InertiaResponse
    {
        $result = $request->session()->get('plaid_complete');

        return Inertia::render('banking/complete', [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? 'You can close this window.'),
        ]);
    }

    /**
     * Ends Plaid's access to the bank. Best effort: the local data is deleted either way.
     */
    public static function removeItem(PlaidClient $client, BankConnection $connection): void
    {
        $token = (string) Arr::get($connection->credentials_encrypted ?? [], 'access_token', '');

        if ($connection->provider === BankConnection::PROVIDER_PLAID && $token !== '' && $client->isConfigured()) {
            self::removeQuietly($client, $token);
        }
    }

    private static function removeQuietly(PlaidClient $client, string $accessToken): void
    {
        try {
            $client->removeItem($accessToken);
        } catch (PlaidException $exception) {
            report($exception);
        }
    }

    private function finish(Request $request, bool $success, string $message): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->flash('plaid_complete', ['success' => $success, 'message' => $message]);

        return to_route('banking.plaid.complete');
    }

    /**
     * @return array{token: string, connection_id: int|null}|null
     */
    private function pendingLink(Request $request): ?array
    {
        $link = $request->session()->get(self::SESSION_KEY);

        if (! is_array($link) || ! is_string($link['token'] ?? null) || (int) ($link['created_at'] ?? 0) < now()->timestamp - self::LINK_TOKEN_TTL_SECONDS) {
            return null;
        }

        return [
            'token' => $link['token'],
            'connection_id' => isset($link['connection_id']) ? (int) $link['connection_id'] : null,
        ];
    }

    private function page(?string $linkToken = null, ?BankConnection $connection = null, bool $isOAuthReturn = false, ?string $error = null): InertiaResponse
    {
        return Inertia::render('banking/plaid-link', [
            'linkToken' => $linkToken,
            'isOAuthReturn' => $isOAuthReturn,
            'mode' => $connection !== null ? 'reconnect' : 'connect',
            'institutionName' => $connection?->institution_name,
            'error' => $error,
        ]);
    }
}
