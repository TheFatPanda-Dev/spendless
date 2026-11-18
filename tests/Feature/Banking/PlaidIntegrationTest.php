<?php

namespace Tests\Feature\Banking;

use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Plaid\PlaidSync;
use App\Services\Plaid\PlaidUsage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaidIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.plaid.client_id' => 'client-id',
            'services.plaid.secret' => 'secret',
            'services.plaid.environment' => 'sandbox',
            'services.plaid.country_codes' => ['GB'],
            'services.plaid.history_days' => 90,
            'services.plaid.manual_sync_cooldown_minutes' => 60,
            'services.plaid.redirect_uri' => 'https://spendless.test/banking/plaid/oauth',
            'services.plaid.call_limit' => 200,
            'services.plaid.sync_hours' => '7',
        ]);
    }

    public function test_connecting_opens_plaid_link_and_resumes_after_the_bank_login(): void
    {
        $user = User::factory()->create();
        Http::fake(['sandbox.plaid.com/link/token/create' => Http::response(['link_token' => 'link-sandbox-1', 'expiration' => '2026-11-18T18:00:00Z'])]);

        $this->actingAs($user)->get(route('banking.plaid.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('banking/plaid-link')
                ->where('linkToken', 'link-sandbox-1')
                ->where('mode', 'connect')
                ->where('isOAuthReturn', false));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox.plaid.com/link/token/create'
            && $request['products'] === ['transactions']
            && $request['country_codes'] === ['GB']
            && $request['redirect_uri'] === 'https://spendless.test/banking/plaid/oauth'
            && $request['user'] === ['client_user_id' => (string) $user->id]
            && $request['client_id'] === 'client-id');

        // UK banks log in with OAuth and send the user back here.
        $this->actingAs($user)->get(route('banking.plaid.oauth', ['oauth_state_id' => 'abc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('linkToken', 'link-sandbox-1')
                ->where('isOAuthReturn', true));
    }

    public function test_finishing_link_creates_the_wallet_and_syncs_a_little_later(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $this->startLink($user);
        Http::fake(['sandbox.plaid.com/item/public_token/exchange' => Http::response(['access_token' => 'access-sandbox-1', 'item_id' => 'item-1'])]);

        $this->actingAs($user)->post(route('banking.plaid.exchange'), [
            'public_token' => 'public-sandbox-1',
            'institution_id' => 'ins_barclays',
            'institution_name' => 'Barclays',
        ])->assertRedirect(route('banking.plaid.complete'));

        $connection = BankConnection::query()->sole();
        $this->assertSame(BankConnection::PROVIDER_PLAID, $connection->provider);
        $this->assertSame('access-sandbox-1', $connection->credentials_encrypted['access_token']);
        $this->assertSame('ins_barclays', $connection->metadata['institution_id']);
        $this->assertSame('Barclays', $connection->wallet->name);
        Bus::assertDispatched(SyncPlaidConnectionJob::class, fn (SyncPlaidConnectionJob $job): bool => $job->bankConnectionId === $connection->id && $job->delay !== null);

        $this->actingAs($user)->get(route('banking.plaid.complete'))
            ->assertInertia(fn (Assert $page) => $page->component('banking/complete')->where('success', true));
    }

    public function test_the_same_bank_cannot_be_connected_twice(): void
    {
        $user = User::factory()->create();
        $this->plaidConnection($user, institutionId: 'ins_barclays');
        $this->startLink($user);
        Http::fake([
            'sandbox.plaid.com/item/public_token/exchange' => Http::response(['access_token' => 'access-2', 'item_id' => 'item-2']),
            'sandbox.plaid.com/item/remove' => Http::response(['request_id' => 'r']),
        ]);

        $this->actingAs($user)->post(route('banking.plaid.exchange'), [
            'public_token' => 'public-2',
            'institution_id' => 'ins_barclays',
            'institution_name' => 'Barclays',
        ]);

        $this->assertSame(1, BankConnection::query()->count());
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/item/remove') && $request['access_token'] === 'access-2');
        $this->actingAs($user)->get(route('banking.plaid.complete'))
            ->assertInertia(fn (Assert $page) => $page->where('success', false));
    }

    public function test_sync_stores_accounts_balances_and_signed_transactions(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Groceries']);
        $connection = $this->plaidConnection($user);

        Http::fakeSequence('sandbox.plaid.com/transactions/sync')
            ->push($this->page(
                added: [
                    $this->transaction('t1', 'acc-current', 12.40, 'TESCO STORES 1234'),
                    $this->transaction('t2', 'acc-current', -2500, 'ACME LTD SALARY'),
                ],
                cursor: 'c1',
                hasMore: true,
            ))
            ->push($this->page(added: [$this->transaction('t3', 'acc-card', 30, 'AMAZON')], cursor: 'c2'));

        $run = app(PlaidSync::class)->sync($connection, 'manual');

        $this->assertSame('succeeded', $run->status);
        $current = BankAccount::query()->where('external_uid', 'plaid:acc-current')->sole();
        $card = BankAccount::query()->where('external_uid', 'plaid:acc-card')->sole();
        $this->assertSame(1000.0, (float) $current->balances_encrypted['current']);
        $this->assertSame(950.0, (float) $current->balances_encrypted['available']);
        $this->assertSame(-250.0, (float) $card->balances_encrypted['current'], 'Money owed on the card counts against the total.');
        $this->assertSame('GBP', $current->currency);
        $this->assertSame('0000', $current->maskForDisplay());

        $this->assertSame('-12.40', BankTransaction::query()->where('external_uid', 't1')->sole()->amount);
        $this->assertSame('2500.00', BankTransaction::query()->where('external_uid', 't2')->sole()->amount);
        $this->assertSame('-30.00', BankTransaction::query()->where('external_uid', 't3')->sole()->amount);
        $this->assertSame($groceries->id, BankTransaction::query()->where('external_uid', 't1')->sole()->category_id);

        $connection->refresh();
        $this->assertSame('c2', $connection->metadata['cursor']);
        $this->assertSame(BankConnection::STATUS_CONNECTED, $connection->status);
        $this->assertEquals(PlaidSync::nextRun(), $connection->next_sync_at);
        $this->assertSame(2, PlaidUsage::used(), 'Each /transactions/sync page counts against the allowance.');
    }

    public function test_a_posted_payment_keeps_the_category_and_tags_given_to_the_pending_one(): void
    {
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        $fuel = Category::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Fuel']);
        $golf = Tag::query()->create(['user_id' => $user->id, 'name' => 'Golf', 'color' => 'sky']);

        Http::fakeSequence('sandbox.plaid.com/transactions/sync')
            ->push($this->page(added: [$this->transaction('pending-1', 'acc-current', 45, 'SHELL', pending: true)], cursor: 'c1'))
            ->push($this->page(
                added: [$this->transaction('posted-1', 'acc-current', 45.20, 'SHELL', pendingTransactionId: 'pending-1')],
                removed: [['transaction_id' => 'pending-1']],
                cursor: 'c2',
            ));

        app(PlaidSync::class)->sync($connection, 'manual');
        $pending = BankTransaction::query()->where('external_uid', 'pending-1')->sole();
        $this->assertTrue($pending->pending);
        $pending->forceFill(['category_id' => $fuel->id, 'category_manually_set' => true])->save();
        $pending->tags()->attach($golf);

        app(PlaidSync::class)->sync($connection->fresh(), 'scheduled');

        $this->assertNull(BankTransaction::query()->where('external_uid', 'pending-1')->first());
        $posted = BankTransaction::query()->where('external_uid', 'posted-1')->sole();
        $this->assertFalse($posted->pending);
        $this->assertSame('-45.20', $posted->amount);
        $this->assertSame($fuel->id, $posted->category_id);
        $this->assertTrue($posted->category_manually_set);
        $this->assertSame([$golf->id], $posted->tags()->pluck('tags.id')->all());
    }

    public function test_data_not_ready_yet_is_retried_soon_without_keeping_the_cursor(): void
    {
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        Http::fake(['sandbox.plaid.com/transactions/sync' => Http::response($this->page(cursor: 'too-early', status: 'NOT_READY'))]);

        app(PlaidSync::class)->sync($connection, 'connected');

        $connection->refresh();
        $this->assertNull($connection->metadata['cursor']);
        $this->assertTrue($connection->next_sync_at->between(now()->addMinutes(9), now()->addMinutes(11)));
        $this->assertSame(2, $connection->accounts()->count(), 'Accounts and balances are stored straight away.');
    }

    public function test_a_bank_that_needs_a_new_login_is_reconnected_with_link_update_mode(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        Http::fake([
            'sandbox.plaid.com/transactions/sync' => Http::response(['error_type' => 'ITEM_ERROR', 'error_code' => 'ITEM_LOGIN_REQUIRED', 'error_message' => 'the login details of this item have changed', 'display_message' => null], 400),
            'sandbox.plaid.com/link/token/create' => Http::response(['link_token' => 'link-update-1']),
        ]);

        app(PlaidSync::class)->sync($connection, 'scheduled');

        $connection->refresh();
        $this->assertSame(BankConnection::STATUS_NEEDS_REAUTH, $connection->status);
        $this->assertNull($connection->next_sync_at);

        $this->actingAs($user)->get(route('wallets.add-account', ['reconnect' => $connection->id]))
            ->assertRedirect(route('banking.plaid.show', ['reconnect' => $connection->id]));
        $this->actingAs($user)->get(route('banking.plaid.show', ['reconnect' => $connection->id]))
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'reconnect')->where('linkToken', 'link-update-1'));
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/link/token/create')
            && $request['access_token'] === 'access-sandbox-1'
            && ! isset($request['products']));

        $this->actingAs($user)->post(route('banking.plaid.exchange'), ['public_token' => 'public-update'])
            ->assertRedirect(route('banking.plaid.complete'));

        $this->assertSame(BankConnection::STATUS_SYNCING, $connection->fresh()->status);
        $this->assertSame(1, BankConnection::query()->count());
        Bus::assertDispatchedAfterResponse(SyncPlaidConnectionJob::class);
    }

    public function test_syncing_stops_before_the_free_allowance_runs_out(): void
    {
        Bus::fake();
        Http::fake();
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        $connection->forceFill(['next_sync_at' => now()->subMinute()])->save();
        PlaidUsage::record(195);

        $this->artisan('banking:dispatch-due-syncs')->assertSuccessful();
        Bus::assertNotDispatched(SyncPlaidConnectionJob::class);

        app(PlaidSync::class)->sync($connection, 'scheduled');
        Http::assertNothingSent();
        $this->assertSame(BankConnection::STATUS_SYNC_FAILED, $connection->fresh()->status);
        $this->assertStringContainsString('free allowance', (string) $connection->fresh()->last_sync_error);

        // The user can still use the last few calls by hand.
        $this->actingAs($user)->postJson(route('bank-connections.refresh', $connection))
            ->assertJson(['queued' => 1]);

        PlaidUsage::record(5);
        $connection->forceFill(['status' => BankConnection::STATUS_CONNECTED, 'last_synced_at' => now()->subDay()])->save();
        $this->actingAs($user)->postJson(route('bank-connections.refresh', $connection))
            ->assertJson(['queued' => 0]);
    }

    public function test_sync_now_for_a_uk_bank_waits_an_hour_between_syncs(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        $connection->forceFill(['last_synced_at' => now()->subMinutes(30)])->save();

        $this->actingAs($user)->postJson(route('bank-connections.refresh', $connection))
            ->assertJson(['queued' => 0]);

        $connection->forceFill(['last_synced_at' => now()->subMinutes(61)])->save();
        $this->actingAs($user)->postJson(route('bank-connections.refresh', $connection))
            ->assertJson(['queued' => 1]);
        Bus::assertDispatchedAfterResponse(SyncPlaidConnectionJob::class);
    }

    public function test_disconnecting_removes_the_bank_at_plaid(): void
    {
        $user = User::factory()->create();
        $connection = $this->plaidConnection($user);
        Http::fake(['sandbox.plaid.com/item/remove' => Http::response(['request_id' => 'r'])]);

        $this->actingAs($user)->delete(route('banking.destroy', $connection))->assertRedirect(route('wallets.index'));

        $this->assertModelMissing($connection);
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/item/remove') && $request['access_token'] === 'access-sandbox-1');
    }

    public function test_uk_banks_sync_once_a_day_in_the_morning(): void
    {
        // 07:00 in Ljubljana is 06:00 UTC in winter.
        $this->assertSame('2026-11-17 06:00', PlaidSync::nextRun(CarbonImmutable::parse('2026-11-17 05:00', 'UTC'))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-18 06:00', PlaidSync::nextRun(CarbonImmutable::parse('2026-11-17 06:00', 'UTC'))->format('Y-m-d H:i'));
    }

    public function test_another_users_bank_cannot_be_reconnected(): void
    {
        $connection = $this->plaidConnection(User::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('banking.plaid.show', ['reconnect' => $connection->id]))
            ->assertForbidden();
    }

    private function startLink(User $user): void
    {
        Http::fake(['sandbox.plaid.com/link/token/create' => Http::response(['link_token' => 'link-sandbox-1'])]);
        $this->actingAs($user)->get(route('banking.plaid.show'))->assertOk();
    }

    private function plaidConnection(User $user, string $institutionId = 'ins_nationwide'): BankConnection
    {
        $wallet = Wallet::query()->create(['user_id' => $user->id, 'name' => 'Nationwide', 'type' => 'bank', 'currency' => 'GBP']);

        return BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => BankConnection::PROVIDER_PLAID,
            'institution_name' => 'Nationwide',
            'aspsp_name' => 'Nationwide',
            'aspsp_country' => 'GB',
            'state' => 'state-'.uniqid(),
            'status' => BankConnection::STATUS_CONNECTED,
            'credentials_encrypted' => ['access_token' => 'access-sandbox-1'],
            'metadata' => ['item_id' => 'item-1', 'institution_id' => $institutionId, 'cursor' => null],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $added
     * @param  list<array<string, mixed>>  $removed
     * @return array<string, mixed>
     */
    private function page(array $added = [], array $removed = [], string $cursor = 'c1', bool $hasMore = false, string $status = 'HISTORICAL_UPDATE_COMPLETE'): array
    {
        return [
            'added' => $added,
            'modified' => [],
            'removed' => $removed,
            'next_cursor' => $cursor,
            'has_more' => $hasMore,
            'transactions_update_status' => $status,
            'accounts' => [
                ['account_id' => 'acc-current', 'name' => 'FlexDirect', 'mask' => '0000', 'type' => 'depository', 'subtype' => 'checking', 'balances' => ['current' => 1000, 'available' => 950, 'iso_currency_code' => 'GBP']],
                ['account_id' => 'acc-card', 'name' => 'Credit Card', 'mask' => '4444', 'type' => 'credit', 'subtype' => 'credit card', 'balances' => ['current' => 250, 'available' => 750, 'limit' => 1000, 'iso_currency_code' => 'GBP']],
            ],
            'request_id' => 'req',
        ];
    }

    /**
     * Plaid amounts: positive = money out.
     *
     * @return array<string, mixed>
     */
    private function transaction(string $id, string $accountId, float $amount, string $name, bool $pending = false, ?string $pendingTransactionId = null): array
    {
        return [
            'transaction_id' => $id,
            'account_id' => $accountId,
            'amount' => $amount,
            'iso_currency_code' => 'GBP',
            'date' => '2026-11-10',
            'name' => $name,
            'merchant_name' => null,
            'original_description' => $name,
            'pending' => $pending,
            'pending_transaction_id' => $pendingTransactionId,
            'counterparties' => [],
        ];
    }
}
