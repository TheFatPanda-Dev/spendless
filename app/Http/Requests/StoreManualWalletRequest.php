<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'currency' => strtoupper(trim((string) $this->input('currency', 'EUR'))),
            'starting_balance' => $this->filled('starting_balance') ? $this->input('starting_balance') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'starting_balance' => ['nullable', 'numeric', 'between:-999999999999.99,999999999999.99'],
        ];
    }
}
