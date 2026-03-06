<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function show(Request $request, BankAccount $bankAccount): Response
    {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id
            && $bankAccount->connection->provider === 'plaid',
            403,
        );

        $startDate = Carbon::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse((string) $request->query('end_date', now()->endOfMonth()->toDateString()))->endOfDay();

        $bankAccount->load([
            'connection',
            'transactions' => fn ($query) => $query
                ->whereNull('removed_at')
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->latest('date')
                ->limit(200),
        ]);

        $transactions = $bankAccount->transactions
            ->map(fn ($transaction): array => [
                'id' => $transaction->id,
                'name' => $transaction->name,
                'merchant_name' => $transaction->merchant_name,
                'counterparty' => $transaction->payee,
                'category' => is_array($transaction->category) ? ($transaction->category[0] ?? 'Other') : 'Other',
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->iso_currency_code ?: $transaction->currency,
                'date' => $transaction->date?->toDateString(),
                'pending' => $transaction->pending,
            ])
            ->values();

        return Inertia::render('accounts/show', [
            'account' => [
                'id' => $bankAccount->id,
                'display_name' => $bankAccount->display_name,
                'name' => $bankAccount->name,
                'official_name' => $bankAccount->official_name,
                'institution_name' => $bankAccount->connection?->institution_name,
                'mask' => $bankAccount->mask_encrypted ? str_pad(substr((string) $bankAccount->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                'currency' => $bankAccount->currency_code ?: $bankAccount->currency,
                'balances' => $bankAccount->balances_encrypted,
                'last_synced_at' => $bankAccount->last_synced_at?->toIso8601String(),
            ],
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'transactions' => $transactions,
        ]);
    }
}
