<?php

namespace App\Http\Controllers\Banking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\StartEnableBankingConnectionRequest;
use App\Jobs\SyncEnableBankingConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\Wallet;
use App\Services\Banking\BankActivityLogger;
use App\Services\EnableBanking\EnableBankingClient;
use App\Services\EnableBanking\EnableBankingException;
use App\Services\EnableBanking\PsuHeaders;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class EnableBankingConnectionController extends Controller
{
    private const COUNTRY_NAMES = [
        'AT' => 'Austria', 'BE' => 'Belgium', 'HR' => 'Croatia', 'CZ' => 'Czechia', 'DE' => 'Germany',
        'DK' => 'Denmark', 'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland', 'FR' => 'France',
        'GB' => 'United Kingdom', 'HU' => 'Hungary', 'IE' => 'Ireland', 'IT' => 'Italy', 'LT' => 'Lithuania',
        'LV' => 'Latvia', 'NL' => 'Netherlands', 'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal',
        'SE' => 'Sweden', 'SI' => 'Slovenia', 'SK' => 'Slovakia',
    ];

    /**
     * ISO 20022 cash account types, used to name an account when the bank sends no product name.
     */
    private const CASH_ACCOUNT_TYPE_LABELS = [
        'CACC' => 'Current account',
        'CARD' => 'Card account',
        'SVGS' => 'Savings account',
        'LOAN' => 'Loan account',
        'CASH' => 'Cash account',
        'MOMA' => 'Money market account',
        'TRAN' => 'Transaction account',
        'TRAS' => 'Cash trading account',
        'SLRY' => 'Salary account',
        'ONDP' => 'Overnight deposit',
        'NREX' => 'Non-resident account',
        'TAXE' => 'Tax account',
        'MGLD' => 'Marginal lending account',
        'OTHR' => 'Bank account',
    ];

    /**
     * The bank picker shown in the "Connect a bank account" popup.
     */
    public function create(Request $request, EnableBankingClient $client): InertiaResponse
    {
        Gate::authorize('create', Wallet::class);

        $countries = $this->countries();
        $country = strtoupper((string) $request->query('country', $countries[0]));

        if (! in_array($country, $countries, true)) {
            $country = $countries[0];
        }

        $reconnect = null;

        if ($request->filled('reconnect')) {
            $connection = BankConnection::query()->findOrFail($request->integer('reconnect'));
            Gate::authorize('update', $connection);
            abort_if($connection->isManual(), Response::HTTP_NOT_FOUND);

            $reconnect = [
                'id' => $connection->id,
                'institution_name' => $connection->institution_name,
                'institution_logo' => $connection->institution_logo,
            ];
        }

        $institutions = [];
        $error = null;

        if (! $client->isConfigured()) {
            $error = 'Bank connections are not set up on this server yet (Enable Banking credentials are missing).';
        } elseif ($reconnect === null) {
            try {
                $institutions = collect($client->getAspsps($country))
                    ->map(fn (array $aspsp): array => [
                        'name' => (string) Arr::get($aspsp, 'name', ''),
                        'logo' => Arr::get($aspsp, 'logo'),
                        'beta' => (bool) Arr::get($aspsp, 'beta', false),
                        'psu_types' => array_values(Arr::wrap(Arr::get($aspsp, 'psu_types', ['personal']))),
                    ])
                    ->filter(fn (array $aspsp): bool => $aspsp['name'] !== '')
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            } catch (EnableBankingException $exception) {
                report($exception);
                $error = 'Could not load the list of banks. Please try again in a moment.';
            }
        }

        return Inertia::render('wallets/add-account', [
            'countries' => collect($countries)->map(fn (string $code): array => [
                'code' => $code,
                'name' => self::COUNTRY_NAMES[$code] ?? $code,
            ])->values(),
            'country' => $country,
            'institutions' => $institutions,
            'reconnect' => $reconnect,
            'error' => $error,
        ]);
    }

    public function start(StartEnableBankingConnectionRequest $request, EnableBankingClient $client): Response
    {
        Gate::authorize('create', Wallet::class);

        $validated = $request->validated();

        try {
            $aspsp = $client->findAspsp($validated['aspsp_country'], $validated['aspsp_name']);
        } catch (EnableBankingException $exception) {
            report($exception);

            return back()->with('error', 'Could not reach the bank directory. Please try again.');
        }

        if ($aspsp === null) {
            return back()->with('error', 'That bank is not available.');
        }

        $connection = BankConnection::query()->create([
            'user_id' => $request->user()->id,
            'provider' => BankConnection::PROVIDER_ENABLE_BANKING,
            'aspsp_name' => (string) $aspsp['name'],
            'aspsp_country' => $validated['aspsp_country'],
            'institution_name' => (string) $aspsp['name'],
            'institution_logo' => Arr::get($aspsp, 'logo'),
            'state' => (string) Str::uuid(),
            'status' => BankConnection::STATUS_PENDING,
            'metadata' => $this->aspspMetadata($aspsp, $validated['psu_type']),
        ]);

        return $this->redirectToBank($request, $client, $connection);
    }

    public function reconnect(Request $request, BankConnection $bankConnection, EnableBankingClient $client): Response
    {
        Gate::authorize('update', $bankConnection);
        abort_if($bankConnection->isManual(), Response::HTTP_UNPROCESSABLE_ENTITY);

        $metadata = $bankConnection->metadata ?? [];

        try {
            $aspsp = $client->findAspsp($bankConnection->aspsp_country, $bankConnection->aspsp_name);
        } catch (EnableBankingException $exception) {
            report($exception);
            $aspsp = null;
        }

        if ($aspsp !== null) {
            $metadata = [...$metadata, ...$this->aspspMetadata($aspsp, (string) ($metadata['psu_type'] ?? 'personal'))];
        }

        $bankConnection->forceFill([
            'state' => (string) Str::uuid(),
            'metadata' => [...$metadata, 'reconnecting' => true],
        ])->save();

        return $this->redirectToBank($request, $client, $bankConnection);
    }

    /**
     * The bank sends the user back here (inside the popup) after they approve or cancel.
     */
    public function callback(Request $request, EnableBankingClient $client, BankActivityLogger $activityLogger): InertiaResponse
    {
        $state = (string) $request->query('state', '');

        $connection = $state === '' ? null : BankConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('state', $state)
            ->first();

        if ($connection === null) {
            return $this->complete(false, 'This bank authorisation link is no longer valid. Please start again.');
        }

        $isReconnect = (bool) Arr::get($connection->metadata, 'reconnecting', false);
        $code = (string) $request->query('code', '');

        if ($code === '' || $request->filled('error')) {
            $this->abandon($connection, $isReconnect);
            $reason = Str::limit((string) ($request->query('error_description') ?: $request->query('error') ?: ''), 200);

            return $this->complete(false, 'The bank connection was cancelled.'.($reason !== '' ? ' ('.$reason.')' : ''));
        }

        $psuHeaders = PsuHeaders::fromRequest($request, Arr::wrap(Arr::get($connection->metadata, 'required_psu_headers', [])));

        try {
            $session = $client->authorizeSession($code, $psuHeaders);
        } catch (EnableBankingException $exception) {
            report($exception);
            $this->abandon($connection, $isReconnect);

            return $this->complete(false, 'The bank did not confirm access. Please try again.');
        }

        $sessionId = (string) Arr::get($session, 'session_id', '');
        $accounts = collect(Arr::get($session, 'accounts', []))
            ->filter(fn (mixed $account): bool => is_array($account) && Arr::get($account, 'uid') !== null)
            ->values();

        if ($sessionId === '' || $accounts->isEmpty()) {
            $this->abandon($connection, $isReconnect);

            return $this->complete(false, 'The bank did not share any accounts. Make sure you select at least one account at your bank.');
        }

        $previousSessionId = $connection->session_id;

        DB::transaction(function () use ($connection, $session, $sessionId, $accounts, $isReconnect, $request): void {
            $wallet = $connection->wallet ?? Wallet::query()->create([
                'user_id' => $request->user()->id,
                'name' => $connection->institution_name ?: $connection->aspsp_name,
                'type' => 'bank',
                'currency' => strtoupper((string) (Arr::get($accounts->first(), 'currency') ?: 'EUR')),
            ]);

            $metadata = $connection->metadata ?? [];
            $validUntil = Arr::get($session, 'access.valid_until') ?? Arr::get($metadata, 'requested_valid_until');

            $connection->forceFill([
                'wallet_id' => $wallet->id,
                'session_id' => $sessionId,
                'status' => BankConnection::STATUS_SYNCING,
                'authorized_at' => now(),
                'consent_valid_until' => is_string($validUntil) ? CarbonImmutable::parse($validUntil) : null,
                'consent_reminder_sent_at' => null,
                'next_sync_at' => null,
                'last_sync_error' => null,
                'sync_failures' => 0,
                'metadata' => Arr::except($metadata, ['reconnecting', 'requested_valid_until']),
            ])->save();

            $this->storeAccounts($connection, $accounts->all(), $isReconnect);
        });

        if ($isReconnect && $previousSessionId !== null && $previousSessionId !== $sessionId) {
            try {
                $client->deleteSession($previousSessionId);
            } catch (EnableBankingException) {
                // The old session has usually expired already.
            }
        }

        $activityLogger->log($request->user(), $isReconnect ? 'bank_reconnected' : 'account_added', [
            'connection_id' => $connection->id,
            'institution_name' => $connection->institution_name,
            'accounts_count' => $accounts->count(),
        ]);

        // The user is present, so this first (90-day) sync doesn't count against the
        // bank's daily limit. It runs after the page below has been sent.
        SyncEnableBankingConnectionJob::dispatchAfterResponse($connection->id, 'connected', $psuHeaders);

        return $this->complete(true, $isReconnect
            ? sprintf('%s is reconnected. Syncing now…', $connection->institution_name)
            : sprintf('%s is connected. Fetching your transactions…', $connection->institution_name));
    }

    public function destroy(BankConnection $bankConnection, EnableBankingClient $client): RedirectResponse
    {
        Gate::authorize('delete', $bankConnection);
        abort_if($bankConnection->isManual(), Response::HTTP_UNPROCESSABLE_ENTITY);

        self::closeBankSession($client, $bankConnection);

        DB::transaction(function () use ($bankConnection): void {
            $wallet = $bankConnection->wallet;
            $bankConnection->delete();

            if ($wallet !== null && $wallet->bankConnections()->doesntExist()) {
                $wallet->delete();
            }
        });

        return to_route('wallets.index')->with('success', 'Bank disconnected and its data deleted.');
    }

    /**
     * Ends the consent at the bank. Best effort: the local data is deleted either way.
     */
    public static function closeBankSession(EnableBankingClient $client, BankConnection $connection): void
    {
        if (
            $connection->provider !== BankConnection::PROVIDER_ENABLE_BANKING
            || $connection->session_id === null
            || ! $client->isConfigured()
        ) {
            return;
        }

        try {
            $client->deleteSession($connection->session_id);
        } catch (EnableBankingException $exception) {
            report($exception);
        }
    }

    private function redirectToBank(Request $request, EnableBankingClient $client, BankConnection $connection): Response
    {
        $metadata = $connection->metadata ?? [];
        $isReconnect = (bool) ($metadata['reconnecting'] ?? false);
        $maxConsentSeconds = (int) ($metadata['maximum_consent_validity'] ?? 0);
        $validUntil = now()->addDays(max(1, (int) config('services.enable_banking.max_consent_days', 180)));

        if ($maxConsentSeconds > 0) {
            // Stay a minute under the bank's maximum so the request isn't rejected.
            $validUntil = $validUntil->min(now()->addSeconds(max(60, $maxConsentSeconds - 60)));
        }

        try {
            $response = $client->startAuthorization([
                'access' => [
                    'valid_until' => $validUntil->toIso8601String(),
                ],
                'aspsp' => [
                    'name' => $connection->aspsp_name,
                    'country' => $connection->aspsp_country,
                ],
                'state' => $connection->state,
                'redirect_url' => route('banking.callback'),
                'psu_type' => (string) ($metadata['psu_type'] ?? 'personal'),
                'language' => 'en',
            ], PsuHeaders::fromRequest($request, Arr::wrap($metadata['required_psu_headers'] ?? [])));
        } catch (EnableBankingException $exception) {
            report($exception);
            $this->abandon($connection, $isReconnect);

            return back()->with('error', 'Could not start the bank authorisation: '.$exception->getMessage());
        }

        $authorizationUrl = (string) Arr::get($response, 'url', '');

        if ($authorizationUrl === '') {
            $this->abandon($connection, $isReconnect);

            return back()->with('error', 'The bank did not return an authorisation link.');
        }

        $connection->forceFill([
            'metadata' => [...$metadata, 'requested_valid_until' => $validUntil->toIso8601String()],
        ])->save();

        return Inertia::location($authorizationUrl);
    }

    /**
     * @param  list<array<string, mixed>>  $sessionAccounts
     */
    private function storeAccounts(BankConnection $connection, array $sessionAccounts, bool $isReconnect): void
    {
        $existing = $connection->accounts()->get();
        $seenIds = [];

        foreach ($sessionAccounts as $sessionAccount) {
            $uid = (string) Arr::get($sessionAccount, 'uid');
            $identificationHash = Arr::get($sessionAccount, 'identification_hash');

            // Account uids change with every session; the identification hash does not.
            $account = $existing->first(fn (BankAccount $account): bool => is_string($identificationHash)
                && $account->identification_hash === $identificationHash)
                ?? $existing->firstWhere('external_uid', $uid)
                ?? new BankAccount(['bank_connection_id' => $connection->id]);

            $identifier = Arr::get($sessionAccount, 'account_id.iban')
                ?? Arr::get($sessionAccount, 'account_id.other.identification');
            $identifier = is_string($identifier) ? (preg_replace('/\s+/', '', $identifier) ?? '') : '';
            $holderName = Arr::get($sessionAccount, 'name');
            $cashAccountType = Arr::get($sessionAccount, 'cash_account_type');
            // `name` is usually the account holder, so prefer the product, then the account type.
            $label = collect([
                Arr::get($sessionAccount, 'product'),
                Arr::get($sessionAccount, 'details'),
                self::CASH_ACCOUNT_TYPE_LABELS[strtoupper((string) $cashAccountType)] ?? null,
                $holderName,
            ])->first(fn (mixed $value): bool => is_string($value) && trim($value) !== '');

            $account->fill([
                'external_uid' => $uid,
                'identification_hash' => is_string($identificationHash) ? $identificationHash : null,
                'name' => is_string($label) ? Str::limit(trim($label), 250, '') : 'Bank account',
                'official_name' => is_string($holderName) ? Str::limit($holderName, 250, '') : null,
                'mask_encrypted' => strlen($identifier) >= 4 ? substr($identifier, -4) : null,
                'type' => 'bank',
                'subtype' => is_string($cashAccountType) ? strtolower($cashAccountType) : null,
                'currency' => strtoupper((string) (Arr::get($sessionAccount, 'currency') ?: 'EUR')),
                'is_active' => true,
                'raw_encrypted' => $sessionAccount,
            ]);

            if (! $account->exists) {
                $account->balances_encrypted = ['current' => 0, 'available' => null];
            }

            $account->save();
            $seenIds[] = $account->id;
        }

        if ($isReconnect) {
            // Accounts the user didn't share again keep their history but stop syncing.
            $connection->accounts()->whereNotIn('id', $seenIds)->update(['is_active' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $aspsp
     * @return array<string, mixed>
     */
    private function aspspMetadata(array $aspsp, string $requestedPsuType): array
    {
        $psuTypes = array_values(Arr::wrap(Arr::get($aspsp, 'psu_types', ['personal'])));

        return [
            'psu_type' => in_array($requestedPsuType, $psuTypes, true) ? $requestedPsuType : ($psuTypes[0] ?? 'personal'),
            'maximum_consent_validity' => (int) Arr::get($aspsp, 'maximum_consent_validity', 0),
            'required_psu_headers' => array_values(Arr::wrap(Arr::get($aspsp, 'required_psu_headers', []))),
        ];
    }

    private function abandon(BankConnection $connection, bool $isReconnect): void
    {
        if ($isReconnect) {
            $connection->forceFill([
                'metadata' => Arr::except($connection->metadata ?? [], ['reconnecting', 'requested_valid_until']),
            ])->save();

            return;
        }

        if ($connection->status === BankConnection::STATUS_PENDING) {
            $connection->delete();
        }
    }

    private function complete(bool $success, string $message): InertiaResponse
    {
        return Inertia::render('banking/complete', [
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * @return non-empty-list<string>
     */
    private function countries(): array
    {
        $countries = array_values(array_filter(
            (array) config('services.enable_banking.countries', ['SI', 'GB']),
            fn (mixed $country): bool => is_string($country) && strlen($country) === 2,
        ));

        return $countries === [] ? ['SI'] : $countries;
    }
}
