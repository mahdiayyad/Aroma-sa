<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone'  => ['nullable', 'string', 'regex:/^(\+9665|05)\d{8}$/', Rule::unique('users', 'phone')->ignore($id)],
            'gender' => ['nullable', 'in:female,male'],
            'dob'    => ['nullable', 'date', 'before:today'],
            'locale' => ['nullable', 'in:ar,en'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('auth_ui.validation.phone'),
        ];
    }
}
