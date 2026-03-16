<?php

use App\Http\Controllers\Auth\GithubAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\OAuthRegistrationController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\Banking\EnableBankingConnectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlaidWebhookController;
use App\Http\Controllers\WalletBankConnectionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletSyncStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('wallets/sync-status', WalletSyncStatusController::class)
        ->middleware('throttle:banking-sensitive')
        ->name('wallets.sync-status');
    Route::get('wallets/add-account', [WalletController::class, 'addAccount'])
        ->name('wallets.add-account');
    Route::post('wallets/manual', [WalletController::class, 'storeManual'])
        ->name('wallets.manual.store');
    Route::resource('wallets', WalletController::class)
        ->only(['index', 'store', 'show']);
    Route::get('accounts/{bankAccount}', [BankAccountController::class, 'show'])
        ->name('accounts.show');
    Route::post('accounts/{bankAccount}/manual-transactions', [BankAccountController::class, 'storeManualTransaction'])
        ->name('accounts.transactions.manual.store');
    Route::patch('accounts/{bankAccount}/transactions/{bankTransaction}', [BankAccountController::class, 'update'])
        ->name('accounts.transactions.update');

    Route::middleware('throttle:banking-sensitive')->group(function (): void {
        Route::post('wallets/quick-connect', [WalletController::class, 'quickConnect'])
            ->name('wallets.quick-connect');
        Route::post('wallets/{wallet}/bank-connections/plaid/link-token', [WalletBankConnectionController::class, 'createLinkToken'])
            ->name('wallets.bank-connections.plaid.link-token');
        Route::post('wallets/{wallet}/bank-connections/plaid/exchange', [WalletBankConnectionController::class, 'exchangePublicToken'])
            ->name('wallets.bank-connections.plaid.exchange');
        Route::post('wallets/refresh-all', [WalletBankConnectionController::class, 'refreshAll'])
            ->name('wallets.refresh-all');
        Route::post('bank-connections/{bankConnection}/refresh', [WalletBankConnectionController::class, 'refreshConnection'])
            ->name('bank-connections.refresh');
    });

    Route::get('banking/institutions', [EnableBankingConnectionController::class, 'institutions'])
        ->name('banking.institutions');
    Route::post('banking/connect', [EnableBankingConnectionController::class, 'start'])
        ->name('banking.start');
    Route::delete('banking/connections/{bankConnection}', [EnableBankingConnectionController::class, 'destroy'])
        ->name('banking.destroy');
    Route::get('banking/callback', [EnableBankingConnectionController::class, 'callback'])
        ->name('banking.callback');
});

Route::post('plaid/webhook', PlaidWebhookController::class)
    ->middleware('throttle:plaid-webhooks')
    ->name('plaid.webhook');

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::get('auth/github/redirect', [GithubAuthController::class, 'redirect'])->name('github.redirect');
Route::get('auth/github/callback', [GithubAuthController::class, 'callback'])->name('github.callback');
Route::post('auth/oauth/register', [OAuthRegistrationController::class, 'register'])->name('oauth.register');
Route::post('auth/oauth/cancel', [OAuthRegistrationController::class, 'cancel'])->name('oauth.cancel');

require __DIR__.'/settings.php';
