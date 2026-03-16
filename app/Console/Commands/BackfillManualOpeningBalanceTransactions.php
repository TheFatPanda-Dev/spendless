<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Console\Command;

class BackfillManualOpeningBalanceTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallets:backfill-manual-opening-balances {--dry-run : List accounts that would be backfilled without writing transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill opening balance transactions for older manual cash accounts that have no transactions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $eligibleAccounts = BankAccount::query()
            ->whereHas('connection', fn ($query) => $query
                ->where('provider', 'manual')
                ->whereHas('wallet', fn ($walletQuery) => $walletQuery->where('type', 'cash')))
            ->whereDoesntHave('transactions')
            ->with(['connection.wallet'])
            ->get();

        if ($eligibleAccounts->isEmpty()) {
            $this->info('No manual cash accounts eligible for backfill.');

            return self::SUCCESS;
        }

        $shouldDryRun = (bool) $this->option('dry-run');
        $createdCount = 0;
        $skippedZeroBalanceCount = 0;

        foreach ($eligibleAccounts as $account) {
            $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];
            $openingBalance = round((float) ($balances['current'] ?? 0), 2);

            if ($openingBalance === 0.0) {
                $skippedZeroBalanceCount++;

                continue;
            }

            $currency = (string) ($account->currency_code ?: $account->currency ?: $account->connection?->wallet?->currency ?: 'EUR');
            $openedOn = $account->created_at?->toDateString() ?? now()->toDateString();

            if ($shouldDryRun) {
                $this->line(sprintf(
                    'Would backfill account #%d (%s) with %.2f %s',
                    $account->id,
                    $account->display_name ?? $account->name ?? 'Manual account',
                    $openingBalance,
                    $currency,
                ));

                $createdCount++;

                continue;
            }

            BankTransaction::query()->create([
                'bank_connection_id' => $account->bank_connection_id,
                'bank_account_id' => $account->id,
                'external_uid' => 'manual-opening-balance-backfill-'.$account->id,
                'name' => 'Opening balance',
                'amount' => $openingBalance,
                'currency' => $currency,
                'iso_currency_code' => $currency,
                'booked_at' => $openedOn,
                'date' => $openedOn,
                'pending' => false,
                'description' => 'Opening balance (backfilled)',
            ]);

            $createdCount++;
        }

        if ($shouldDryRun) {
            $this->info(sprintf('Dry run complete: %d transaction(s) would be created.', $createdCount));
        } else {
            $this->info(sprintf('Backfill complete: created %d opening balance transaction(s).', $createdCount));
        }

        if ($skippedZeroBalanceCount > 0) {
            $this->line(sprintf('Skipped %d account(s) with zero balance.', $skippedZeroBalanceCount));
        }

        return self::SUCCESS;
    }
}
