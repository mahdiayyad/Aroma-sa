<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'topic'    => ['required', 'in:general,order,complaint,suggestion,other'],
            'message'  => ['required', 'string', 'max:2000'],
            // Honeypot — a real visitor never fills a field hidden with CSS.
            // "nullable" matters here: Laravel's ConvertEmptyStringsToNull
            // middleware turns the empty field into null before validation
            // reaches this point, and `prohibited` treats a present-but-null
            // value differently than a genuinely absent key — `size:0` under
            // `nullable` is the version that's actually correct for both cases.
            'website'  => ['nullable', 'size:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.size' => __('contact.form.errors.spam'),
        ];
    }
}
