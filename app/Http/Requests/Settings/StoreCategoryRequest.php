<?php

namespace App\Http\Requests\Settings;

use App\Models\Category;
use App\Support\Categories\CategoryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:80',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(CategoryOptions::typeValues()),
            ],
            'icon' => [
                'required',
                'string',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'color' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail('Choose a valid hex color.');

                        return;
                    }

                    if (
                        in_array($value, CategoryOptions::colorValues(), true)
                        || preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $value) === 1
                    ) {
                        return;
                    }

                    $fail('Choose a valid hex color.');
                },
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duplicateCategory = $this->findDuplicateSiblingCategory();

            if ($duplicateCategory instanceof Category) {
                $validator->errors()->add(
                    'name',
                    $this->filled('parent_id')
                        ? 'A subcategory with this name already exists under the selected parent.'
                        : 'A main category with this name already exists. This name can only be added as a subcategory.',
                );

                return;
            }

            if (! $this->filled('parent_id')) {
                return;
            }

            $parent = Category::query()->find($this->integer('parent_id'));

            if (! $parent instanceof Category) {
                return;
            }

            if ($parent->type !== $this->input('type')) {
                $validator->errors()->add(
                    'parent_id',
                    'The selected parent category must have the same type.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $type = $this->input('type');
        $icon = $this->input('icon');
        $color = $this->input('color');
        $parentId = $this->input('parent_id');

        $this->merge([
            'name' => is_string($name) ? $this->normalizeCategoryName($name) : $name,
            'type' => is_string($type) ? strtolower(trim($type)) : $type,
            'icon' => is_string($icon) ? trim($icon) : $icon,
            'color' => is_string($color) ? trim($color) : $color,
            'parent_id' => $parentId === '' ? null : $parentId,
        ]);
    }

    public function messages(): array
    {
        return [
            'icon.regex' => 'Choose a valid icon name.',
        ];
    }

    private function findDuplicateSiblingCategory(): ?Category
    {
        $name = $this->input('name');
        $userId = $this->user()?->id;

        if (! is_string($name) || $userId === null) {
            return null;
        }

        return Category::query()
            ->where('user_id', $userId)
            ->when(
                $this->filled('parent_id'),
                fn (Builder $query): Builder => $query->where('parent_id', $this->integer('parent_id')),
                fn (Builder $query): Builder => $query->whereNull('parent_id'),
            )
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();
    }

    private function normalizeCategoryName(string $value): string
    {
        return (string) Str::of($value)
            ->squish()
            ->lower()
            ->title();
    }
}
