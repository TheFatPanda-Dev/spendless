<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBankAccountDisplayNameRequest;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Services\Banking\BankActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WalletAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $accounts = BankAccount::query()
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('provider', 'plaid'))
            ->with(['connection', 'connection.wallet'])
            ->orderByRaw("COALESCE(display_name, name, official_name, '') asc")
            ->get()
            ->map(fn (BankAccount $account): array => [
                'id' => $account->id,
                'display_name' => $account->display_name,
                'name' => $account->name,
                'official_name' => $account->official_name,
                'institution_name' => $account->connection?->institution_name,
                'wallet_name' => $account->connection?->wallet?->name,
                'mask' => $account->mask_encrypted ? str_pad(substr((string) $account->mask_encrypted, -4), 4, '*', STR_PAD_LEFT) : null,
                'currency' => $account->currency_code ?: $account->currency,
            ])
            ->values();

        return Inertia::render('settings/wallets', [
            'accounts' => $accounts,
        ]);
    }

    public function update(
        UpdateBankAccountDisplayNameRequest $request,
        BankAccount $bankAccount,
    ): RedirectResponse {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id
            && $bankAccount->connection->provider === 'plaid',
            403,
        );

        $bankAccount->update([
            'display_name' => $request->validated('display_name'),
        ]);

        return back()->with('success', 'Account label updated.');
    }

    public function destroy(
        Request $request,
        BankAccount $bankAccount,
        BankActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id
            && $bankAccount->connection->provider === 'plaid',
            403,
        );

        $accountName = $bankAccount->display_name ?: $bankAccount->name;
        $connectionId = $bankAccount->bank_connection_id;

        DB::transaction(function () use ($bankAccount): void {
            $connection = $bankAccount->connection;

            $bankAccount->delete();

            if (! $connection instanceof BankConnection) {
                return;
            }

            if ($connection->accounts()->count() === 0) {
                $connection->delete();
            }
        });

        $activityLogger->log($request->user(), 'account_deleted', [
            'bank_connection_id' => $connectionId,
            'account_name' => $accountName,
            'bank_account_id' => $bankAccount->id,
        ]);

        return back()->with('success', 'Bank account deleted.');
    }
}
