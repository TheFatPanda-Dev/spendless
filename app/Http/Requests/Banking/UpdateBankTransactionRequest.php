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
        $merchantName = $this->input('merchant_name');

        if (is_string($name)) {
            $normalized = trim($name);

            $this->merge([
                'name' => $normalized === '' ? null : $normalized,
            ]);
        }

        if (is_string($merchantName)) {
            $normalizedMerchantName = trim($merchantName);

            $this->merge([
                'merchant_name' => $normalizedMerchantName === '' ? null : $normalizedMerchantName,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)),
            ],
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'merchant_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
