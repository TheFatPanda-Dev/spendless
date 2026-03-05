<?php

use App\Http\Controllers\Auth\GithubAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('settings/security', [ProfileController::class, 'security'])->name('security.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/oauth/google/redirect', [GoogleAuthController::class, 'linkRedirect'])
        ->name('settings.google.redirect');
    Route::get('settings/oauth/google/callback', [GoogleAuthController::class, 'linkCallback'])
        ->name('settings.google.callback');
    Route::delete('settings/oauth/google', [ProfileController::class, 'unlinkGoogle'])
        ->name('settings.google.unlink');

    Route::get('settings/oauth/github/redirect', [GithubAuthController::class, 'linkRedirect'])
        ->name('settings.github.redirect');
    Route::get('settings/oauth/github/callback', [GithubAuthController::class, 'linkCallback'])
        ->name('settings.github.callback');
    Route::delete('settings/oauth/github', [ProfileController::class, 'unlinkGithub'])
        ->name('settings.github.unlink');
});

Route::get('settings/profile/email/confirm/{user}', [ProfileController::class, 'confirmEmailChange'])
    ->middleware('signed:relative')
    ->name('profile.email.confirm');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::redirect('settings/two-factor', '/settings/security')
        ->name('two-factor.show');
});
