<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_published' => $this->boolean('is_published')]);
    }

    public function rules(): array
    {
        return [
            'title.ar'            => ['required', 'string', 'max:255'],
            'title.en'            => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255'],
            'excerpt.ar'          => ['nullable', 'string', 'max:500'],
            'excerpt.en'          => ['nullable', 'string', 'max:500'],
            'body.ar'             => ['required', 'string'],
            'body.en'             => ['required', 'string'],
            'cover_image'         => ['nullable', 'image', 'max:4096'],
            'is_published'        => ['boolean'],
            'meta_title.ar'       => ['nullable', 'string', 'max:255'],
            'meta_title.en'       => ['nullable', 'string', 'max:255'],
            'meta_description.ar' => ['nullable', 'string', 'max:500'],
            'meta_description.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}
