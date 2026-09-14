<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\InternationalPhone;
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
            'phone'    => ['nullable', 'required_without:email', 'string', new InternationalPhone(), 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'gender'   => ['nullable', 'in:female,male'],
            // Deliberately not validated against `exists:users,referral_code`
            // — an unrecognized code should never block registration itself,
            // only silently skip the reward. See ReferralService::applyReferral().
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
