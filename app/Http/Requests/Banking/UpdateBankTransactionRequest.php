<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (! is_string($name)) {
            return;
        }

        $normalized = trim($name);

        $this->merge([
            'name' => $normalized === '' ? null : $normalized,
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'integer',
                'required_without:name',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)),
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
                'required_without:category_id',
            ],
        ];
    }
}
