<?php

namespace App\Http\Requests\Settings;

use App\Models\BankAccount;
use App\Support\Currency\BaseCurrencyOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBaseCurrencyPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'base_currency' => [
                'required',
                'string',
                'size:3',
                Rule::in($this->allowedCurrencies()),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $baseCurrency = $this->input('base_currency');

        if (! is_string($baseCurrency)) {
            return;
        }

        $this->merge([
            'base_currency' => strtoupper(trim($baseCurrency)),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function allowedCurrencies(): array
    {
        $user = $this->user();

        if ($user === null) {
            return ['EUR'];
        }

        $accountCurrencies = BankAccount::query()
            ->whereHas('connection', fn ($query) => $query
                ->where('user_id', $user->id))
            ->pluck('currency_code')
            ->merge(
                BankAccount::query()
                    ->whereHas('connection', fn ($query) => $query
                        ->where('user_id', $user->id))
                    ->pluck('currency'),
            )
            ->filter()
            ->map(fn (mixed $currency): string => strtoupper((string) $currency))
            ->unique()
            ->values()
            ->all();

        return BaseCurrencyOptions::all($accountCurrencies);
    }
}
