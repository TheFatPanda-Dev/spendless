<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateUserFromOAuthCandidate;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function __construct(private CreateUserFromOAuthCandidate $createUserFromOAuthCandidate) {}

    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->boolean('popup')) {
            session()->put('oauth_popup_google', true);
        }

        if ($request->boolean('register')) {
            session()->put('oauth_register_intent_google', true);
        }

        $callbackUrl = (string) config('services.google.redirect', route('google.callback', absolute: true));

        $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        return $driver->redirect();
    }

    /**
     * Handle Google callback and authenticate the user.
     */
    public function callback(Request $request): RedirectResponse|View
    {
        $usePopup = $request->boolean('popup') || (bool) $request->session()->pull('oauth_popup_google', false);
        $registerIntent = (bool) $request->session()->pull('oauth_register_intent_google', false);
        $callbackUrl = (string) config('services.google.redirect', route('google.callback', absolute: true));

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $googleUser = $driver->user();
        } catch (InvalidStateException) {
            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'error',
                    'message' => 'Google sign-in expired or host changed. Please try again.',
                ]);
            }

            return to_route('login')->withErrors([
                'email' => 'Google sign-in expired or host changed. Please try again from the same browser tab.',
            ]);
        }

        $intent = session()->pull('oauth_link_intent');

        if ($intent === 'google' && Auth::check()) {
            $authenticatedUser = Auth::user();

            if (! $authenticatedUser instanceof User) {
                return to_route('login');
            }

            $googleId = $googleUser->getId();

            $alreadyLinkedToAnotherUser = User::query()
                ->where('google_id', $googleId)
                ->whereKeyNot($authenticatedUser->id)
                ->exists();

            if ($alreadyLinkedToAnotherUser) {
                return redirect()->to(route('security.edit').'#oauth')
                    ->with('error', 'This Google account is already linked to another SpendLess profile.');
            }

            $authenticatedUser->google_id = $googleId;
            $googleAvatar = $this->resolveGoogleAvatar($googleUser);

            if ($googleAvatar !== null) {
                $authenticatedUser->google_avatar = $googleAvatar;
            }

            $authenticatedUser->save();

            return redirect()->to(route('security.edit').'#oauth')
                ->with('success', 'Google account linked successfully.');
        }

        $googleEmail = (string) $googleUser->getEmail();
        $googleAvatar = $this->resolveGoogleAvatar($googleUser);
        $googleEmailVerified = true;

        if (property_exists($googleUser, 'user')) {
            $googleEmailVerified = (bool) Arr::get($googleUser->user, 'email_verified', false);
        }

        if ($googleEmail === '' || ! $googleEmailVerified) {
            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'error',
                    'message' => 'Your Google account email must be verified before signing in.',
                ]);
            }

            return to_route('login')->withErrors([
                'email' => 'Your Google account email must be verified before signing in.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleEmail)
            ->first();

        if (! $user) {
            $candidate = [
                'provider' => 'google',
                'provider_label' => 'Google',
                'provider_id' => (string) $googleUser->getId(),
                'email' => $googleEmail,
                'name' => (string) ($googleUser->getName() ?: 'Google User'),
                'avatar' => $googleAvatar,
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
                    ->with('success', 'Account created with Google. Please review your security settings.');
            }

            $message = 'No SpendLess account was found for this Google email.';

            if ($usePopup) {
                return $this->popupResponse([
                    'type' => 'prompt',
                    'provider' => 'Google',
                    'email' => $googleEmail,
                ]);
            }

            return to_route('login')
                ->withErrors(['email' => $message])
                ->with('error', $message)
                ->with('oauth_prompt', [
                    'provider' => 'Google',
                    'email' => $googleEmail,
                ]);
        }

        if (! $user->google_id) {
            $user->google_id = $googleUser->getId();
        }

        if ($googleAvatar !== null) {
            $user->google_avatar = $googleAvatar;
        }

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
     * Redirect the authenticated user to Google OAuth for account linking.
     */
    public function linkRedirect(): RedirectResponse
    {
        session()->put('oauth_link_intent', 'google');

        $callbackUrl = (string) config('services.google.redirect', route('google.callback', absolute: true));

        $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

        if (method_exists($driver, 'redirectUrl')) {
            $driver = $driver->redirectUrl($callbackUrl);
        }

        return $driver->redirect();
    }

    /**
     * Handle Google callback and link account to the authenticated user.
     */
    public function linkCallback(): RedirectResponse
    {
        $callbackUrl = (string) config('services.google.redirect', route('google.callback', absolute: true));

        try {
            $driver = app('Laravel\\Socialite\\Contracts\\Factory')->driver('google');

            if (method_exists($driver, 'redirectUrl')) {
                $driver = $driver->redirectUrl($callbackUrl);
            }

            $googleUser = $driver->user();
        } catch (InvalidStateException) {
            return to_route('security.edit')->with('error', 'Google link expired or host changed. Please try again from the same browser tab.');
        }

        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            return to_route('login');
        }

        $googleId = $googleUser->getId();

        $alreadyLinkedToAnotherUser = User::query()
            ->where('google_id', $googleId)
            ->whereKeyNot($authenticatedUser->id)
            ->exists();

        if ($alreadyLinkedToAnotherUser) {
            return redirect()->to(route('security.edit').'#oauth')->with('error', 'This Google account is already linked to another SpendLess profile.');
        }

        $authenticatedUser->google_id = $googleId;
        $googleAvatar = $this->resolveGoogleAvatar($googleUser);

        if ($googleAvatar !== null) {
            $authenticatedUser->google_avatar = $googleAvatar;
        }

        $authenticatedUser->save();

        return redirect()->to(route('security.edit').'#oauth')->with('success', 'Google account linked successfully.');
    }

    /**
     * Resolve Google avatar URL from provider payload.
     */
    private function resolveGoogleAvatar(object $googleUser): ?string
    {
        $avatar = $googleUser->getAvatar();

        if (is_string($avatar) && $avatar !== '') {
            return $avatar;
        }

        if (property_exists($googleUser, 'user')) {
            $picture = Arr::get($googleUser->user, 'picture')
                ?? Arr::get($googleUser->user, 'avatar_url');

            if (is_string($picture) && $picture !== '') {
                return $picture;
            }
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
