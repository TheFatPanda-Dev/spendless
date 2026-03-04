<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\ConfirmEmailChangeNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'preferredName' => $user?->preferred_name,
            'pendingEmail' => $user?->pending_email,
            'oauth' => [
                'googleLinked' => (bool) $user?->google_id,
                'githubLinked' => (bool) $user?->github_id,
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill($request->safe()->except(['avatar', 'new_email']));

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $newEmail = (string) ($validated['new_email'] ?? '');

        if ($newEmail !== '' && Str::lower($newEmail) !== Str::lower($user->email)) {
            $user->pending_email = $newEmail;
            $user->pending_email_requested_at = now();

            Notification::route('mail', $newEmail)
                ->notify(new ConfirmEmailChangeNotification($user->id, $newEmail));
        }

        $user->save();

        if ($newEmail !== '' && Str::lower($newEmail) !== Str::lower($user->email)) {
            return to_route('profile.edit')->with('success', 'Confirmation email sent. Please confirm your new email address.');
        }

        return to_route('profile.edit');
    }

    public function confirmEmailChange(Request $request, User $user): RedirectResponse
    {
        $email = (string) $request->query('email', '');

        if (
            $email === ''
            || ! is_string($user->pending_email)
            || strcasecmp($user->pending_email, $email) !== 0
        ) {
            abort(403);
        }

        $user->email = $email;
        $user->email_verified_at = now();
        $user->pending_email = null;
        $user->pending_email_requested_at = null;
        $user->save();

        if (Auth::check() && Auth::id() === $user->id) {
            return to_route('profile.edit')->with('success', 'Email address updated successfully.');
        }

        return to_route('login')->with('status', 'Email address updated successfully.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
