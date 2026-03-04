<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'preferred_name',
        'email',
        'google_id',
        'google_avatar',
        'github_id',
        'github_avatar',
        'avatar_path',
        'password',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar',
        'display_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function bankConnections(): HasMany
    {
        return $this->hasMany(\App\Models\BankConnection::class);
    }

    public function getAvatarAttribute(): ?string
    {
        if ($this->avatar_path) {
            return Storage::url($this->avatar_path);
        }

        if ($this->google_avatar) {
            return $this->google_avatar;
        }

        if ($this->github_avatar) {
            return $this->github_avatar;
        }

        return null;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->preferred_name ?: $this->name;
    }
}
