<?php

namespace App\Http\Requests\Settings;

use App\Support\Localization\NumberLocaleOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNumberLocalePreferenceRequest extends FormRequest
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
            'number_locale' => [
                'required',
                'string',
                Rule::in(NumberLocaleOptions::all()),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $locale = $this->input('number_locale');

        if (! is_string($locale)) {
            return;
        }

        $this->merge([
            'number_locale' => NumberLocaleOptions::normalize($locale),
        ]);
    }
}
