<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\SaudiMobile;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $canonical = Phone::normalizeSaudi($this->input('phone'));

        if ($canonical !== null) {
            $this->merge(['phone' => $canonical]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SaudiMobile()],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
