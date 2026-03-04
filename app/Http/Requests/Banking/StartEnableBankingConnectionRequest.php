<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class StartEnableBankingConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'aspsp_name' => ['required', 'string', 'max:120'],
            'aspsp_country' => ['required', 'string', 'size:2'],
            'psu_type' => ['required', 'string', 'in:personal,business'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aspsp_country' => strtoupper((string) $this->input('aspsp_country', (string) config('services.enable_banking.country', 'SI'))),
            'psu_type' => (string) $this->input('psu_type', 'personal'),
        ]);
    }
}
