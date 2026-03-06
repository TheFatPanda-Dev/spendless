<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $startDate = Carbon::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse((string) $request->query('end_date', now()->endOfMonth()->toDateString()))->endOfDay();

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('provider', 'plaid'))
            ->with('connection')
            ->orderByRaw("COALESCE(display_name, name, official_name, '') asc")
            ->get()
            ->map(function (BankAccount $account): array {
                $balances = is_array($account->balances_encrypted) ? $account->balances_encrypted : [];
                $currency = (string) ($account->currency_code ?: $account->currency ?: 'EUR');

                return [
                    'id' => $account->id,
                    'display_name' => $account->display_name,
                    'name' => $account->name,
                    'official_name' => $account->official_name,
                    'institution_name' => $account->connection?->institution_name,
                    'mask' => $account->mask_encrypted ? str_pad(substr((string) $account->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                    'type' => $account->type,
                    'subtype' => $account->subtype,
                    'currency' => $currency,
                    'current_balance' => (float) ($balances['current'] ?? 0),
                    'available_balance' => isset($balances['available']) ? (float) $balances['available'] : null,
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                ];
            })
            ->values();

        $baseTransactionQuery = BankTransaction::query()
            ->whereNull('removed_at')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('provider', 'plaid'));

        $expenses = (float) (clone $baseTransactionQuery)
            ->where('amount', '>', 0)
            ->sum('amount');

        $income = (float) abs((clone $baseTransactionQuery)
            ->where('amount', '<', 0)
            ->sum('amount'));

        $totalBalance = (float) $accounts->sum('current_balance');
        $periodChange = $income - $expenses;

        return Inertia::render('dashboard', [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_balance' => $totalBalance,
                'period_change' => $periodChange,
                'period_expenses' => -$expenses,
                'period_income' => $income,
            ],
            'accounts' => $accounts,
        ]);
    }
}
