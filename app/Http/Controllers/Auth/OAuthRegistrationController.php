<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateUserFromOAuthCandidate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class OAuthRegistrationController extends Controller
{
    public function __construct(private CreateUserFromOAuthCandidate $createUserFromOAuthCandidate) {}

    public function register(Request $request): RedirectResponse
    {
        $candidate = $request->session()->pull('oauth_registration_candidate');

        if (! is_array($candidate)) {
            return to_route('login')->with('error', 'OAuth registration session expired. Please try signing in again.');
        }

        try {
            $user = $this->createUserFromOAuthCandidate->create($candidate);
        } catch (InvalidArgumentException $exception) {
            return to_route('login')->with('error', $exception->getMessage());
        }

        $providerLabel = (string) ($candidate['provider_label'] ?? 'OAuth');

        Auth::login($user, remember: true);

        return redirect()->to(route('profile.edit').'#oauth')
            ->with('success', 'Account created with '.$providerLabel.'. Please review your profile settings.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('oauth_registration_candidate');

        return to_route('login');
    }
}
