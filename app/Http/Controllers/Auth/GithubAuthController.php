<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;

class GithubAuthController extends Controller
{
    /**
     * Redirect the user to GitHub's OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        $callbackUrl = url('/auth/github/callback');

        $driver = app('Laravel\\Socialite\\Contracts\\Factory')
            ->driver('github')
            ->scopes(['read:user', 'user:email']);

        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        return $driver->redirect();
    }

    /**
     * Handle GitHub callback and authenticate the user.
     */
    public function callback(): RedirectResponse
    {
        $callbackUrl = url('/auth/github/callback');

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('github');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $githubUser = $driver->user();
        } catch (InvalidStateException) {
            return to_route('login')->withErrors([
                'email' => 'GitHub sign-in expired or host changed. Please try again from the same browser tab.',
            ]);
        }

        $intent = session()->pull('oauth_link_intent');

        if ($intent === 'github' && Auth::check()) {
            $authenticatedUser = Auth::user();

            if (! $authenticatedUser instanceof User) {
                return to_route('login');
            }

            $githubEmail = $this->resolveVerifiedGithubEmail($githubUser->token, (string) $githubUser->getEmail());

            if ($githubEmail === null) {
                return redirect()->to(route('profile.edit').'#oauth-authentication')
                    ->with('error', 'Your GitHub account needs a primary verified email to be linked.');
            }

            $githubId = (string) $githubUser->getId();

            $alreadyLinkedToAnotherUser = User::query()
                ->where('github_id', $githubId)
                ->whereKeyNot($authenticatedUser->id)
                ->exists();

            if ($alreadyLinkedToAnotherUser) {
                return redirect()->to(route('profile.edit').'#oauth-authentication')
                    ->with('error', 'This GitHub account is already linked to another SpendLess profile.');
            }

            $authenticatedUser->github_id = $githubId;
            $authenticatedUser->github_avatar = $githubUser->getAvatar();
            $authenticatedUser->save();

            return redirect()->to(route('profile.edit').'#oauth-authentication')
                ->with('success', 'GitHub account linked successfully.');
        }

        $githubEmail = $this->resolveVerifiedGithubEmail($githubUser->token, (string) $githubUser->getEmail());

        if ($githubEmail === null) {
            return to_route('login')->withErrors([
                'email' => 'Your GitHub account needs a primary verified email to sign in.',
            ]);
        }

        $user = User::query()
            ->where('github_id', (string) $githubUser->getId())
            ->orWhere('email', $githubEmail)
            ->first();
        $created = false;

        if ($user) {
            if (! $user->github_id) {
                $user->github_id = (string) $githubUser->getId();
            }

            $user->github_avatar = $githubUser->getAvatar();
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            $user = User::create([
                'name' => $githubUser->getName() ?: 'GitHub User',
                'email' => $githubEmail,
                'github_id' => (string) $githubUser->getId(),
                'github_avatar' => $githubUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
                'password_set_at' => null,
            ]);
            $created = true;
        }

        Auth::login($user, remember: true);

        $successMessage = $created ? 'Registration successful' : 'Login successful';

        return to_route('dashboard')->with('success', $successMessage);
    }

    /**
     * Redirect the authenticated user to GitHub OAuth for account linking.
     */
    public function linkRedirect(): RedirectResponse
    {
        session()->put('oauth_link_intent', 'github');

        $callbackUrl = url('/auth/github/callback');

        $driver = app('Laravel\\Socialite\\Contracts\\Factory')
            ->driver('github')
            ->scopes(['read:user', 'user:email']);

        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        return $driver->redirect();
    }

    /**
     * Handle GitHub callback and link account to the authenticated user.
     */
    public function linkCallback(): RedirectResponse
    {
        $callbackUrl = url('/auth/github/callback');

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('github');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $githubUser = $driver->user();
        } catch (InvalidStateException) {
            return to_route('profile.edit')->with('error', 'GitHub link expired or host changed. Please try again from the same browser tab.');
        }

        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            return to_route('login');
        }

        $githubEmail = $this->resolveVerifiedGithubEmail($githubUser->token, (string) $githubUser->getEmail());

        if ($githubEmail === null) {
            return to_route('profile.edit')->with('error', 'Your GitHub account needs a primary verified email to be linked.');
        }

        $githubId = (string) $githubUser->getId();

        $alreadyLinkedToAnotherUser = User::query()
            ->where('github_id', $githubId)
            ->whereKeyNot($authenticatedUser->id)
            ->exists();

        if ($alreadyLinkedToAnotherUser) {
            return to_route('profile.edit')->with('error', 'This GitHub account is already linked to another SpendLess profile.');
        }

        $authenticatedUser->github_id = $githubId;
        $authenticatedUser->github_avatar = $githubUser->getAvatar();
        $authenticatedUser->save();

        return to_route('profile.edit')->with('success', 'GitHub account linked successfully.');
    }

    /**
     * Resolve a verified GitHub email.
     */
    private function resolveVerifiedGithubEmail(string $token, string $fallbackEmail): ?string
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.github.com/user/emails');

        if ($response->ok()) {
            $emails = $response->json();

            if (is_array($emails)) {
                $primaryVerified = collect($emails)->first(function ($email): bool {
                    return is_array($email)
                        && isset($email['email'])
                        && ! empty($email['verified'])
                        && ! empty($email['primary']);
                });

                if (is_array($primaryVerified) && isset($primaryVerified['email'])) {
                    return (string) $primaryVerified['email'];
                }

                $anyVerified = collect($emails)->first(function ($email): bool {
                    return is_array($email)
                        && isset($email['email'])
                        && ! empty($email['verified']);
                });

                if (is_array($anyVerified) && isset($anyVerified['email'])) {
                    return (string) $anyVerified['email'];
                }
            }
        }

        if ($fallbackEmail !== '') {
            return $fallbackEmail;
        }

        return null;
    }
}
