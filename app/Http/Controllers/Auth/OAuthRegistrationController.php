<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OAuthRegistrationController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $candidate = $request->session()->pull('oauth_registration_candidate');

        if (! is_array($candidate)) {
            return to_route('login')->with('error', 'OAuth registration session expired. Please try signing in again.');
        }

        $provider = (string) ($candidate['provider'] ?? '');
        $providerLabel = (string) ($candidate['provider_label'] ?? 'OAuth');
        $providerId = (string) ($candidate['provider_id'] ?? '');
        $email = (string) ($candidate['email'] ?? '');
        $name = (string) ($candidate['name'] ?? '');
        $avatar = (string) ($candidate['avatar'] ?? '');

        if (! in_array($provider, ['google', 'github'], true) || $providerId === '' || $email === '') {
            return to_route('login')->with('error', 'Invalid OAuth registration payload. Please try again.');
        }

        $providerIdColumn = $provider.'_id';
        $providerAvatarColumn = $provider.'_avatar';

        $user = User::query()
            ->where('email', $email)
            ->orWhere($providerIdColumn, $providerId)
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $name !== '' ? $name : Str::title($providerLabel.' User'),
                'email' => $email,
                $providerIdColumn => $providerId,
                $providerAvatarColumn => $avatar !== '' ? $avatar : null,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
                'password_set_at' => null,
            ]);
        } else {
            $user->{$providerIdColumn} ??= $providerId;

            if ($avatar !== '') {
                $user->{$providerAvatarColumn} = $avatar;
            }

            $user->email_verified_at ??= now();
            $user->save();
        }

        Auth::login($user, remember: true);

        return to_route('profile.edit')->with('success', 'Account created with '.$providerLabel.'. Please review your profile settings.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('oauth_registration_candidate');

        return to_route('login');
    }
}
