<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new InternationalPhone()],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
