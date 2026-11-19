<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CreateManualTransaction;
use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Http\Requests\Banking\StoreManualTransactionRequest;
use App\Http\Requests\Banking\UpdateBankTransactionRequest;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Support\Categories\CategoryOptions;
use App\Support\Transactions\TransactionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        [$startDate, $endDate] = DashboardController::month($request);
        $user = $request->user();
        $presenter = new TransactionPresenter($user, $resolveBankTransactionCategory);

        $bankAccount->load([
            'connection',
            'transactions' => fn ($query) => $query
                ->with(['tags' => fn ($tags) => $tags->select('tags.id')])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->latest('date')
                ->latest('id')
                ->limit(300),
        ]);

        $transactions = $bankAccount->transactions
            ->map(fn (BankTransaction $transaction): array => $presenter->present($transaction, $bankAccount))
            ->values();

        return Inertia::render('accounts/show', [
            'account' => [
                'id' => $bankAccount->id,
                'display_name' => $bankAccount->display_name,
                'name' => $bankAccount->name,
                'official_name' => $bankAccount->official_name,
                'institution_name' => $bankAccount->connection?->institution_name,
                'institution_logo' => $bankAccount->connection?->institution_logo,
                'provider' => $bankAccount->connection?->provider,
                'wallet_id' => $bankAccount->connection?->wallet_id,
                'subtype' => $bankAccount->subtype,
                'is_manual' => (bool) $bankAccount->connection?->isManual(),
                'connection' => $bankAccount->connection?->toStatusArray(),
                'last_import' => $bankAccount->connection?->provider === BankConnection::PROVIDER_IMPORT
                    ? $bankAccount->statementImports()->latest()->first()?->toSummaryArray()
                    : null,
                'mask' => $bankAccount->maskForDisplay(),
                'currency' => $bankAccount->currency,
                'balances' => $bankAccount->balances_encrypted,
                'last_synced_at' => $bankAccount->last_synced_at?->toIso8601String(),
            ],
            'month' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            'categoryOptions' => $presenter->categoryOptions(),
            'categoryColors' => CategoryOptions::colors(),
            'tags' => $user->tags()->orderBy('name')->get(['id', 'name', 'color']),
            'transactions' => $transactions,
            'addTransaction' => [
                'accounts' => $bankAccount->connection?->isManual()
                    ? [['id' => $bankAccount->id, 'name' => (string) ($bankAccount->display_name ?: $bankAccount->name), 'currency' => strtoupper((string) ($bankAccount->currency ?: 'EUR'))]]
                    : [],
                'categories' => $presenter->categoryOptions(),
            ],
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
            $categoryId = $request->validated('category_id');

            if (! is_int($categoryId) && $categoryId !== null) {
                throw ValidationException::withMessages([
                    'category_id' => 'The selected category is invalid.',
                ]);
            }

            $updates['category_id'] = $categoryId;
            $updates['category_manually_set'] = $categoryId !== null;
        }

        if ($request->exists('name')) {
            $updates['name'] = $request->validated('name');
        }

        if ($request->exists('merchant_name')) {
            $updates['merchant_name'] = $request->validated('merchant_name');
        }

        if ($updates !== []) {
            $bankTransaction->forceFill($updates)->save();
        }

        if ($request->exists('tag_ids')) {
            $bankTransaction->tags()->sync($request->validated('tag_ids') ?? []);
        }

        // Edited from Overview or Activity: stay there.
        if ($this->cameFromElsewhere($request)) {
            return back()->with('success', 'Transaction updated.');
        }

        return to_route('accounts.show', [
            'bankAccount' => $bankAccount,
            'start_date' => (string) $request->query('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => (string) $request->query('end_date', now()->endOfMonth()->toDateString()),
        ])->with('success', 'Transaction updated.');
    }

    public function storeManualTransaction(
        StoreManualTransactionRequest $request,
        BankAccount $bankAccount,
        CreateManualTransaction $createManualTransaction,
    ): RedirectResponse {
        $this->ensureAccountIsAccessible($request, $bankAccount);

        abort_unless((bool) $bankAccount->connection?->isManual(), 403);

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

        $message = $category->type === 'income' ? 'Income saved.' : 'Expense saved.';

        if ($this->cameFromElsewhere($request)) {
            return back()->with('success', $message);
        }

        return to_route('accounts.show', [
            'bankAccount' => $bankAccount,
            'start_date' => (string) $request->query('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => (string) $request->query('end_date', now()->endOfMonth()->toDateString()),
        ])->with('success', $message);
    }

    private function cameFromElsewhere(Request $request): bool
    {
        return in_array($request->query('from'), ['overview', 'activity', 'account'], true);
    }

    private function ensureAccountIsAccessible(Request $request, BankAccount $bankAccount): void
    {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id,
            403,
        );
    }
}
