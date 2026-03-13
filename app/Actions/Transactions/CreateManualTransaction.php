<?php

namespace App\Actions\Transactions;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateManualTransaction
{
    /**
     * @param  array{category: Category, amount: float, date: string, merchant_name: string|null, transaction_name: string|null}  $attributes
     */
    public function __invoke(BankAccount $bankAccount, array $attributes): BankTransaction
    {
        return DB::transaction(function () use ($bankAccount, $attributes): BankTransaction {
            $category = $attributes['category'];
            $amount = round((float) $attributes['amount'], 2);
            $signedAmount = $category->type === 'income' ? $amount : -$amount;
            $currency = (string) ($bankAccount->currency_code ?: $bankAccount->currency ?: 'EUR');
            $merchantName = $attributes['merchant_name'];
            $transactionName = $attributes['transaction_name'] ?: $merchantName ?: 'Manual transaction';
            $balances = is_array($bankAccount->balances_encrypted) ? $bankAccount->balances_encrypted : [];
            $currentBalance = round((float) ($balances['current'] ?? 0) + $signedAmount, 2);
            $availableBalance = array_key_exists('available', $balances)
                ? round((float) ($balances['available'] ?? 0) + $signedAmount, 2)
                : $currentBalance;

            $transaction = BankTransaction::query()->create([
                'bank_connection_id' => $bankAccount->bank_connection_id,
                'bank_account_id' => $bankAccount->id,
                'category_id' => $category->id,
                'category_manually_set' => true,
                'external_uid' => 'manual-transaction-'.Str::uuid(),
                'merchant_name' => $merchantName,
                'name' => $transactionName,
                'amount' => $signedAmount,
                'currency' => $currency,
                'iso_currency_code' => $currency,
                'booked_at' => $attributes['date'],
                'date' => $attributes['date'],
                'pending' => false,
                'description' => null,
            ]);

            $bankAccount->forceFill([
                'balances_encrypted' => [
                    ...$balances,
                    'current' => $currentBalance,
                    'available' => $availableBalance,
                ],
                'last_synced_at' => now(),
            ])->save();

            $connection = $bankAccount->connection;

            if ($connection !== null) {
                $connection->forceFill([
                    'last_synced_at' => now(),
                ])->save();

                if ($connection->wallet !== null) {
                    $connection->wallet->forceFill([
                        'last_synced_at' => now(),
                    ])->save();
                }
            }

            return $transaction;
        });
    }
}
