<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssetAccountController;
use App\Http\Controllers\Auth\GithubAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\OAuthRegistrationController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\Banking\EnableBankingConnectionController;
use App\Http\Controllers\Banking\PlaidConnectionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IbkrConnectionController;
use App\Http\Controllers\StatementImportController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TagRuleController;
use App\Http\Controllers\WalletBankConnectionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletSyncStatusController;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()) && AppSetting::registrationOpen(),
    ]);
})->name('home');

Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('wallets/sync-status', WalletSyncStatusController::class)
        ->middleware('throttle:120,1')
        ->name('wallets.sync-status');
    Route::get('wallets/add-account', [EnableBankingConnectionController::class, 'create'])
        ->name('wallets.add-account');
    Route::post('wallets/manual', [WalletController::class, 'storeManual'])
        ->name('wallets.manual.store');
    Route::resource('wallets', WalletController::class)
        ->only(['index', 'show']);
    Route::get('accounts/{bankAccount}', [BankAccountController::class, 'show'])
        ->name('accounts.show');
    Route::post('accounts/{bankAccount}/manual-transactions', [BankAccountController::class, 'storeManualTransaction'])
        ->name('accounts.transactions.manual.store');
    Route::patch('accounts/{bankAccount}/transactions/{bankTransaction}', [BankAccountController::class, 'update'])
        ->name('accounts.transactions.update');

    Route::get('activity', ActivityController::class)->name('activity.index');
    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    Route::post('tags/{tag}/rules', [TagRuleController::class, 'store'])->name('tags.rules.store');
    Route::delete('tags/{tag}/rules/{rule}', [TagRuleController::class, 'destroy'])
        ->scopeBindings()
        ->name('tags.rules.destroy');

    Route::middleware('throttle:banking-sensitive')->group(function (): void {
        Route::post('wallets/refresh-all', [WalletBankConnectionController::class, 'refreshAll'])
            ->name('wallets.refresh-all');
        Route::post('bank-connections/{bankConnection}/refresh', [WalletBankConnectionController::class, 'refreshConnection'])
            ->name('bank-connections.refresh');
        Route::post('assets', [AssetAccountController::class, 'store'])->name('assets.store');
        Route::patch('assets/{bankConnection}', [AssetAccountController::class, 'update'])->name('assets.update');
        Route::post('assets/{bankConnection}/valuations', [AssetAccountController::class, 'storeValuation'])
            ->name('assets.valuations.store');
        Route::delete('assets/{bankConnection}/valuations/{valuation}', [AssetAccountController::class, 'destroyValuation'])
            ->name('assets.valuations.destroy');
        Route::post('investments/ibkr', [IbkrConnectionController::class, 'store'])
            ->name('investments.ibkr.store');
        Route::patch('investments/ibkr/{bankConnection}', [IbkrConnectionController::class, 'update'])
            ->name('investments.ibkr.update');
        Route::post('banking/connect', [EnableBankingConnectionController::class, 'start'])
            ->name('banking.start');
        Route::post('banking/connections/{bankConnection}/reconnect', [EnableBankingConnectionController::class, 'reconnect'])
            ->name('banking.reconnect');
        Route::delete('banking/connections/{bankConnection}', [EnableBankingConnectionController::class, 'destroy'])
            ->name('banking.destroy');
        Route::get('banking/plaid', [PlaidConnectionController::class, 'show'])->name('banking.plaid.show');
        Route::post('banking/plaid/exchange', [PlaidConnectionController::class, 'exchange'])
            ->name('banking.plaid.exchange');
    });

    Route::get('banking/callback', [EnableBankingConnectionController::class, 'callback'])
        ->name('banking.callback');
    Route::get('banking/plaid/oauth', [PlaidConnectionController::class, 'oauth'])->name('banking.plaid.oauth');
    Route::get('banking/plaid/complete', [PlaidConnectionController::class, 'complete'])->name('banking.plaid.complete');

    Route::get('imports/new', [StatementImportController::class, 'create'])->name('imports.create');
    Route::post('imports', [StatementImportController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('imports.store');
    Route::prefix('imports/{token}')->where(['token' => '[A-Za-z0-9]{40}'])->group(function (): void {
        Route::get('/', [StatementImportController::class, 'show'])->name('imports.show');
        Route::patch('mapping', [StatementImportController::class, 'updateMapping'])->name('imports.mapping');
        Route::post('confirm', [StatementImportController::class, 'confirm'])->name('imports.confirm');
        Route::delete('/', [StatementImportController::class, 'destroy'])->name('imports.destroy');
    });
});

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::get('auth/github/redirect', [GithubAuthController::class, 'redirect'])->name('github.redirect');
Route::get('auth/github/callback', [GithubAuthController::class, 'callback'])->name('github.callback');
Route::post('auth/oauth/register', [OAuthRegistrationController::class, 'register'])->name('oauth.register');
Route::post('auth/oauth/cancel', [OAuthRegistrationController::class, 'cancel'])->name('oauth.cancel');

require __DIR__.'/settings.php';
