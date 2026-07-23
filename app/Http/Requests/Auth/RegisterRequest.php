<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            // Phone-first: at least one identifier is required.
            'email'    => ['nullable', 'required_without:phone', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'required_without:email', 'string', 'regex:/^(\+9665|05)\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'gender'   => ['nullable', 'in:female,male,unspecified'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('auth_ui.validation.phone'),
        ];
    }
}
