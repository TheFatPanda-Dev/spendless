<?php

namespace App\Support\Transactions;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\User;
use App\Support\Categories\CategoryOptions;
use Illuminate\Support\Collection;

/**
 * The transaction and category shapes the React pages share (Overview, Activity,
 * account page, add-transaction sheet).
 */
class TransactionPresenter
{
    /** @var Collection<int|string, Category>|null */
    private ?Collection $categories = null;

    public function __construct(
        private readonly User $user,
        private readonly ResolveBankTransactionCategory $resolveCategory,
    ) {}

    /**
     * @return Collection<int|string, Category>
     */
    public function categories(): Collection
    {
        return $this->categories ??= $this->user->categories()
            ->get(['id', 'user_id', 'parent_id', 'name', 'type', 'icon', 'color'])
            ->keyBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function present(BankTransaction $transaction, ?BankAccount $account = null): array
    {
        $category = $transaction->category_id !== null
            ? $this->categories()->get($transaction->category_id)
            : ($this->resolveCategory)($transaction, $this->user);
        $type = $category instanceof Category ? $category->type : ResolveBankTransactionCategory::resolveTransactionType($transaction);

        return [
            'id' => $transaction->id,
            'account_id' => $transaction->bank_account_id,
            'account' => $account !== null ? ($account->display_name ?: $account->name) : null,
            'name' => $transaction->name,
            'merchant_name' => $transaction->merchant_name,
            'counterparty' => $transaction->payee,
            'category' => $category instanceof Category ? $this->path($category) : ($type === 'income' ? 'Other Income' : 'Other'),
            'category_id' => $category?->id,
            'assigned_category_id' => $transaction->category_id,
            'category_icon' => $category?->icon ?? ($type === 'income' ? 'banknote' : 'package'),
            'category_color' => $category instanceof Category ? $this->tint($category) : null,
            'category_type' => $type,
            'category_source' => $transaction->category_manually_set ? 'manual' : ($category instanceof Category ? 'automatic' : 'fallback'),
            'amount' => (float) $transaction->amount,
            'currency' => strtoupper((string) ($transaction->currency ?: $account?->currency ?: 'EUR')),
            'date' => $transaction->date?->toDateString(),
            'pending' => (bool) $transaction->pending,
            'tag_ids' => $transaction->relationLoaded('tags') ? $transaction->tags->modelKeys() : [],
        ];
    }

    /**
     * Flat, tree-ordered category options for pickers: "Transportation / Fuel".
     *
     * @return array{expense: list<array<string, mixed>>, income: list<array<string, mixed>>}
     */
    public function categoryOptions(): array
    {
        return [
            'expense' => $this->options('expense'),
            'income' => $this->options('income'),
        ];
    }

    /**
     * Manual (cash) wallets the add-transaction sheet can write to.
     *
     * @return list<array{id: int, name: string, currency: string}>
     */
    public function manualAccounts(): array
    {
        return BankAccount::query()
            ->whereIn('bank_connection_id', $this->user->bankConnections()
                ->where('provider', BankConnection::PROVIDER_MANUAL)
                ->select('id'))
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'currency'])
            ->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'name' => (string) ($account->display_name ?: $account->name),
                'currency' => strtoupper((string) ($account->currency ?: 'EUR')),
            ])
            ->values()
            ->all();
    }

    /**
     * The category's tile colour; subcategories without their own take their parent's.
     */
    public function tint(Category $category): ?string
    {
        $current = $category;

        for ($depth = 0; $current instanceof Category && $depth < 6; $depth++) {
            if (($tint = CategoryOptions::tint($current->color)) !== null) {
                return $tint;
            }

            $current = $current->parent_id !== null ? $this->categories()->get($current->parent_id) : null;
        }

        return null;
    }

    public function path(Category $category): string
    {
        $segments = [];
        $current = $category;

        while ($current instanceof Category && count($segments) < 6) {
            array_unshift($segments, $current->name);
            $current = $current->parent_id !== null ? $this->categories()->get($current->parent_id) : null;
        }

        return implode(' / ', $segments);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function options(string $type, ?int $parentId = null, array $trail = []): array
    {
        return $this->categories()
            ->filter(fn (Category $category): bool => $category->type === $type && $category->parent_id === $parentId)
            ->sortBy('name')
            ->values()
            ->flatMap(fn (Category $category): array => [
                [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => implode(' / ', [...$trail, $category->name]),
                    'type' => $type,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'tint' => $this->tint($category),
                    'depth' => count($trail),
                ],
                ...$this->options($type, $category->id, [...$trail, $category->name]),
            ])
            ->all();
    }
}
