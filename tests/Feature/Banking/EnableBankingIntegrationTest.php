<?php

namespace Tests\Feature\Banking;

use App\Jobs\SyncEnableBankingConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\BankAccessExpiringNotification;
use App\Services\EnableBanking\EnableBankingJwtFactory;
use App\Services\EnableBanking\EnableBankingSync;
use App\Services\EnableBanking\SyncSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Response shapes follow https://enablebanking.com/docs/api/reference/.
 */
class EnableBankingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.enablebanking.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.enable_banking.app_id' => 'test-app-id',
            'services.enable_banking.private_key_path' => '/tmp/test-key.pem',
            'services.enable_banking.countries' => ['SI', 'GB'],
        ]);

        $this->mock(EnableBankingJwtFactory::class, function (MockInterface $mock): void {
            $mock->shouldReceive('makeToken')->andReturn('test-jwt');
        });
    }

    public function test_bank_picker_lists_banks_for_the_selected_country(): void
    {
        Http::fake([
            self::API.'/aspsps*' => Http::response(['aspsps' => [
                $this->aspsp('Revolut', 'GB'),
                $this->aspsp('Monzo', 'GB'),
            ]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('wallets.add-account', ['country' => 'GB']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wallets/add-account')
                ->where('country', 'GB')
                ->has('countries', 2)
                ->where('institutions.0.name', 'Monzo')
                ->where('institutions.1.name', 'Revolut')
                ->where('error', null));

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'country=GB')
            && str_contains($request->url(), 'service=AIS'));
    }

    public function test_bank_picker_explains_when_enable_banking_is_not_configured(): void
    {
        config(['services.enable_banking.app_id' => null]);
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('wallets.add-account'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wallets/add-account')
                ->has('institutions', 0)
                ->where('error', fn (string $error): bool => str_contains($error, 'not set up')));

        Http::assertNothingSent();
    }

    public function test_connect_creates_pending_connection_and_sends_user_to_bank(): void
    {
        Http::fake([
            self::API.'/aspsps*' => Http::response(['aspsps' => [$this->aspsp('NLB d.d.', 'SI', maxConsentDays: 90)]]),
            self::API.'/auth' => Http::response(['url' => 'https://bank.example/authorize?flow=123']),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('banking.start'), ['aspsp_name' => 'NLB d.d.', 'aspsp_country' => 'SI'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://bank.example/authorize?flow=123');

        $connection = BankConnection::query()->sole();
        $this->assertSame(BankConnection::STATUS_PENDING, $connection->status);
        $this->assertSame('NLB d.d.', $connection->institution_name);
        $this->assertSame('personal', $connection->metadata['psu_type']);

        Http::assertSent(function (HttpRequest $request) use ($connection): bool {
            if (! str_ends_with($request->url(), '/auth')) {
                return false;
            }

            $validUntil = CarbonImmutable::parse($request['access']['valid_until']);

            // Capped by the bank's own 90-day maximum instead of the 180-day default.
            return $request['state'] === $connection->state
                && $request['redirect_url'] === route('banking.callback')
                && $request['aspsp'] === ['name' => 'NLB d.d.', 'country' => 'SI']
                && $validUntil->lessThan(now()->addDays(90))
                && $validUntil->greaterThan(now()->addDays(89))
                && $request->hasHeader('Psu-Ip-Address');
        });
    }

    public function test_connect_rejects_countries_that_are_not_offered(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('banking.start'), ['aspsp_name' => 'Some Bank', 'aspsp_country' => 'US'])
            ->assertSessionHasErrors('aspsp_country');

        $this->assertDatabaseCount('bank_connections', 0);
    }

    public function test_callback_creates_wallet_and_accounts_then_syncs(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $connection = $this->pendingConnection($user);

        Http::fake([
            self::API.'/sessions' => Http::response([
                'session_id' => 'session-1',
                'access' => ['valid_until' => '2027-05-01T00:00:00+00:00'],
                'accounts' => [[
                    'uid' => 'acc-uid-1',
                    'identification_hash' => 'hash-1',
                    'account_id' => ['iban' => 'SI56 0201 0001 2345 678'],
                    'name' => 'Jane Doe',
                    'product' => 'Osebni račun',
                    'cash_account_type' => 'CACC',
                    'currency' => 'EUR',
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('banking.callback', ['code' => 'auth-code', 'state' => $connection->state]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('banking/complete')
                ->where('success', true));

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->status);
        $this->assertSame('session-1', $connection->session_id);
        $this->assertSame('2027-05-01', $connection->consent_valid_until->toDateString());
        $this->assertSame('NLB d.d.', $connection->wallet->name);
        $this->assertSame('bank', $connection->wallet->type);

        $account = $connection->accounts()->sole();
        $this->assertSame('acc-uid-1', $account->external_uid);
        $this->assertSame('hash-1', $account->identification_hash);
        $this->assertSame('Osebni račun', $account->name);
        $this->assertSame('5678', $account->maskForDisplay());
        $this->assertSame('EUR', $account->currency);

        Bus::assertDispatchedAfterResponse(SyncEnableBankingConnectionJob::class, fn (SyncEnableBankingConnectionJob $job): bool => $job->bankConnectionId === $connection->id
            && $job->trigger === 'connected'
            && $job->psuHeaders !== []);
    }

    public function test_cancelled_authorisation_removes_the_pending_connection(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $connection = $this->pendingConnection($user);

        $this->actingAs($user)
            ->get(route('banking.callback', ['error' => 'access_denied', 'state' => $connection->state]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('banking/complete')
                ->where('success', false));

        $this->assertModelMissing($connection);
        Http::assertNothingSent();
    }

    public function test_callback_ignores_states_of_other_users(): void
    {
        Http::fake();
        $connection = $this->pendingConnection(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('banking.callback', ['code' => 'x', 'state' => $connection->state]))
            ->assertInertia(fn (Assert $page) => $page->where('success', false));

        $this->assertSame(BankConnection::STATUS_PENDING, $connection->fresh()->status);
    }

    public function test_sync_stores_signed_amounts_balances_and_follows_pagination(): void
    {
        $user = User::factory()->create();
        [$connection, $account] = $this->connectedAccount($user);

        $salary = Category::query()->create([
            'user_id' => $user->id, 'name' => 'Wages & Salary', 'type' => 'income', 'icon' => 'briefcase', 'color' => 'emerald',
        ]);

        Http::fake([
            self::API.'/accounts/acc-1/balances' => Http::response(['balances' => [
                ['name' => 'Available', 'balance_amount' => ['currency' => 'EUR', 'amount' => '1180.40'], 'balance_type' => 'ITAV'],
                ['name' => 'Booked', 'balance_amount' => ['currency' => 'EUR', 'amount' => '1200.40'], 'balance_type' => 'ITBD'],
            ]]),
            self::API.'/accounts/acc-1/transactions*' => Http::sequence()
                ->push([
                    'transactions' => [
                        $this->transaction('t-1', '12.50', 'DBIT', creditor: 'MERCATOR D.D.', remittance: ['Card payment Ljubljana']),
                        $this->transaction('t-2', '1500.00', 'CRDT', debtor: 'ACME d.o.o.', remittance: ['PLACA 10/2026']),
                    ],
                    'continuation_key' => 'page-2',
                ])
                ->push([
                    'transactions' => [
                        $this->transaction('t-3', '4.20', 'DBIT', creditor: 'Kavarna', status: 'PDNG'),
                        $this->transaction('t-4', '99.00', 'DBIT', creditor: 'Cancelled shop', status: 'CNCL'),
                    ],
                    'continuation_key' => null,
                ]),
        ]);

        $run = app(EnableBankingSync::class)->sync($connection, 'scheduled');

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(3, $run->added_count);
        $this->assertDatabaseCount('bank_transactions', 3);

        $groceries = BankTransaction::query()->where('external_uid', 't-1')->sole();
        $this->assertSame('-12.50', $groceries->amount);
        $this->assertSame('MERCATOR D.D.', $groceries->merchant_name);
        $this->assertSame('Card payment Ljubljana', $groceries->name);
        $this->assertSame($connection->id, $groceries->bank_connection_id);
        $this->assertSame('2026-10-10', $groceries->date->toDateString());

        $income = BankTransaction::query()->where('external_uid', 't-2')->sole();
        $this->assertSame('1500.00', $income->amount);
        $this->assertSame($salary->id, $income->category_id);
        $this->assertFalse($income->category_manually_set);

        $this->assertTrue(BankTransaction::query()->where('external_uid', 't-3')->sole()->pending);

        $account->refresh();
        $this->assertSame(['current' => 1200.4, 'available' => 1180.4], $account->balances_encrypted);

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_CONNECTED, $connection->status);
        $this->assertEquals(SyncSchedule::nextSlot(), $connection->next_sync_at);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'continuation_key=page-2'));
        Http::assertNotSent(fn (HttpRequest $request): bool => $request->hasHeader('Psu-Ip-Address'));
    }

    public function test_pending_transaction_is_replaced_when_the_bank_books_it(): void
    {
        $user = User::factory()->create();
        [$connection] = $this->connectedAccount($user);

        Http::fake([
            self::API.'/accounts/acc-1/balances' => Http::response(['balances' => []]),
            self::API.'/accounts/acc-1/transactions*' => Http::sequence()
                ->push(['transactions' => [$this->transaction('pending-ref', '4.20', 'DBIT', creditor: 'Kavarna', status: 'PDNG')]])
                ->push(['transactions' => [$this->transaction('booked-ref', '4.20', 'DBIT', creditor: 'Kavarna')]]),
        ]);

        app(EnableBankingSync::class)->sync($connection, 'scheduled');
        $this->assertTrue(BankTransaction::query()->sole()->pending);

        $run = app(EnableBankingSync::class)->sync($connection->fresh(), 'scheduled');

        $this->assertSame(1, $run->removed_count);
        $booked = BankTransaction::query()->sole();
        $this->assertSame('booked-ref', $booked->external_uid);
        $this->assertFalse($booked->pending);
    }

    public function test_resync_keeps_categories_the_user_chose(): void
    {
        $user = User::factory()->create();
        [$connection, $account] = $this->connectedAccount($user);
        $fun = Category::query()->create([
            'user_id' => $user->id, 'name' => 'Entertainment', 'type' => 'expense', 'icon' => 'clapperboard', 'color' => 'fuchsia',
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account->id,
            'external_uid' => 't-1',
            'name' => 'Old name',
            'amount' => -12.50,
            'currency' => 'EUR',
            'date' => '2026-10-10',
            'category_id' => $fun->id,
            'category_manually_set' => true,
        ]);

        $this->fakeAccountResponses([$this->transaction('t-1', '12.50', 'DBIT', creditor: 'Kino', remittance: ['Cinema'])]);
        app(EnableBankingSync::class)->sync($connection, 'scheduled');

        $transaction = BankTransaction::query()->sole();
        $this->assertSame($fun->id, $transaction->category_id);
        $this->assertTrue($transaction->category_manually_set);
        $this->assertSame('Cinema', $transaction->name);
    }

    public function test_identical_transactions_without_a_reference_are_not_merged(): void
    {
        [$connection] = $this->connectedAccount(User::factory()->create());

        $coffee = $this->transaction(null, '2.10', 'DBIT', creditor: 'Kavarna');
        $this->fakeAccountResponses([$coffee, $coffee]);

        app(EnableBankingSync::class)->sync($connection, 'scheduled');
        app(EnableBankingSync::class)->sync($connection->fresh(), 'scheduled');

        $this->assertDatabaseCount('bank_transactions', 2);
    }

    public function test_expired_session_asks_the_user_to_reconnect(): void
    {
        [$connection] = $this->connectedAccount(User::factory()->create());

        Http::fake([
            self::API.'/accounts/acc-1/balances' => Http::response([
                'message' => 'Session is expired', 'code' => 401, 'error' => 'EXPIRED_SESSION',
            ], 401),
        ]);

        $run = app(EnableBankingSync::class)->sync($connection, 'scheduled');

        $this->assertSame('failed', $run->status);
        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_NEEDS_REAUTH, $connection->status);
        $this->assertNull($connection->next_sync_at);
        $this->assertStringContainsString('Reconnect', $connection->last_sync_error);

        // Background syncs leave it alone until the user reconnects.
        Http::fake();
        (new SyncEnableBankingConnectionJob($connection->id))->handle(app(EnableBankingSync::class));
        Http::assertNothingSent();
    }

    public function test_other_errors_retry_at_the_next_slot(): void
    {
        [$connection] = $this->connectedAccount(User::factory()->create());

        Http::fake([self::API.'/accounts/acc-1/balances' => Http::response(['message' => 'Bank is down', 'error' => 'ASPSP_ERROR'], 500)]);

        app(EnableBankingSync::class)->sync($connection, 'scheduled');

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_SYNC_FAILED, $connection->status);
        $this->assertSame(1, $connection->sync_failures);
        $this->assertEquals(SyncSchedule::nextSlot(), $connection->next_sync_at);
    }

    public function test_reconnect_keeps_accounts_and_history(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        [$connection, $account] = $this->connectedAccount($user, status: BankConnection::STATUS_NEEDS_REAUTH);

        BankTransaction::query()->create([
            'bank_connection_id' => $connection->id, 'bank_account_id' => $account->id, 'external_uid' => 'old-tx',
            'name' => 'Old', 'amount' => -1, 'currency' => 'EUR', 'date' => '2026-09-01',
        ]);

        Http::fake([
            self::API.'/aspsps*' => Http::response(['aspsps' => [$this->aspsp('NLB d.d.', 'SI')]]),
            self::API.'/auth' => Http::response(['url' => 'https://bank.example/reauth']),
            self::API.'/sessions' => Http::response([
                'session_id' => 'session-2',
                'accounts' => [['uid' => 'acc-new-uid', 'identification_hash' => 'hash-1', 'currency' => 'EUR', 'name' => 'Jane Doe', 'cash_account_type' => 'CARD']],
            ]),
            self::API.'/sessions/session-1' => Http::response(['message' => 'OK']),
        ]);

        $this->actingAs($user)
            ->post(route('banking.reconnect', $connection))
            ->assertRedirect('https://bank.example/reauth');

        $state = $connection->fresh()->state;

        $this->actingAs($user)
            ->get(route('banking.callback', ['code' => 'c', 'state' => $state]))
            ->assertInertia(fn (Assert $page) => $page->where('success', true));

        $account->refresh();
        $this->assertSame('acc-new-uid', $account->external_uid);
        $this->assertSame('Card account', $account->name, 'No product name: falls back to the account type, not the holder.');
        $this->assertSame(1, BankAccount::query()->count());
        $this->assertSame(1, BankTransaction::query()->count());
        $this->assertSame('session-2', $connection->fresh()->session_id);
        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->fresh()->status);

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/sessions/session-1'));
    }

    public function test_disconnect_ends_the_bank_session_and_deletes_the_wallet(): void
    {
        $user = User::factory()->create();
        [$connection] = $this->connectedAccount($user);
        $walletId = $connection->wallet_id;

        Http::fake([self::API.'/sessions/session-1' => Http::response(['message' => 'OK'])]);

        $this->actingAs($user)
            ->delete(route('banking.destroy', $connection))
            ->assertRedirect(route('wallets.index'));

        $this->assertModelMissing($connection);
        $this->assertDatabaseMissing('wallets', ['id' => $walletId]);
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'DELETE');
    }

    public function test_sync_now_tells_the_bank_the_user_is_present(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        [$connection] = $this->connectedAccount($user);

        $this->actingAs($user)
            ->postJson(route('bank-connections.refresh', $connection))
            ->assertOk()
            ->assertJson(['queued' => 1]);

        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->fresh()->status);
        Bus::assertDispatchedAfterResponse(SyncEnableBankingConnectionJob::class, fn (SyncEnableBankingConnectionJob $job): bool => $job->trigger === 'manual'
            && isset($job->psuHeaders['Psu-Ip-Address']));

        $this->getJson(route('wallets.sync-status'))
            ->assertJson(['is_syncing' => true, 'syncing_connections' => 1]);
    }

    public function test_scheduler_queues_only_due_connections(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        [$due] = $this->connectedAccount($user, nextSyncAt: now()->subMinute());
        [$notDue] = $this->connectedAccount($user, nextSyncAt: now()->addHour());
        [$expired] = $this->connectedAccount($user, nextSyncAt: now()->subMinute(), consentValidUntil: now()->subDay());
        $stalePending = $this->pendingConnection($user);
        $stalePending->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('banking:dispatch-due-syncs')->assertSuccessful();

        Queue::assertPushed(SyncEnableBankingConnectionJob::class, 1);
        Queue::assertPushed(SyncEnableBankingConnectionJob::class, fn ($job): bool => $job->bankConnectionId === $due->id);
        $this->assertSame(BankConnection::STATUS_NEEDS_REAUTH, $expired->fresh()->status);
        $this->assertSame(BankConnection::STATUS_CONNECTED, $notDue->fresh()->status);
        $this->assertModelMissing($stalePending);
    }

    public function test_sync_slots_are_every_four_hours_during_the_slovenian_day(): void
    {
        $next = fn (string $utc): string => SyncSchedule::nextSlot(CarbonImmutable::parse($utc, 'UTC'))->format('Y-m-d H:i');

        // Winter (CET = UTC+1): 07/11/15/19 local = 06/10/14/18 UTC.
        $this->assertSame('2026-11-17 06:00', $next('2026-11-17 05:59'));
        $this->assertSame('2026-11-17 10:00', $next('2026-11-17 06:00'));
        $this->assertSame('2026-11-17 18:00', $next('2026-11-17 15:30'));
        $this->assertSame('2026-11-18 06:00', $next('2026-11-17 18:00'));
        $this->assertSame('2026-11-18 06:00', $next('2026-11-17 23:30'));
        // Summer (CEST = UTC+2): 07:00 local = 05:00 UTC.
        $this->assertSame('2026-07-01 05:00', $next('2026-07-01 02:00'));
        $this->assertSame('07:00, 11:00, 15:00 and 19:00 (Europe/Ljubljana)', SyncSchedule::describe());

        config(['services.enable_banking.sync_hours' => ' 21, 9,nope,25,9', 'services.enable_banking.sync_timezone' => 'UTC']);
        $this->assertSame([9, 21], SyncSchedule::hours());
        $this->assertSame('2026-11-17 21:00', $next('2026-11-17 09:00'));
        $this->assertSame('09:00 and 21:00 (UTC)', SyncSchedule::describe());
    }

    public function test_access_reminder_is_emailed_once_a_week_before_expiry(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [$connection] = $this->connectedAccount($user, consentValidUntil: now()->addDays(5));
        $this->connectedAccount($user, consentValidUntil: now()->addDays(60));

        $this->artisan('banking:send-access-reminders')->assertSuccessful();
        $this->artisan('banking:send-access-reminders')->assertSuccessful();

        Notification::assertSentToTimes($user, BankAccessExpiringNotification::class, 1);
        $this->assertNotNull($connection->fresh()->consent_reminder_sent_at);
    }

    /**
     * @return array<string, mixed>
     */
    private function aspsp(string $name, string $country, int $maxConsentDays = 180): array
    {
        return [
            'name' => $name,
            'country' => $country,
            'logo' => 'https://enablebanking.com/brands/'.$country.'/'.$name.'/',
            'psu_types' => ['personal', 'business'],
            'auth_methods' => [['name' => 'redirect', 'approach' => 'REDIRECT', 'psu_type' => 'personal']],
            'maximum_consent_validity' => $maxConsentDays * 86400,
            'beta' => false,
            'bic' => 'TESTSI2X',
            'required_psu_headers' => [],
        ];
    }

    /**
     * @param  list<string>  $remittance
     * @return array<string, mixed>
     */
    private function transaction(
        ?string $entryReference,
        string $amount,
        string $indicator,
        ?string $creditor = null,
        ?string $debtor = null,
        array $remittance = [],
        string $status = 'BOOK',
    ): array {
        return array_filter([
            'entry_reference' => $entryReference,
            'transaction_amount' => ['currency' => 'EUR', 'amount' => $amount],
            'creditor' => $creditor !== null ? ['name' => $creditor] : null,
            'debtor' => $debtor !== null ? ['name' => $debtor] : null,
            'credit_debit_indicator' => $indicator,
            'status' => $status,
            'booking_date' => $status === 'BOOK' ? '2026-10-10' : null,
            'value_date' => '2026-10-10',
            'transaction_date' => '2026-10-09',
            'remittance_information' => $remittance,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    private function fakeAccountResponses(array $transactions): void
    {
        Http::fake([
            self::API.'/accounts/acc-1/balances' => Http::response(['balances' => []]),
            self::API.'/accounts/acc-1/transactions*' => Http::response(['transactions' => $transactions, 'continuation_key' => null]),
        ]);
    }

    private function pendingConnection(User $user): BankConnection
    {
        return BankConnection::query()->create([
            'user_id' => $user->id,
            'provider' => BankConnection::PROVIDER_ENABLE_BANKING,
            'aspsp_name' => 'NLB d.d.',
            'aspsp_country' => 'SI',
            'institution_name' => 'NLB d.d.',
            'state' => 'state-'.uniqid(),
            'status' => BankConnection::STATUS_PENDING,
            'metadata' => ['psu_type' => 'personal', 'required_psu_headers' => []],
        ]);
    }

    /**
     * @return array{BankConnection, BankAccount}
     */
    private function connectedAccount(
        User $user,
        string $status = BankConnection::STATUS_CONNECTED,
        mixed $nextSyncAt = null,
        mixed $consentValidUntil = null,
    ): array {
        $wallet = Wallet::query()->create(['user_id' => $user->id, 'name' => 'NLB d.d.', 'type' => 'bank', 'currency' => 'EUR']);
        $isFirst = BankConnection::query()->doesntExist();

        $connection = BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => BankConnection::PROVIDER_ENABLE_BANKING,
            'aspsp_name' => 'NLB d.d.',
            'aspsp_country' => 'SI',
            'institution_name' => 'NLB d.d.',
            'state' => 'state-'.uniqid(),
            'status' => $status,
            'session_id' => $isFirst ? 'session-1' : 'session-'.uniqid(),
            'consent_valid_until' => $consentValidUntil ?? now()->addDays(170),
            'next_sync_at' => $nextSyncAt,
            'metadata' => ['psu_type' => 'personal', 'required_psu_headers' => []],
        ]);

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => $isFirst ? 'acc-1' : 'acc-'.uniqid(),
            'identification_hash' => 'hash-1',
            'name' => 'Current account',
            'currency' => 'EUR',
            'balances_encrypted' => ['current' => 0, 'available' => null],
            'is_active' => true,
        ]);

        return [$connection, $account];
    }
}
