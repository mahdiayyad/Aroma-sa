<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;

class OtpSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new InternationalPhone()],
        ];
    }
}
