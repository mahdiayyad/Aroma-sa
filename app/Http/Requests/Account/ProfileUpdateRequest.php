<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\InternationalPhone;
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
            'phone'  => ['nullable', 'string', new InternationalPhone(), Rule::unique('users', 'phone')->ignore($id)],
            'gender' => ['nullable', 'in:female,male'],
            'dob'    => ['nullable', 'date', 'before:today'],
            'locale' => ['nullable', 'in:ar,en'],
        ];
    }
}
