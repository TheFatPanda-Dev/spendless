<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\ResolveBankTransactionCategory;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Tag;
use App\Models\TagRule;
use App\Models\User;
use App\Support\Categories\CategoryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tags (which car, which flat…) with what each one cost or earned in a period.
 */
class TagController extends Controller
{
    public const PERIODS = ['month', 'year', '12m', 'all'];

    private const RECENT_PER_TAG = 30;

    public function index(Request $request, ResolveBankTransactionCategory $resolveCategory): Response
    {
        $user = $request->user();
        $period = in_array($request->query('period'), self::PERIODS, true) ? (string) $request->query('period') : 'year';
        [$from, $to] = $this->periodRange($period);

        $accounts = $this->accounts($user);
        $tags = $user->tags()->with('rules')->orderBy('name')->get();
        $categories = $user->categories()->get(['id', 'parent_id', 'name', 'type', 'icon', 'color'])->keyBy('id');

        $transactions = $tags->isEmpty() ? collect() : BankTransaction::query()
            ->whereIn('bank_account_id', $accounts->modelKeys())
            ->whereIn('id', fn ($query) => $query->select('bank_transaction_id')->from('bank_transaction_tag')->whereIn('tag_id', $tags->modelKeys()))
            ->when($from !== null, fn ($query) => $query->where('date', '>=', $from->toDateString()))
            ->where('date', '<=', $to->toDateString())
            ->with(['tags:id', 'assignedCategory'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $rows = $transactions->map(function (BankTransaction $transaction) use ($resolveCategory, $user, $categories, $accounts): array {
            $category = $transaction->assignedCategory ?? $resolveCategory($transaction, $user);
            $account = $accounts->get($transaction->bank_account_id);

            return [
                'id' => $transaction->id,
                'tag_ids' => $transaction->tags->modelKeys(),
                'date' => $transaction->date?->toDateString(),
                'label' => $transaction->merchant_name ?: ($transaction->payee ?: ($transaction->name ?: 'Transaction')),
                'account_id' => $transaction->bank_account_id,
                'account' => $account?->display_name ?: $account?->name,
                'amount' => (float) $transaction->amount,
                'currency' => strtoupper((string) ($transaction->currency ?: 'EUR')),
                'category' => $category instanceof Category ? $this->categoryPath($category, $categories) : null,
                'category_icon' => $category?->icon ?? 'package',
                'category_color' => $category?->color ?? 'slate',
                'pending' => $transaction->pending,
            ];
        });

        return Inertia::render('tags/index', [
            'period' => $period,
            'range' => ['from' => $from?->toDateString(), 'to' => $to->toDateString()],
            'tags' => $tags->map(fn (Tag $tag): array => $this->summarise($tag, $rows, $accounts))->values(),
            'accounts' => $accounts->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'name' => $this->accountLabel($account),
            ])->values(),
            'colors' => CategoryOptions::colors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTag($request);

        $request->user()->tags()->create($validated);

        return back()->with('success', sprintf('Tag "%s" created.', $validated['name']));
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorizeTag($request, $tag);

        $tag->update($this->validateTag($request, $tag));

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorizeTag($request, $tag);

        $tag->delete();

        return back()->with('success', sprintf('Tag "%s" deleted.', $tag->name));
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validateTag(Request $request, ?Tag $ignore = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', Rule::in(array_column(CategoryOptions::colors(), 'value'))],
        ]);
        $name = trim(preg_replace('/\s+/u', ' ', $validated['name']) ?? '');

        $taken = $request->user()->tags()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($name === '' || $taken) {
            throw ValidationException::withMessages([
                'name' => $name === '' ? 'Give the tag a name.' : 'You already have a tag with this name.',
            ]);
        }

        return ['name' => $name, 'color' => $validated['color']];
    }

    private function authorizeTag(Request $request, Tag $tag): void
    {
        abort_unless($tag->user_id === $request->user()->id, 404);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, BankAccount>  $accounts
     * @return array<string, mixed>
     */
    private function summarise(Tag $tag, Collection $rows, Collection $accounts): array
    {
        $tagged = $rows->filter(fn (array $row): bool => in_array($tag->id, $row['tag_ids'], true))->values();

        $totals = $tagged->groupBy('currency')->map(fn (Collection $items, string $currency): array => [
            'currency' => $currency,
            'spent' => round(-$items->where('amount', '<', 0)->sum('amount'), 2),
            'received' => round($items->where('amount', '>', 0)->sum('amount'), 2),
            'count' => $items->count(),
        ])->sortByDesc(fn (array $total): float => $total['spent'] + $total['received'])->values();

        $byCategory = $tagged->where('amount', '<', 0)
            ->groupBy(fn (array $row): string => ($row['category'] ?? 'Uncategorised').'|'.$row['currency'])
            ->map(fn (Collection $items): array => [
                'category' => $items->first()['category'] ?? 'Uncategorised',
                'icon' => $items->first()['category_icon'],
                'color' => $items->first()['category_color'],
                'currency' => $items->first()['currency'],
                'spent' => round(-$items->sum('amount'), 2),
            ])
            ->sortByDesc('spent')
            ->values();

        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
            'count' => $tagged->count(),
            'totals' => $totals,
            'by_category' => $byCategory,
            'transactions' => $tagged->take(self::RECENT_PER_TAG)
                ->map(fn (array $row): array => collect($row)->except('tag_ids')->all())
                ->values(),
            'rules' => $tag->rules->map(fn (TagRule $rule): array => [
                'id' => $rule->id,
                'account_id' => $rule->bank_account_id,
                'account' => $rule->bank_account_id !== null && $accounts->has($rule->bank_account_id)
                    ? $this->accountLabel($accounts->get($rule->bank_account_id))
                    : null,
                'contains' => $rule->contains,
            ])->values(),
        ];
    }

    /**
     * @return array{CarbonImmutable|null, CarbonImmutable}
     */
    private function periodRange(string $period): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'month' => [$today->startOfMonth(), $today->endOfMonth()],
            '12m' => [$today->subMonths(12)->addDay(), $today],
            'all' => [null, $today->addYears(5)],
            default => [$today->startOfYear(), $today->endOfYear()],
        };
    }

    /**
     * @return Collection<int, BankAccount>
     */
    private function accounts(User $user): Collection
    {
        return BankAccount::query()
            ->whereIn('bank_connection_id', $user->bankConnections()->select('id'))
            ->with('connection:id,institution_name')
            ->orderByRaw("COALESCE(display_name, name, '') asc")
            ->get()
            ->keyBy('id');
    }

    private function accountLabel(BankAccount $account): string
    {
        $name = $account->display_name ?: ($account->name ?: 'Account');
        $institution = $account->connection?->institution_name;
        $mask = $account->maskForDisplay();

        return trim($name.($institution && ! str_contains($name, $institution) ? ' · '.$institution : '').($mask ? ' ••'.$mask : ''));
    }

    /**
     * @param  Collection<int|string, Category>  $categories
     */
    private function categoryPath(Category $category, Collection $categories): string
    {
        $segments = [];
        $current = $category;

        while ($current instanceof Category && count($segments) < 6) {
            array_unshift($segments, $current->name);
            $current = $current->parent_id !== null ? $categories->get($current->parent_id) : null;
        }

        return implode(' / ', $segments);
    }
}
