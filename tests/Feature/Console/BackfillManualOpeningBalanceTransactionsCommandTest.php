<?php

namespace Tests\Feature\Console;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillManualOpeningBalanceTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{wallet: Wallet, connection: BankConnection, account: BankAccount}
     */
    private function createAccount(
        User $user,
        float $balance,
        string $walletType = 'cash',
        string $provider = 'manual',
    ): array {
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'name' => 'Wallet '.Str::random(8),
            'type' => $walletType,
            'currency' => 'EUR',
            'last_synced_at' => now(),
        ]);

        $connection = BankConnection::query()->create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'provider' => $provider,
            'institution_name' => 'Manual wallet',
            'aspsp_name' => 'Manual wallet',
            'aspsp_country' => 'ZZ',
            'state' => (string) Str::uuid(),
            'status' => 'connected',
            'authorized_at' => now(),
            'last_synced_at' => now(),
        ]);

        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'external_uid' => 'manual-account-'.Str::uuid(),
            'name' => 'Cash Account '.Str::random(4),
            'display_name' => 'Cash Account '.Str::random(4),
            'type' => 'cash',
            'subtype' => 'manual',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'balances_encrypted' => [
                'current' => $balance,
                'available' => $balance,
            ],
            'is_active' => true,
            'last_synced_at' => now(),
        ]);

        return [
            'wallet' => $wallet,
            'connection' => $connection,
            'account' => $account,
        ];
    }

    public function test_dry_run_reports_backfill_without_creating_transactions(): void
    {
        $user = User::factory()->create();

        $manual = $this->createAccount($user, 125.50);

        $this->artisan('wallets:backfill-manual-opening-balances --dry-run')
            ->expectsOutputToContain('Would backfill account #'.$manual['account']->id)
            ->expectsOutput('Dry run complete: 1 transaction(s) would be created.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_command_backfills_only_eligible_manual_cash_accounts(): void
    {
        $user = User::factory()->create();

        $eligible = $this->createAccount($user, 250.75, 'cash', 'manual');
        $zeroBalance = $this->createAccount($user, 0.0, 'cash', 'manual');
        $alreadyHasTransaction = $this->createAccount($user, 88.10, 'cash', 'manual');
        $plaid = $this->createAccount($user, 40.0, 'cash', 'plaid');
        $stockWallet = $this->createAccount($user, 60.0, 'stock', 'manual');

        BankTransaction::query()->create([
            'bank_connection_id' => $alreadyHasTransaction['connection']->id,
            'bank_account_id' => $alreadyHasTransaction['account']->id,
            'external_uid' => 'existing-manual-transaction',
            'name' => 'Existing transaction',
            'amount' => -5.00,
            'currency' => 'EUR',
            'iso_currency_code' => 'EUR',
            'booked_at' => now()->toDateString(),
            'date' => now()->toDateString(),
            'pending' => false,
            'description' => null,
        ]);

        $this->artisan('wallets:backfill-manual-opening-balances')
            ->expectsOutput('Backfill complete: created 1 opening balance transaction(s).')
            ->expectsOutput('Skipped 1 account(s) with zero balance.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $eligible['account']->id,
            'external_uid' => 'manual-opening-balance-backfill-'.$eligible['account']->id,
            'name' => 'Opening balance',
            'amount' => 250.75,
        ]);

        $this->assertDatabaseMissing('bank_transactions', [
            'bank_account_id' => $zeroBalance['account']->id,
            'external_uid' => 'manual-opening-balance-backfill-'.$zeroBalance['account']->id,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'bank_account_id' => $plaid['account']->id,
            'external_uid' => 'manual-opening-balance-backfill-'.$plaid['account']->id,
        ]);
        $this->assertDatabaseMissing('bank_transactions', [
            'bank_account_id' => $stockWallet['account']->id,
            'external_uid' => 'manual-opening-balance-backfill-'.$stockWallet['account']->id,
        ]);

        $this->assertSame(1, BankTransaction::query()
            ->where('bank_account_id', $alreadyHasTransaction['account']->id)
            ->count());

        $this->assertSame(2, BankTransaction::query()->count());
    }
}
