<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBankAccountDisplayNameRequest;
use App\Http\Requests\Settings\UpdateBaseCurrencyPreferenceRequest;
use App\Http\Requests\Settings\UpdateNumberLocalePreferenceRequest;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Services\Banking\BankActivityLogger;
use App\Support\Currency\BaseCurrencyOptions;
use App\Support\Localization\NumberLocaleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WalletAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $accounts = BankAccount::query()
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $user->id))
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

        $availableBaseCurrencies = collect(BaseCurrencyOptions::all(
            $accounts
                ->pluck('currency')
                ->filter()
                ->map(fn (mixed $currency): string => strtoupper((string) $currency))
                ->all(),
        ));

        $selectedBaseCurrency = strtoupper((string) ($user->preferred_base_currency ?: 'EUR'));

        if (! $availableBaseCurrencies->contains($selectedBaseCurrency)) {
            $selectedBaseCurrency = 'EUR';
        }

        return Inertia::render('settings/wallets', [
            'accounts' => $accounts,
            'base_currency' => [
                'selected' => $selectedBaseCurrency,
                'options' => BaseCurrencyOptions::options($availableBaseCurrencies->all()),
            ],
            'number_locale' => [
                'selected' => NumberLocaleOptions::normalize((string) ($user->preferred_number_locale ?: 'en-GB')),
                'options' => NumberLocaleOptions::options(),
            ],
        ]);
    }

    public function updateBaseCurrency(UpdateBaseCurrencyPreferenceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'preferred_base_currency' => $request->validated('base_currency'),
        ]);

        return back()->with('success', 'Base currency updated.');
    }

    public function updateNumberLocale(UpdateNumberLocalePreferenceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'preferred_number_locale' => $request->validated('number_locale'),
        ]);

        return back()->with('success', 'Number localization updated.');
    }

    public function update(
        UpdateBankAccountDisplayNameRequest $request,
        BankAccount $bankAccount,
    ): RedirectResponse {
        abort_unless(
            $bankAccount->connection !== null
            && $bankAccount->connection->user_id === $request->user()->id,
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
            && $bankAccount->connection->user_id === $request->user()->id,
            403,
        );

        $accountName = $bankAccount->display_name ?: $bankAccount->name;
        $connectionId = $bankAccount->bank_connection_id;

        DB::transaction(function () use ($bankAccount): void {
            $connection = $bankAccount->connection;
            $wallet = $connection?->wallet;

            $bankAccount->delete();

            if (! $connection instanceof BankConnection) {
                return;
            }

            if ($connection->accounts()->count() === 0) {
                $connection->delete();

                if ($wallet !== null && $wallet->bankConnections()->count() === 0) {
                    $wallet->delete();
                }
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
