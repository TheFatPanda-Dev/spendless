<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateUserFromOAuthCandidate;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Laravel\Socialite\Two\InvalidStateException;

class GithubAuthController extends Controller
{
    public function __construct(private CreateUserFromOAuthCandidate $createUserFromOAuthCandidate) {}

    /**
     * Redirect the user to GitHub's OAuth page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->boolean('popup')) {
            session()->put('oauth_popup_github', true);
        }

        if ($request->boolean('register')) {
            session()->put('oauth_register_intent_github', true);
        }

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
    public function callback(Request $request): RedirectResponse|View
    {
        $usePopup = $request->boolean('popup') || (bool) $request->session()->pull('oauth_popup_github', false);
        $registerIntent = (bool) $request->session()->pull('oauth_register_intent_github', false);
        $callbackUrl = url('/auth/github/callback');

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('github');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $githubUser = $driver->user();
        } catch (InvalidStateException) {
            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'error',
                    'message' => 'GitHub sign-in expired or host changed. Please try again.',
                ]);
            }

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
                return redirect()->to(route('security.edit').'#oauth')
                    ->with('error', 'Your GitHub account needs a primary verified email to be linked.');
            }

            $githubId = (string) $githubUser->getId();

            $alreadyLinkedToAnotherUser = User::query()
                ->where('github_id', $githubId)
                ->whereKeyNot($authenticatedUser->id)
                ->exists();

            if ($alreadyLinkedToAnotherUser) {
                return redirect()->to(route('security.edit').'#oauth')
                    ->with('error', 'This GitHub account is already linked to another SpendLess profile.');
            }

            $authenticatedUser->github_id = $githubId;
            $authenticatedUser->github_avatar = $githubUser->getAvatar();
            $authenticatedUser->save();

            return redirect()->to(route('security.edit').'#oauth')
                ->with('success', 'GitHub account linked successfully.');
        }

        $githubEmail = $this->resolveVerifiedGithubEmail($githubUser->token, (string) $githubUser->getEmail());

        if ($githubEmail === null) {
            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'error',
                    'message' => 'Your GitHub account needs a primary verified email to sign in.',
                ]);
            }

            return to_route('login')->withErrors([
                'email' => 'Your GitHub account needs a primary verified email to sign in.',
            ]);
        }

        $user = User::query()
            ->where('github_id', (string) $githubUser->getId())
            ->orWhere('email', $githubEmail)
            ->first();

        if (! $user) {
            $candidate = [
                'provider' => 'github',
                'provider_label' => 'GitHub',
                'provider_id' => (string) $githubUser->getId(),
                'email' => $githubEmail,
                'name' => (string) ($githubUser->getName() ?: 'GitHub User'),
                'avatar' => (string) $githubUser->getAvatar(),
            ];

            session()->put('oauth_registration_candidate', $candidate);

            if ($registerIntent && ! $usePopup) {
                try {
                    $registeredUser = $this->createUserFromOAuthCandidate->create($candidate);
                } catch (InvalidArgumentException $exception) {
                    return to_route('login')->with('error', $exception->getMessage());
                }

                Auth::login($registeredUser, remember: true);

                return redirect()->to(route('security.edit').'#oauth')
                    ->with('success', 'Account created with GitHub. Please review your security settings.');
            }

            $message = 'No SpendLess account was found for this GitHub email.';

            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'prompt',
                    'provider' => 'GitHub',
                    'email' => $githubEmail,
                ]);
            }

            return to_route('login')
                ->withErrors(['email' => $message])
                ->with('error', $message)
                ->with('oauth_prompt', [
                    'provider' => 'GitHub',
                    'email' => $githubEmail,
                ]);
        }

        if (! $user->github_id) {
            $user->github_id = (string) $githubUser->getId();
        }

        $user->github_avatar = $githubUser->getAvatar();
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        if ($usePopup) {
            $redirectTo = (string) $request->session()->pull('url.intended', route('dashboard', absolute: false));

            return $this->popupResponse([
                'type' => 'success',
                'redirect' => $redirectTo,
            ]);
        }

        return to_route('dashboard')->with('success', 'Login successful');
    }

    /**
     * Redirect the authenticated user to GitHub OAuth for account linking.
     */
    public function linkRedirect(): RedirectResponse
    {
        session()->put('oauth_link_intent', 'github');

        $callbackUrl = route('github.callback', absolute: true);

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
        $callbackUrl = route('github.callback', absolute: true);

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('github');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $githubUser = $driver->user();
        } catch (InvalidStateException) {
            return to_route('security.edit')->with('error', 'GitHub link expired or host changed. Please try again from the same browser tab.');
        }

        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            return to_route('login');
        }

        $githubEmail = $this->resolveVerifiedGithubEmail($githubUser->token, (string) $githubUser->getEmail());

        if ($githubEmail === null) {
            return to_route('security.edit')->with('error', 'Your GitHub account needs a primary verified email to be linked.');
        }

        $githubId = (string) $githubUser->getId();

        $alreadyLinkedToAnotherUser = User::query()
            ->where('github_id', $githubId)
            ->whereKeyNot($authenticatedUser->id)
            ->exists();

        if ($alreadyLinkedToAnotherUser) {
            return redirect()->to(route('security.edit').'#oauth')->with('error', 'This GitHub account is already linked to another SpendLess profile.');
        }

        $authenticatedUser->github_id = $githubId;
        $authenticatedUser->github_avatar = $githubUser->getAvatar();
        $authenticatedUser->save();

        return redirect()->to(route('security.edit').'#oauth')->with('success', 'GitHub account linked successfully.');
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

    /**
     * Return popup completion view with payload.
     *
     * @param  array<string, string>  $payload
     */
    private function popupResponse(array $payload): View
    {
        return view('auth.oauth-popup', [
            'payload' => $payload,
        ]);
    }
}
