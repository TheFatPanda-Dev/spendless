<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Services\Reports\MonthlySpending;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A monthly total budget plus optional budgets per main expense category, all in the
 * user's base currency and repeating every month.
 */
class BudgetController extends Controller
{
    public function index(Request $request, MonthlySpending $report): Response
    {
        $user = $request->user();
        [$from, $to] = DashboardController::month($request);
        $summary = $report->summarize($user, $from, $to);
        $budgets = $report->budgets($user, $summary);
        $budgeted = collect($budgets['categories'])->pluck('category_id')->all();

        $mainExpense = $user->categories()
            ->whereNull('parent_id')
            ->where('type', 'expense')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        return Inertia::render('budgets/index', [
            'month' => ['start' => $from->toDateString(), 'end' => $to->toDateString()],
            'baseCurrency' => $summary['base_currency'],
            'spent' => $summary['spent'],
            'total' => $budgets['total'],
            'categories' => $budgets['categories'],
            'unbudgeted' => collect($summary['by_category'])
                ->reject(fn (array $row): bool => $row['id'] === null || in_array($row['id'], $budgeted, true))
                ->values(),
            'options' => $mainExpense
                ->reject(fn (Category $category): bool => in_array($category->id, $budgeted, true))
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'icon' => $category->icon])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->whereNull('parent_id')
                    ->where('type', 'expense')),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $budget = Budget::query()
            ->where('user_id', $request->user()->id)
            ->when($categoryId === null, fn ($query) => $query->whereNull('category_id'), fn ($query) => $query->where('category_id', $categoryId))
            ->first() ?? new Budget(['user_id' => $request->user()->id, 'category_id' => $categoryId]);

        $budget->amount = round((float) $validated['amount'], 2);
        $budget->save();

        return back()->with('success', $categoryId === null ? 'Monthly budget saved.' : 'Category budget saved.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        $budget->delete();

        return back()->with('success', 'Budget removed.');
    }
}
