<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required' => $this->boolean('is_required'),
            'is_active'   => $this->boolean('is_active'),
            'key'         => Str::slug((string) ($this->input('key') ?: data_get($this->input('label'), 'en', '')), '_'),
        ]);

        // Normalise the repeater: drop fully-blank rows (no label in either
        // locale) and coerce the per-row switches. Keys are preserved so
        // default_index (a form-row index) stays consistent.
        $values = collect($this->input('values', []))
            ->filter(fn ($row) => is_array($row) && (filled(data_get($row, 'label.ar')) || filled(data_get($row, 'label.en'))))
            ->map(function ($row) {
                $row['is_active'] = (bool) ($row['is_active'] ?? false);
                $row['price_delta'] = $row['price_delta'] === '' || $row['price_delta'] === null ? 0 : $row['price_delta'];

                return $row;
            })
            ->all();

        $this->merge(['values' => $values]);
    }

    public function rules(): array
    {
        return [
            'label.ar'     => ['required', 'string', 'max:255'],
            'label.en'     => ['required', 'string', 'max:255'],
            'key'          => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'is_required'  => ['boolean'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],

            'values'                 => ['required', 'array', 'min:1'],
            'values.*.label.ar'      => ['required', 'string', 'max:255'],
            'values.*.label.en'      => ['required', 'string', 'max:255'],
            'values.*.price_delta'   => ['required', 'numeric', 'min:0'],
            'values.*.is_active'     => ['boolean'],
            'values.*.sort_order'    => ['nullable', 'integer', 'min:0'],

            'default_index' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // At least one value must stay active or the picker renders empty.
            $activeValues = collect($this->input('values', []))
                ->filter(fn ($row) => is_array($row) && (bool) ($row['is_active'] ?? false));

            if ($activeValues->isEmpty()) {
                $validator->errors()->add('values', __('admin.product_options.errors.no_active_value'));
            }
        });
    }
}
