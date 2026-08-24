{{-- International phone field — intl-tel-input (see intl-phone.js) attaches
     its own flag/dial-code/search dropdown to this plain input at runtime,
     so the markup here stays a single <input>; no manual prefix/input-group
     markup like the old Saudi-only version needed.

     $raw is passed through as the full stored/old value as-is (whatever
     format it's in, typically E.164 like +966501234567) — intl-tel-input
     parses a pre-filled international number and auto-selects the matching
     country/flag on init, so no manual prefix-stripping is needed here. --}}
@props([
    'name',              // e.g. 'phone', 'billing_address[phone]', 'recipient[phone]'
    'errorKey' => null,  // dot-notation for @error; derived from $name if omitted
    'value' => null,
    'required' => false,
])

@php
    $errorKey = $errorKey ?? str_replace(['[', ']'], ['.', ''], $name);
    $raw = old($errorKey, $value);
@endphp

<input type="tel" name="{{ $name }}" value="{{ $raw }}"
       data-error-key="{{ $errorKey }}"
       @if ($required) required @endif
       {{ $attributes->merge(['class' => 'form-control js-intl-phone'.($errors->has($errorKey) ? ' is-invalid' : '')]) }}>
@error($errorKey)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
