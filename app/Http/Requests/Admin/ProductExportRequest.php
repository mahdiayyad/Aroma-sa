<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the products.export gate is enforced on the route and in the controller
    }

    public function rules(): array
    {
        return [
            'scope'    => ['nullable', 'in:all,filtered,selected'],
            'ids'      => ['nullable', 'array', 'max:20000'],
            'ids.*'    => ['integer'],
            'q'        => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer'],
            'active'   => ['nullable', 'in:all,0,1'],
        ];
    }
}
