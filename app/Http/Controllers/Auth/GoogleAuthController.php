<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        $callbackUrl = url('/auth/google/callback');

        $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        return $driver->redirect();
    }

    /**
     * Handle Google callback and authenticate the user.
     */
    public function callback(): RedirectResponse
    {
        $callbackUrl = url('/auth/google/callback');

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $googleUser = $driver->user();
        } catch (InvalidStateException) {
            return to_route('login')->withErrors([
                'email' => 'Google sign-in expired or host changed. Please try again from the same browser tab.',
            ]);
        }

        $googleEmail = (string) $googleUser->getEmail();
        $googleEmailVerified = true;

        if (property_exists($googleUser, 'user')) {
            $googleEmailVerified = (bool) Arr::get($googleUser->user, 'email_verified', false);
        }

        if ($googleEmail === '' || ! $googleEmailVerified) {
            return to_route('login')->withErrors([
                'email' => 'Your Google account email must be verified before signing in.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleEmail)
            ->first();
        $created = false;

        if ($user) {
            if (! $user->google_id) {
                $user->google_id = $googleUser->getId();
            }

            $user->google_avatar = $googleUser->getAvatar();
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google User',
                'nickname' => $this->generateUniqueNickname($googleEmail),
                'email' => $googleEmail,
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
            $created = true;
        }

        Auth::login($user, remember: true);

        $successMessage = $created ? 'Registration successful' : 'Login successful';

        return to_route('dashboard')->with('success', $successMessage);
    }

    /**
     * Generate a unique nickname for social registrations.
     */
    private function generateUniqueNickname(string $email): string
    {
        $emailLocalPart = Str::before($email, '@');
        $base = Str::lower((string) Str::of($emailLocalPart)->replaceMatches('/[^A-Za-z0-9_]/', ''));

        if ($base === '') {
            $base = 'user';
        }

        $nickname = $base;
        $suffix = 1;

        while (User::query()->where('nickname', $nickname)->exists()) {
            $nickname = $base.$suffix;
            $suffix++;
        }

        return $nickname;
    }
}
