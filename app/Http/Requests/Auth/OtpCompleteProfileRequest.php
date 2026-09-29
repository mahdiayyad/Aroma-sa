<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The phone number is already verified by the time this runs; a new account
 * needs nothing but a name. Email and gender are editable later in the
 * profile. The referral code is optional and never blocks signup.
 */
class OtpCompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'referral_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
