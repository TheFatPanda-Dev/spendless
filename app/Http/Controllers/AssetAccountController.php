<?php

namespace App\Http\Controllers;

use App\Models\AssetValuation;
use App\Models\BankConnection;
use App\Models\Wallet;
use App\Services\Assets\AssetAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cash ISA (fixed term, profit paid at the end) and pension (value updated by hand).
 */
class AssetAccountController extends Controller
{
    public function store(Request $request, AssetAccounts $assets): RedirectResponse
    {
        Gate::authorize('create', Wallet::class);

        $kind = $request->input('kind');

        $validated = $request->validate([
            'kind' => ['required', Rule::in([AssetAccounts::KIND_CASH_ISA, AssetAccounts::KIND_PENSION])],
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            ...($kind === AssetAccounts::KIND_CASH_ISA ? $this->isaRules() : $this->valuationRules()),
        ]);

        $account = $assets->create($request->user(), [...$validated, 'currency' => strtoupper($validated['currency'])]);

        return to_route('wallets.show', $account->connection->wallet_id)
            ->with('success', sprintf('%s added.', $account->name));
    }

    public function update(Request $request, BankConnection $bankConnection, AssetAccounts $assets): RedirectResponse
    {
        $account = $this->assetAccount($bankConnection, AssetAccounts::KIND_CASH_ISA);

        $assets->updateIsaTerms($account, $request->validate($this->isaRules()));

        return back()->with('success', 'Cash ISA updated.');
    }

    public function storeValuation(Request $request, BankConnection $bankConnection, AssetAccounts $assets): RedirectResponse
    {
        $account = $this->assetAccount($bankConnection, AssetAccounts::KIND_PENSION);
        $validated = $request->validate($this->valuationRules());

        $assets->recordValuation($account, (float) $validated['value'], $validated['date']);

        return back()->with('success', 'Value updated.');
    }

    public function destroyValuation(BankConnection $bankConnection, AssetValuation $valuation, AssetAccounts $assets): RedirectResponse
    {
        $account = $this->assetAccount($bankConnection, AssetAccounts::KIND_PENSION);
        abort_unless($valuation->bank_account_id === $account->id, Response::HTTP_NOT_FOUND);

        $valuation->delete();
        $assets->syncBalanceFromValuations($account);

        return back()->with('success', 'Value removed.');
    }

    private function assetAccount(BankConnection $connection, string $kind): \App\Models\BankAccount
    {
        Gate::authorize('update', $connection);
        abort_unless($connection->provider === BankConnection::PROVIDER_ASSET, Response::HTTP_NOT_FOUND);

        $account = $connection->accounts()->firstOrFail();
        abort_unless($account->subtype === $kind, Response::HTTP_NOT_FOUND);
        $account->setRelation('connection', $connection);

        return $account;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function isaRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['required', 'date'],
            'years' => ['required', 'numeric', 'gt:0', 'max:50'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function valuationRules(): array
    {
        return [
            'value' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
