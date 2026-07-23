<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login'    => ['required', 'string'], // email or phone
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** Whether the supplied login looks like an email address. */
    public function loginField(): string
    {
        return filter_var($this->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    /** @return array<string,string> credentials for Auth::attempt */
    public function credentials(): array
    {
        return [
            $this->loginField() => $this->input('login'),
            'password'          => $this->input('password'),
        ];
    }
}
