<?php

namespace Tests\Feature\Banking;

use App\Jobs\SyncEnableBankingConnectionJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\EnableBanking\EnableBankingClient;
use App\Services\EnableBanking\EnableBankingJwtFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class EnableBankingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_institutions_endpoint_returns_empty_payload_when_enable_banking_is_not_configured(): void
    {
        $this->mock(EnableBankingClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAspsps')
                ->once()
                ->with('SI')
                ->andThrow(new RuntimeException('Enable Banking credentials are not configured.'));
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('banking.institutions'));

        $response->assertOk()->assertJson([
            'configured' => false,
            'preferred' => [],
            'institutions' => [],
            'message' => 'Enable Banking is not configured for this environment.',
        ]);
    }

    public function test_authenticated_user_can_start_enable_banking_authorization(): void
    {
        $this->fakeJwtFactory();

        Http::fake([
            'https://api.enablebanking.com/aspsps*' => Http::response([
                'aspsps' => [
                    ['name' => 'Delavska hranilnica d.d.', 'country' => 'SI'],
                    ['name' => 'Revolut Bank UAB', 'country' => 'SI'],
                ],
            ]),
            'https://api.enablebanking.com/auth' => Http::response([
                'url' => 'https://bank.example/authorize?flow=123',
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('banking.start'), [
            'aspsp_name' => 'Delavska hranilnica d.d.',
            'aspsp_country' => 'SI',
            'psu_type' => 'personal',
        ]);

        $response->assertRedirect('https://bank.example/authorize?flow=123');

        $this->assertDatabaseHas('bank_connections', [
            'user_id' => $user->id,
            'aspsp_name' => 'Delavska hranilnica d.d.',
            'aspsp_country' => 'SI',
            'status' => 'pending',
        ]);
    }

    public function test_inertia_request_redirects_with_inertia_location_header(): void
    {
        $this->fakeJwtFactory();

        Http::fake([
            'https://api.enablebanking.com/aspsps*' => Http::response([
                'aspsps' => [
                    ['name' => 'Delavska hranilnica d.d.', 'country' => 'SI'],
                    ['name' => 'Revolut Bank UAB', 'country' => 'SI'],
                ],
            ]),
            'https://api.enablebanking.com/auth' => Http::response([
                'url' => 'https://bank.example/authorize?flow=456',
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('banking.start'), [
                'aspsp_name' => 'Revolut',
                'aspsp_country' => 'SI',
                'psu_type' => 'personal',
            ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://bank.example/authorize?flow=456');
    }

    public function test_callback_stores_session_accounts_and_dispatches_sync_job(): void
    {
        $this->fakeJwtFactory();
        Queue::fake();

        Http::fake([
            'https://api.enablebanking.com/sessions' => Http::response([
                'session_id' => 'sess-123',
                'accounts' => [
                    [
                        'uid' => 'acc-1',
                        'name' => 'Main account',
                        'currency' => 'EUR',
                        'identification' => ['iban' => 'SI56020170014356205'],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $connection = BankConnection::create([
            'user_id' => $user->id,
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'SI',
            'state' => 'state-123',
            'status' => 'pending',
            'consent_valid_until' => now()->addDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('banking.callback', [
            'code' => 'code-123',
            'state' => 'state-123',
        ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_connections', [
            'id' => $connection->id,
            'status' => 'active',
            'session_id' => 'sess-123',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-1',
            'currency' => 'EUR',
        ]);

        Queue::assertPushed(SyncEnableBankingConnectionJob::class);
    }

    public function test_start_redirects_back_with_error_when_enable_banking_is_not_configured(): void
    {
        $this->mock(EnableBankingJwtFactory::class, function (MockInterface $mock): void {
            $mock->shouldReceive('makeToken')
                ->andThrow(new RuntimeException('Enable Banking credentials are not configured.'));
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('banking.start'), [
                'aspsp_name' => 'Revolut',
                'aspsp_country' => 'SI',
                'psu_type' => 'personal',
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Enable Banking is not configured for this environment.');

        $this->assertDatabaseCount('bank_connections', 0);
    }

    public function test_sync_job_persists_transactions_with_continuation_key(): void
    {
        $this->fakeJwtFactory();

        Http::fake([
            'https://api.enablebanking.com/accounts/acc-1/transactions*' => Http::sequence()
                ->push([
                    'transactions' => [
                        [
                            'entry_reference' => 'tx-1',
                            'booking_date' => '2026-03-03',
                            'amount' => ['amount' => '10.50', 'currency' => 'EUR'],
                            'remittance_information_unstructured' => 'Grocery',
                        ],
                    ],
                    'continuation_key' => 'next-1',
                ])
                ->push([
                    'transactions' => [
                        [
                            'entry_reference' => 'tx-2',
                            'booking_date' => '2026-03-04',
                            'amount' => ['amount' => '-3.20', 'currency' => 'EUR'],
                            'creditor' => ['name' => 'Coffee Shop'],
                        ],
                    ],
                ]),
        ]);

        $user = User::factory()->create();

        $connection = BankConnection::create([
            'user_id' => $user->id,
            'aspsp_name' => 'Revolut',
            'aspsp_country' => 'SI',
            'state' => 'state-456',
            'status' => 'active',
            'session_id' => 'sess-456',
            'authorized_at' => now(),
            'consent_valid_until' => now()->addDays(10),
        ]);

        $account = BankAccount::create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'acc-1',
            'name' => 'Main account',
            'currency' => 'EUR',
        ]);

        (new SyncEnableBankingConnectionJob($connection->id))->handle(app('App\\Services\\EnableBanking\\EnableBankingClient'));

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $account->id,
            'external_uid' => 'tx-1',
            'description' => 'Grocery',
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $account->id,
            'external_uid' => 'tx-2',
            'description' => 'Coffee Shop',
        ]);

        $this->assertDatabaseCount('bank_transactions', 2);
        $this->assertDatabaseHas('bank_connections', [
            'id' => $connection->id,
            'status' => 'active',
        ]);
    }

    private function fakeJwtFactory(): void
    {
        $this->mock(EnableBankingJwtFactory::class, function (MockInterface $mock): void {
            $mock->shouldReceive('makeToken')
                ->andReturn('test-token');
        });
    }
}
