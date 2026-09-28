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

        // No `phone` here on purpose: the mobile number is the sign-in
        // credential and only changes through the verified flow in
        // Account\PhoneChangeController.
        $rules = [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'gender' => ['nullable', 'in:female,male'],
            'dob'    => ['nullable', 'date', 'before:today'],
            'locale' => ['nullable', 'in:ar,en'],
        ];

        // Once the account has a password its email is a sign-in credential (login
        // codes are sent there), so it only changes through verified flows — never
        // this plain form.
        if ($this->user()->password) {
            unset($rules['email']);
        }

        return $rules;
    }
}
