<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'new_email' => [
                ...$this->newEmailRules($this->user()->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $currentEmail = (string) $this->user()?->email;

                    if ($currentEmail !== '' && strcasecmp($value, $currentEmail) === 0) {
                        $fail('The new email address must be different from your current email address.');
                    }
                },
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
