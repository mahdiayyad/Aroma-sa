<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Email;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class EmailRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Email::normalize($this->input('email'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            // Deliberately not validated against `exists:users,referral_code` —
            // an unrecognized code should never block signup, only silently
            // skip the reward. See ReferralService::applyReferral().
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('email_auth.register.email_taken'),
        ];
    }
}
