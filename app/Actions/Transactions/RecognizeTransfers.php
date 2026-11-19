<?php

namespace App\Actions\Transactions;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use App\Services\Reports\MonthlySpending;
use Illuminate\Support\Collection;

/**
 * Re-checks a user's automatically categorised transactions and moves the ones that are
 * clearly transfers (a currency exchange, money to or from IBKR or a pension) into a
 * transfer category. Categories the user chose by hand are never changed.
 */
class RecognizeTransfers
{
    public function __construct(private readonly ResolveBankTransactionCategory $resolveCategory) {}

    public function handle(User $user): int
    {
        $transferIds = Category::query()
            ->where('user_id', $user->id)
            ->where('type', 'transfer')
            ->pluck('id');

        if ($transferIds->isEmpty()) {
            return 0;
        }

        $moved = 0;

        BankTransaction::query()
            ->whereIn('bank_account_id', MonthlySpending::accountIds($user))
            ->where('category_manually_set', false)
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereNotIn('category_id', $transferIds))
            ->chunkById(500, function (Collection $transactions) use ($user, &$moved): void {
                foreach ($transactions as $transaction) {
                    $category = ($this->resolveCategory)($transaction, $user);

                    if ($category instanceof Category && $category->type === 'transfer') {
                        $transaction->forceFill(['category_id' => $category->id])->saveQuietly();
                        $moved++;
                    }
                }
            });

        return $moved;
    }
}
