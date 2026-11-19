<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Support\Categories\CategoryOptions;
use App\Support\Transactions\TransactionPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every transaction across all wallets for a month, filterable, editable in place.
 */
class ActivityController extends Controller
{
    private const LIMIT = 500;

    public function __invoke(Request $request, ResolveBankTransactionCategory $resolveCategory): Response
    {
        $user = $request->user();
        [$from, $to] = DashboardController::month($request);
        $presenter = new TransactionPresenter($user, $resolveCategory);
        $type = in_array($request->query('type'), ['expense', 'income'], true) ? (string) $request->query('type') : 'all';
        $search = trim((string) $request->query('q', ''));

        $accounts = BankAccount::query()
            ->whereIn('bank_connection_id', $user->bankConnections()
                ->where('status', '!=', BankConnection::STATUS_PENDING)
                ->select('id'))
            ->with('connection:id,provider')
            ->orderByRaw("COALESCE(display_name, name, '') asc")
            ->get()
            ->keyBy('id');

        $accountId = $accounts->has($request->integer('account')) ? $request->integer('account') : null;
        $category = $request->filled('category') ? $presenter->categories()->get($request->integer('category')) : null;
        $tagId = $request->filled('tag') ? $user->tags()->whereKey($request->integer('tag'))->value('id') : null;

        $query = BankTransaction::query()
            ->whereIn('bank_account_id', $accountId !== null ? [$accountId] : $accounts->keys()->all())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($type === 'expense', fn ($query) => $query->where('amount', '<', 0))
            ->when($type === 'income', fn ($query) => $query->where('amount', '>', 0))
            ->when($category instanceof Category, fn ($query) => $query->whereIn('category_id', $this->withDescendants($category, $presenter)))
            ->when($tagId !== null, fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($tagId)))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('merchant_name', 'like', '%'.$search.'%')
                ->orWhere('payee', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')));

        $totals = (clone $query)
            ->selectRaw('currency, sum(case when amount < 0 then amount else 0 end) as out_total, sum(case when amount > 0 then amount else 0 end) as in_total, count(*) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row): array => [
                'currency' => strtoupper((string) ($row->currency ?: 'EUR')),
                'out' => round((float) $row->out_total, 2),
                'in' => round((float) $row->in_total, 2),
                'count' => (int) $row->total,
            ])
            ->values();

        $transactions = $query
            ->with('tags:id')
            ->latest('date')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (BankTransaction $transaction): array => $presenter->present($transaction, $accounts->get($transaction->bank_account_id)))
            ->values();

        return Inertia::render('activity/index', [
            'month' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'filters' => [
                'type' => $type,
                'account' => $accountId,
                'category' => $category?->id,
                'tag' => $tagId,
                'q' => $search,
            ],
            'transactions' => $transactions,
            'truncated' => $totals->sum('count') > self::LIMIT,
            'totals' => $totals,
            'accounts' => $accounts->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'name' => (string) ($account->display_name ?: $account->name),
                'currency' => strtoupper((string) ($account->currency ?: 'EUR')),
            ])->values(),
            'categoryOptions' => $presenter->categoryOptions(),
            'categoryColors' => CategoryOptions::colors(),
            'tags' => $user->tags()->orderBy('name')->get(['id', 'name', 'color']),
            'addTransaction' => [
                'accounts' => $presenter->manualAccounts(),
                'categories' => $presenter->categoryOptions(),
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function withDescendants(Category $category, TransactionPresenter $presenter): array
    {
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $children = $presenter->categories()
                ->filter(fn (Category $candidate): bool => in_array($candidate->parent_id, $frontier, true))
                ->keys()
                ->map(fn ($id): int => (int) $id)
                ->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
