<?php

namespace App\Services\Tags;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\TagRule;
use App\Models\User;
use App\Support\Text\SearchText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies a user's tag rules. New transactions are checked once (after a sync, an import
 * or a manual entry); a tag removed by hand afterwards is never put back.
 */
class TagRules
{
    private const CHUNK = 500;

    private const COLUMNS = ['id', 'bank_account_id', 'merchant_name', 'payee', 'name', 'description'];

    /**
     * Tag every transaction the rules haven't looked at yet.
     *
     * @return int Tags added.
     */
    public function applyToNew(User $user): int
    {
        $accountIds = $this->accountIds($user);

        if ($accountIds === []) {
            return 0;
        }

        $rules = TagRule::query()->where('user_id', $user->id)->get();
        $added = 0;

        BankTransaction::query()
            ->whereIn('bank_account_id', $accountIds)
            ->where('tag_rules_applied', false)
            ->select(self::COLUMNS)
            ->chunkById(self::CHUNK, function (Collection $transactions) use ($rules, &$added): void {
                if ($rules->isNotEmpty()) {
                    $added += $this->attach($this->pairs($rules, $transactions));
                }

                BankTransaction::query()
                    ->whereIn('id', $transactions->modelKeys())
                    ->update(['tag_rules_applied' => true]);
            });

        return $added;
    }

    /**
     * Run one rule over all of its owner's existing transactions.
     *
     * @return int Tags added.
     */
    public function applyRule(TagRule $rule): int
    {
        $accountIds = $this->accountIds($rule->user);

        if ($rule->bank_account_id !== null) {
            $accountIds = in_array($rule->bank_account_id, $accountIds, true) ? [$rule->bank_account_id] : [];
        }

        if ($accountIds === []) {
            return 0;
        }

        $added = 0;
        $rules = collect([$rule]);

        BankTransaction::query()
            ->whereIn('bank_account_id', $accountIds)
            ->select(self::COLUMNS)
            ->chunkById(self::CHUNK, function (Collection $transactions) use ($rules, &$added): void {
                $added += $this->attach($this->pairs($rules, $transactions));
            });

        return $added;
    }

    public static function matches(TagRule $rule, BankTransaction $transaction, ?string $text = null): bool
    {
        if ($rule->bank_account_id !== null && $rule->bank_account_id !== (int) $transaction->bank_account_id) {
            return false;
        }

        $needle = SearchText::normalize($rule->contains);

        if ($needle === '') {
            // An account-only rule; a rule with neither condition never matches.
            return $rule->bank_account_id !== null;
        }

        return str_contains($text ?? SearchText::forTransaction($transaction), $needle);
    }

    /**
     * @param  Collection<int, TagRule>  $rules
     * @param  Collection<int, BankTransaction>  $transactions
     * @return list<array{bank_transaction_id: int, tag_id: int}>
     */
    private function pairs(Collection $rules, Collection $transactions): array
    {
        $pairs = [];

        foreach ($transactions as $transaction) {
            $text = SearchText::forTransaction($transaction);

            foreach ($rules as $rule) {
                if (self::matches($rule, $transaction, $text)) {
                    $pairs[$transaction->id.':'.$rule->tag_id] = [
                        'bank_transaction_id' => $transaction->id,
                        'tag_id' => $rule->tag_id,
                    ];
                }
            }
        }

        return array_values($pairs);
    }

    /**
     * @param  list<array{bank_transaction_id: int, tag_id: int}>  $pairs
     */
    private function attach(array $pairs): int
    {
        $added = 0;

        foreach (array_chunk($pairs, self::CHUNK) as $chunk) {
            $added += DB::table('bank_transaction_tag')->insertOrIgnore($chunk);
        }

        return $added;
    }

    /**
     * @return list<int>
     */
    private function accountIds(User $user): array
    {
        return BankAccount::query()
            ->whereIn('bank_connection_id', $user->bankConnections()->select('id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
