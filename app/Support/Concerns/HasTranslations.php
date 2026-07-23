<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * Lightweight JSON translation support for Eloquent models.
 *
 * Translatable columns store a JSON map of locale => value, e.g.
 *   { "ar": "عطر الورد", "en": "Rose Perfume" }
 *
 * Declare which attributes are translatable on the model:
 *
 *   protected array $translatable = ['name', 'description'];
 *
 * Reading `$product->name` returns the value for the active locale with a
 * fallback to the app fallback locale, then to the first available value.
 * Assigning an array (`$product->name = ['ar' => '…', 'en' => '…']`) stores it
 * as JSON. This keeps content bilingual without a dependency or pivot tables.
 */
trait HasTranslations
{
    public function getAttributeValue($key)
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->translate($key);
    }

    public function setAttribute($key, $value)
    {
        if ($this->isTranslatableAttribute($key) && is_array($value)) {
            $this->attributes[$key] = $this->encodeTranslations($value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function translate(string $key, ?string $locale = null)
    {
        $locale       = $locale ?: app()->getLocale();
        $translations = $this->getTranslations($key);

        if (isset($translations[$locale]) && $translations[$locale] !== '') {
            return $translations[$locale];
        }

        $fallback = config('app.fallback_locale');

        if (isset($translations[$fallback]) && $translations[$fallback] !== '') {
            return $translations[$fallback];
        }

        return $translations ? reset($translations) : null;
    }

    public function setTranslation(string $key, string $locale, $value): self
    {
        $translations          = $this->getTranslations($key);
        $translations[$locale] = $value;
        $this->attributes[$key] = $this->encodeTranslations($translations);

        return $this;
    }

    public function getTranslations(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    protected function encodeTranslations(array $translations): string
    {
        return json_encode($translations, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
