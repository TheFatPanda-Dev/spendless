<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        return app('Laravel\\Socialite\\Contracts\\Factory')
            ->driver('google')
            ->redirect();
    }

    /**
     * Handle Google callback and authenticate the user.
     */
    public function callback(): RedirectResponse
    {
        $googleUser = app('Laravel\\Socialite\\Contracts\\Factory')
            ->driver('google')
            ->user();

        $googleEmail = (string) $googleUser->getEmail();

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleEmail)
            ->first();

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
        }

        Auth::login($user, remember: true);

        return to_route('dashboard')->with('success', 'Registration successful');
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
