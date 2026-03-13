<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merchantName = $this->input('merchant_name', $this->input('note'));
        $transactionName = $this->input('transaction_name', $this->input('label'));

        $this->merge([
            'merchant_name' => is_string($merchantName) ? trim((string) $merchantName) : null,
            'transaction_name' => is_string($transactionName) ? trim((string) $transactionName) : null,
            'date' => (string) $this->input('date', now()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)),
            ],
            'date' => ['required', 'date'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'transaction_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'between:0.01,999999999999.99'],
        ];
    }
}
