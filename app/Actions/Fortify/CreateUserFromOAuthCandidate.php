<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateUserFromOAuthCandidate
{
    /**
     * Create or update a user from an OAuth registration candidate.
     *
     * @param  array<string, mixed>  $candidate
     */
    public function create(array $candidate): User
    {
        $provider = (string) ($candidate['provider'] ?? '');
        $providerLabel = (string) ($candidate['provider_label'] ?? 'OAuth');
        $providerId = (string) ($candidate['provider_id'] ?? '');
        $email = (string) ($candidate['email'] ?? '');
        $name = (string) ($candidate['name'] ?? '');
        $avatar = (string) ($candidate['avatar'] ?? '');

        if (! in_array($provider, ['google', 'github'], true) || $providerId === '' || $email === '') {
            throw new InvalidArgumentException('Invalid OAuth registration payload. Please try again.');
        }

        $providerIdColumn = $provider.'_id';
        $providerAvatarColumn = $provider.'_avatar';

        $user = User::query()
            ->where('email', $email)
            ->orWhere($providerIdColumn, $providerId)
            ->first();

        if (! $user) {
            return User::create([
                'name' => $name !== '' ? $name : Str::title($providerLabel.' User'),
                'email' => $email,
                $providerIdColumn => $providerId,
                $providerAvatarColumn => $avatar !== '' ? $avatar : null,
                'email_verified_at' => now(),
                'password' => Str::random(40),
                'password_set_at' => null,
            ]);
        }

        $user->{$providerIdColumn} ??= $providerId;

        if ($avatar !== '') {
            $user->{$providerAvatarColumn} = $avatar;
        }

        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }
}
