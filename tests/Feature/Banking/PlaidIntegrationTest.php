<?php

namespace Tests\Feature\Banking;

use App\Jobs\RefreshAllPlaidConnectionsJob;
use App\Jobs\SyncPlaidConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Plaid\PlaidTransactionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaidIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.plaid.client_id', 'test-client-id');
        config()->set('services.plaid.secret', 'test-secret');
        config()->set('services.plaid.webhook_secret', 'webhook-secret');
    }

    public function test_user_can_create_wallet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/wallets', [
            'name' => 'Primary Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'name' => 'Primary Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);
    }

    public function test_user_can_create_link_token_for_owned_wallet(): void
    {
        Http::fake([
            '*/link/token/create' => Http::response([
                'link_token' => 'link-sandbox-token',
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Primary Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/wallets/{$wallet->id}/bank-connections/plaid/link-token");

        $response->assertOk()->assertJson([
            'link_token' => 'link-sandbox-token',
        ]);
    }

    public function test_user_cannot_create_link_token_for_wallet_they_do_not_own(): void
    {
        Http::fake([
            '*/link/token/create' => Http::response([
                'link_token' => 'link-sandbox-token',
            ]),
        ]);

        $walletOwner = User::factory()->create();
        $otherUser = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $walletOwner->id,
            'name' => 'Owner Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $this->actingAs($otherUser)
            ->postJson("/wallets/{$wallet->id}/bank-connections/plaid/link-token")
            ->assertForbidden();
    }

    public function test_public_token_exchange_stores_encrypted_access_token_and_queues_initial_sync(): void
    {
        Queue::fake();

        Http::fake([
            '*/item/public_token/exchange' => Http::response([
                'access_token' => 'access-sandbox-123',
                'item_id' => 'item-123',
            ]),
            '*/item/get' => Http::response([
                'item' => [
                    'item_id' => 'item-123',
                    'institution_id' => 'ins_001',
                    'available_products' => ['transactions'],
                ],
            ]),
            '*/institutions/get_by_id' => Http::response([
                'institution' => [
                    'name' => 'Plaid Test Bank',
                ],
            ]),
            '*/accounts/get' => Http::response([
                'accounts' => [
                    [
                        'account_id' => 'acc_1',
                        'name' => 'Checking',
                        'official_name' => 'Plaid Checking',
                        'mask' => '1234',
                        'type' => 'depository',
                        'subtype' => 'checking',
                        'balances' => [
                            'current' => 500.12,
                            'available' => 490.12,
                            'iso_currency_code' => 'EUR',
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Main Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/wallets/{$wallet->id}/bank-connections/plaid/exchange", [
                'public_token' => 'public-sandbox-token',
            ]);

        $response->assertCreated();

        $connection = BankConnection::query()->where('wallet_id', $wallet->id)->firstOrFail();

        $this->assertSame('plaid', $connection->provider);
        $this->assertNotNull($connection->plaid_access_token_encrypted);

        $rawConnection = BankConnection::query()
            ->withoutGlobalScopes()
            ->where('id', $connection->id)
            ->firstOrFail()
            ->getRawOriginal('plaid_access_token_encrypted');

        $this->assertNotSame('access-sandbox-123', $rawConnection);

        Queue::assertPushed(SyncPlaidConnectionJob::class);

        $this->assertDatabaseHas('bank_accounts', [
            'bank_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_1',
        ]);

        $this->assertDatabaseHas('bank_activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'account_added',
        ]);
    }

    public function test_incremental_sync_upserts_added_modified_and_marks_removed_transactions(): void
    {
        Http::fake([
            '*/accounts/get' => Http::response([
                'accounts' => [
                    [
                        'account_id' => 'acc_1',
                        'name' => 'Checking',
                        'official_name' => 'Plaid Checking',
                        'mask' => '1234',
                        'type' => 'depository',
                        'subtype' => 'checking',
                        'balances' => [
                            'current' => 1200,
                            'available' => 1100,
                            'iso_currency_code' => 'EUR',
                        ],
                    ],
                ],
            ]),
            '*/transactions/sync' => Http::response([
                'added' => [
                    [
                        'transaction_id' => 'tx_1',
                        'account_id' => 'acc_1',
                        'name' => 'Coffee Shop',
                        'amount' => 5.5,
                        'date' => '2026-03-01',
                        'iso_currency_code' => 'EUR',
                        'pending' => false,
                    ],
                ],
                'modified' => [
                    [
                        'transaction_id' => 'tx_2',
                        'account_id' => 'acc_1',
                        'name' => 'Groceries Updated',
                        'amount' => 45,
                        'date' => '2026-03-02',
                        'iso_currency_code' => 'EUR',
                        'pending' => false,
                    ],
                ],
                'removed' => [
                    [
                        'transaction_id' => 'tx_removed',
                    ],
                ],
                'next_cursor' => 'cursor-2',
                'has_more' => false,
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Sync Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet);

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc_1',
            'plaid_account_id' => 'acc_1',
            'name' => 'Checking',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 100],
            'is_active' => true,
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account->id,
            'external_uid' => 'tx_removed',
            'plaid_transaction_id' => 'tx_removed',
            'name' => 'Old transaction',
            'amount' => 11,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-01',
            'date' => '2026-03-01',
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account->id,
            'external_uid' => 'tx_2',
            'plaid_transaction_id' => 'tx_2',
            'name' => 'Groceries',
            'amount' => 40,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => '2026-03-01',
            'date' => '2026-03-01',
        ]);

        app(PlaidTransactionsService::class)->syncItem($connection, 'manual_single');
        app(PlaidTransactionsService::class)->syncItem($connection, 'manual_single');

        $this->assertDatabaseHas('bank_transactions', [
            'bank_connection_id' => $connection->id,
            'plaid_transaction_id' => 'tx_1',
        ]);

        $this->assertSame(
            1,
            BankTransaction::query()
                ->where('bank_connection_id', $connection->id)
                ->where('plaid_transaction_id', 'tx_1')
                ->count(),
        );

        $this->assertNotNull(
            BankTransaction::query()
                ->where('bank_connection_id', $connection->id)
                ->where('plaid_transaction_id', 'tx_removed')
                ->value('removed_at'),
        );

        $this->assertDatabaseHas('bank_activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'data_fetched',
        ]);
    }

    public function test_manual_refresh_all_queues_refresh_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/wallets/refresh-all')
            ->assertOk();

        Queue::assertPushed(RefreshAllPlaidConnectionsJob::class);

        $this->assertDatabaseHas('bank_activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'sync_all_requested',
        ]);
    }

    public function test_sync_status_endpoint_reports_when_connections_are_syncing(): void
    {
        $user = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Status Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet, 'sync-status-item');
        $connection->update(['status' => 'syncing']);

        $this->actingAs($user)
            ->getJson('/wallets/sync-status')
            ->assertOk()
            ->assertJson([
                'is_syncing' => true,
                'syncing_connections' => 1,
                'total_connections' => 1,
            ]);
    }

    public function test_manual_refresh_single_queues_connection_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);
        $connection = $this->createPlaidConnection($user, $wallet);

        $this->actingAs($user)
            ->postJson("/bank-connections/{$connection->id}/refresh")
            ->assertOk();

        Queue::assertPushed(SyncPlaidConnectionJob::class, function (SyncPlaidConnectionJob $job) use ($connection): bool {
            return $job->bankConnectionId === $connection->id;
        });
    }

    public function test_webhook_triggers_sync_and_archives_payload(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($user, $wallet, 'item_webhook_123');

        $this->postJson(
            '/plaid/webhook',
            [
                'webhook_type' => 'TRANSACTIONS',
                'webhook_code' => 'SYNC_UPDATES_AVAILABLE',
                'item_id' => 'item_webhook_123',
            ],
            ['Plaid-Webhook-Secret' => 'webhook-secret'],
        )->assertOk();

        Queue::assertPushed(SyncPlaidConnectionJob::class, function (SyncPlaidConnectionJob $job) use ($connection): bool {
            return $job->bankConnectionId === $connection->id;
        });

        $this->assertDatabaseHas('plaid_webhook_events', [
            'bank_connection_id' => $connection->id,
            'webhook_type' => 'TRANSACTIONS',
            'webhook_code' => 'SYNC_UPDATES_AVAILABLE',
        ]);
    }

    public function test_authorization_boundary_blocks_wallet_access(): void
    {
        $walletOwner = User::factory()->create();
        $otherUser = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $walletOwner->id,
            'name' => 'Private Wallet',
            'type' => 'general',
            'currency' => 'EUR',
        ]);

        $this->actingAs($otherUser)
            ->get("/wallets/{$wallet->id}")
            ->assertForbidden();
    }

    public function test_quick_connect_creates_wallet_and_redirects_to_auto_connect(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/wallets/quick-connect');

        $response->assertRedirect();

        $wallet = Wallet::query()->where('user_id', $user->id)->latest('id')->first();

        $this->assertNotNull($wallet);
        $this->assertSame('bank', $wallet->type);
        $this->assertStringContainsString('/wallets/'.$wallet->id.'?auto_connect=1', $response->headers->get('Location', ''));
    }

    public function test_account_page_shows_monthly_transactions_for_owner_only(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $wallet = Wallet::query()->create([
            'user_id' => $owner->id,
            'name' => 'Main Wallet',
            'type' => 'bank',
            'currency' => 'EUR',
        ]);

        $connection = $this->createPlaidConnection($owner, $wallet);

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc_dashboard',
            'plaid_account_id' => 'acc_dashboard',
            'name' => 'Everyday Account',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => ['current' => 325.22],
            'is_active' => true,
        ]);

        BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account->id,
            'external_uid' => 'tx_current_month',
            'plaid_transaction_id' => 'tx_current_month',
            'name' => 'Groceries',
            'amount' => 41.8,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => now()->startOfMonth()->addDay()->toDateString(),
            'date' => now()->startOfMonth()->addDay()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get("/accounts/{$account->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/show')
                ->where('account.id', $account->id)
                ->has('transactions', 1));

        $this->actingAs($otherUser)
            ->get("/accounts/{$account->id}")
            ->assertForbidden();
    }

    private function createPlaidConnection(User $user, Wallet $wallet, string $itemId = 'item_123'): BankConnection
    {
        return BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => 'plaid',
            'plaid_item_id_hash' => hash('sha256', $itemId),
            'plaid_item_id_encrypted' => $itemId,
            'plaid_access_token_encrypted' => 'access-token',
            'institution_id' => 'ins_001',
            'institution_name' => 'Plaid Test Bank',
            'status' => 'connected',
            'aspsp_name' => 'Plaid Test Bank',
            'aspsp_country' => 'US',
            'state' => (string) fake()->uuid(),
            'authorized_at' => now(),
        ]);
    }
}
