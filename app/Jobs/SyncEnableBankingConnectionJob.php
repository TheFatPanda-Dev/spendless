<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\EnableBanking\EnableBankingClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

class SyncEnableBankingConnectionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bankConnectionId) {}

    public function handle(EnableBankingClient $client): void
    {
        $connection = BankConnection::query()
            ->with('accounts')
            ->find($this->bankConnectionId);

        if (! $connection || $connection->status !== 'active') {
            return;
        }

        $dateFrom = $connection->last_synced_at
            ? $connection->last_synced_at->copy()->subDays(2)->toDateString()
            : now()->subDays(90)->toDateString();

        try {
            foreach ($connection->accounts as $account) {
                $continuationKey = null;

                do {
                    $query = ['date_from' => $dateFrom];

                    if ($continuationKey) {
                        $query['continuation_key'] = $continuationKey;
                    }

                    $response = $client->getTransactions($account->external_uid, $query);
                    $transactions = Arr::get($response, 'transactions', []);

                    foreach ($transactions as $transaction) {
                        $externalUid = (string) Arr::get($transaction, 'entry_reference', Arr::get($transaction, 'transaction_id', ''));
                        $fallbackUid = hash('sha256', json_encode($transaction, JSON_THROW_ON_ERROR));

                        BankTransaction::updateOrCreate(
                            [
                                'bank_account_id' => $account->id,
                                'external_uid' => $externalUid !== '' ? $externalUid : $fallbackUid,
                            ],
                            [
                                'booked_at' => $this->resolveBookedAt($transaction),
                                'amount' => Arr::get($transaction, 'amount.amount'),
                                'currency' => Arr::get($transaction, 'amount.currency'),
                                'description' => $this->resolveDescription($transaction),
                                'raw_payload' => $transaction,
                            ],
                        );
                    }

                    $continuationKey = Arr::get($response, 'continuation_key');
                } while ($continuationKey);

                $account->update(['last_synced_at' => now()]);
            }

            $connection->update([
                'last_synced_at' => now(),
                'next_sync_at' => now()->addMinutes((int) config('services.enable_banking.sync_interval_minutes', 60)),
                'last_sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            $connection->update([
                'last_sync_error' => $exception->getMessage(),
                'next_sync_at' => now()->addMinutes(15),
            ]);

            throw $exception;
        }
    }

    private function resolveBookedAt(array $transaction): ?string
    {
        $value = Arr::get($transaction, 'booking_date', Arr::get($transaction, 'value_date'));

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function resolveDescription(array $transaction): ?string
    {
        $description = Arr::get($transaction, 'remittance_information_unstructured');

        if (is_string($description) && $description !== '') {
            return $description;
        }

        $creditorName = Arr::get($transaction, 'creditor.name');

        if (is_string($creditorName) && $creditorName !== '') {
            return $creditorName;
        }

        $debtorName = Arr::get($transaction, 'debtor.name');

        if (is_string($debtorName) && $debtorName !== '') {
            return $debtorName;
        }

        return null;
    }
}
