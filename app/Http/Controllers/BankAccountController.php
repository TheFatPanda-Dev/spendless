<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateManualTransaction;
use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Http\Requests\Banking\StoreManualTransactionRequest;
use App\Http\Requests\Banking\UpdateBankTransactionRequest;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Support\Categories\CategoryOptions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function show(
        Request $request,
        BankAccount $bankAccount,
        ResolveBankTransactionCategory $resolveBankTransactionCategory,
    ): Response {
        $this->ensureAccountIsAccessible($request, $bankAccount);

        $startDate = Carbon::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse((string) $request->query('end_date', now()->endOfMonth()->toDateString()))->endOfDay();
        $user = $request->user();
        $categories = $user->categories()
            ->select(['id', 'user_id', 'parent_id', 'name', 'type', 'icon', 'color'])
            ->orderBy('name')
            ->get();
        $categoriesById = $categories->keyBy('id');

        $bankAccount->load([
            'connection',
            'transactions' => fn ($query) => $query
                ->with('assignedCategory')
                ->whereNull('removed_at')
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->latest('date')
                ->limit(200),
        ]);

        $transactions = $bankAccount->transactions
            ->map(function (BankTransaction $transaction) use ($categoriesById, $resolveBankTransactionCategory, $user): array {
                $resolvedCategory = $transaction->assignedCategory
                    ?? ($resolveBankTransactionCategory)($transaction, $user);
                $inferredTransactionType = ResolveBankTransactionCategory::resolveTransactionType($transaction);
                $displayCategoryType = $resolvedCategory instanceof Category
                    ? $resolvedCategory->type
                    : $inferredTransactionType;
                $fallbackCategory = $displayCategoryType === 'income' ? 'Other Income' : 'Other';

                return [
                    'id' => $transaction->id,
                    'name' => $transaction->name,
                    'merchant_name' => $transaction->merchant_name,
                    'counterparty' => $transaction->payee,
                    'category' => $resolvedCategory instanceof Category
                        ? $this->formatCategoryPath($resolvedCategory, $categoriesById)
                        : $fallbackCategory,
                    'category_id' => $resolvedCategory?->id,
                    'assigned_category_id' => $transaction->category_id,
                    'category_icon' => $resolvedCategory?->icon ?? 'package',
                    'category_color' => $resolvedCategory?->color ?? ($displayCategoryType === 'income' ? 'emerald' : 'slate'),
                    'category_source' => $transaction->category_manually_set
                        ? 'manual'
                        : ($resolvedCategory instanceof Category ? 'automatic' : 'fallback'),
                    'category_type' => $displayCategoryType,
                    'provider_category' => $this->formatProviderCategory($transaction),
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->iso_currency_code ?: $transaction->currency,
                    'date' => $transaction->date?->toDateString(),
                    'pending' => $transaction->pending,
                ];
            })
            ->values();

        return Inertia::render('accounts/show', [
            'account' => [
                'id' => $bankAccount->id,
                'display_name' => $bankAccount->display_name,
                'name' => $bankAccount->name,
                'official_name' => $bankAccount->official_name,
                'institution_name' => $bankAccount->connection?->institution_name,
                'provider' => $bankAccount->connection?->provider,
                'is_manual' => $bankAccount->connection?->provider === 'manual',
                'mask' => $bankAccount->mask_encrypted ? str_pad(substr((string) $bankAccount->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                'currency' => $bankAccount->currency_code ?: $bankAccount->currency,
                'balances' => $bankAccount->balances_encrypted,
                'last_synced_at' => $bankAccount->last_synced_at?->toIso8601String(),
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'categoryOptions' => [
                'expense' => $this->buildCategoryOptions($categories, 'expense'),
                'income' => $this->buildCategoryOptions($categories, 'income'),
            ],
            'categoryColors' => CategoryOptions::colors(),
            'transactions' => $transactions,
        ]);
    }

    public function update(
        UpdateBankTransactionRequest $request,
        BankAccount $bankAccount,
        BankTransaction $bankTransaction,
    ): RedirectResponse {
        $this->ensureAccountIsAccessible($request, $bankAccount);

        abort_unless($bankTransaction->bank_account_id === $bankAccount->id, 403);

        $updates = [];

        if ($request->exists('category_id')) {
            $categoryId = $request->integer('category_id');

            $updates['category_id'] = $categoryId;
            $updates['category_manually_set'] = $categoryId !== 0;
        }

        if ($request->exists('name')) {
            $updates['name'] = $request->validated('name');
        }

        if ($updates !== []) {
            $bankTransaction->forceFill($updates)->save();
        }

        return back()->with('success', 'Transaction updated.');
    }

    public function storeManualTransaction(
        StoreManualTransactionRequest $request,
        BankAccount $bankAccount,
        CreateManualTransaction $createManualTransaction,
    ): RedirectResponse {
        $this->ensureAccountIsAccessible($request, $bankAccount);

        abort_unless($bankAccount->connection?->provider === 'manual', 403);

        $category = Category::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->integer('category_id'));

        $createManualTransaction($bankAccount, [
            'category' => $category,
            'amount' => (float) $request->validated('amount'),
            'date' => $request->validated('date'),
            'merchant_name' => $request->validated('merchant_name'),
            'transaction_name' => $request->validated('transaction_name'),
        ]);

        return to_route('accounts.show', [
            'bankAccount' => $bankAccount,
            'start_date' => (string) $request->query('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => (string) $request->query('end_date', now()->endOfMonth()->toDateString()),
        ])->with('success', 'Manual transaction added.');
    }

    private function ensureAccountIsAccessible(Request $request, BankAccount $bankAccount): void
    {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id,
            403,
        );
    }

    /**
     * @return list<array{id: int, name: string, path: string, type: string, icon: string, color: string}>
     */
    private function buildCategoryOptions(Collection $categories, string $type, ?int $parentId = null, array $trail = []): array
    {
        return $categories
            ->filter(fn (Category $category): bool => $category->type === $type && $category->parent_id === $parentId)
            ->sortBy('name')
            ->values()
            ->flatMap(function (Category $category) use ($categories, $type, $trail): array {
                $pathSegments = [...$trail, $category->name];

                return [
                    [
                        'id' => $category->id,
                        'name' => $category->name,
                        'path' => implode(' / ', $pathSegments),
                        'type' => $type,
                        'icon' => $category->icon,
                        'color' => $category->color,
                    ],
                    ...$this->buildCategoryOptions($categories, $type, $category->id, $pathSegments),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int|string, Category>  $categoriesById
     */
    private function formatCategoryPath(Category $category, Collection $categoriesById): string
    {
        $segments = [];
        $current = $category;

        while ($current instanceof Category) {
            array_unshift($segments, $current->name);
            $current = $current->parent_id !== null
                ? $categoriesById->get($current->parent_id)
                : null;
        }

        return implode(' / ', $segments);
    }

    private function formatProviderCategory(BankTransaction $transaction): ?string
    {
        $detailedPersonalFinanceCategory = data_get($transaction->personal_finance_category, 'detailed');

        if (is_string($detailedPersonalFinanceCategory) && $detailedPersonalFinanceCategory !== '') {
            return (string) str($detailedPersonalFinanceCategory)
                ->replace('_', ' ')
                ->lower()
                ->title();
        }

        if (is_array($transaction->category) && $transaction->category !== []) {
            return implode(' / ', array_filter($transaction->category, fn ($value): bool => is_string($value) && $value !== ''));
        }

        return null;
    }
}
