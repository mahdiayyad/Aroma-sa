<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the products.import gate is enforced on the route and in the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_apply'            => $this->boolean('auto_apply'),
            'replace_images'        => $this->boolean('replace_images'),
            'ignore_image_failures' => $this->boolean('ignore_image_failures'),
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:'.((int) config('aroma.import.max_upload_mb', 10) * 1024)],
            'auto_apply'            => ['boolean'],
            'replace_images'        => ['boolean'],
            'ignore_image_failures' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => __('admin.products_io.file_invalid'),
            'file.mimes'    => __('admin.products_io.file_invalid'),
            'file.file'     => __('admin.products_io.file_invalid'),
            'file.max'      => __('admin.products_io.file_hint', ['max' => (int) config('aroma.import.max_upload_mb', 10)]),
        ];
    }
}
