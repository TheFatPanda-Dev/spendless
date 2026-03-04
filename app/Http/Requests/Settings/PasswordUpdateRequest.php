<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $hasPasswordSet = $user?->has_password_set ?? true;

        return [
            'current_password' => $hasPasswordSet ? $this->currentPasswordRules() : ['nullable', 'string'],
            'password' => [
                ...$this->passwordRules(),
                function (string $attribute, mixed $value, Closure $fail) use ($hasPasswordSet): void {
                    if (! $hasPasswordSet || ! is_string($value) || $value === '') {
                        return;
                    }

                    $user = $this->user();

                    if ($user && Hash::check($value, (string) $user->password)) {
                        $fail('The new password must be different from your current password.');
                    }
                },
            ],
        ];
    }
}
