<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'preferred_name' => $this->preferredNameRules(),
            'new_email' => $this->newEmailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate preferred names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function preferredNameRules(): array
    {
        return [
            'nullable',
            'string',
            'max:255',
        ];
    }

    /**
     * Get the validation rules used to validate requested new emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function newEmailRules(?int $userId = null): array
    {
        return [
            'nullable',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
