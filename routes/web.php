<?php

use App\Http\Controllers\Auth\GithubAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\OAuthRegistrationController;
use App\Http\Controllers\BankConnectionsController;
use App\Http\Controllers\Banking\EnableBankingConnectionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::inertia('/privacy', 'privacy')->name('privacy');
Route::inertia('/terms', 'terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('bank-connections', BankConnectionsController::class)->name('bank-connections');

    Route::get('banking/institutions', [EnableBankingConnectionController::class, 'institutions'])
        ->name('banking.institutions');
    Route::post('banking/connect', [EnableBankingConnectionController::class, 'start'])
        ->name('banking.start');
    Route::delete('banking/connections/{bankConnection}', [EnableBankingConnectionController::class, 'destroy'])
        ->name('banking.destroy');
    Route::get('banking/callback', [EnableBankingConnectionController::class, 'callback'])
        ->name('banking.callback');
});

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
Route::get('auth/github/redirect', [GithubAuthController::class, 'redirect'])->name('github.redirect');
Route::get('auth/github/callback', [GithubAuthController::class, 'callback'])->name('github.callback');
Route::post('auth/oauth/register', [OAuthRegistrationController::class, 'register'])->name('oauth.register');
Route::post('auth/oauth/cancel', [OAuthRegistrationController::class, 'cancel'])->name('oauth.cancel');

require __DIR__.'/settings.php';
