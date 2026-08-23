{{-- Fixed "+966" country-code badge — Aroma is Saudi-only, so every phone
     field only ever asks for the 9-digit local subscriber number (5XXXXXXXX)
     instead of making shoppers type a country code themselves. The whole
     control is forced dir="ltr" (badge + input) so "+966" always sits before
     the digits, the normal reading order for a phone number, even on an
     RTL page.

     phone-prefix.js normalizes the submitted value to +9665XXXXXXXX on
     submit — the exact form app/Http/Requests/*'s phone regex already
     accepts — so no backend validation changes were needed for this. --}}
@props([
    'name',              // e.g. 'phone', 'billing_address[phone]', 'recipient[phone]'
    'errorKey' => null,  // dot-notation for @error; derived from $name if omitted
    'value' => null,
    'required' => false,
])

@php
    $errorKey = $errorKey ?? str_replace(['[', ']'], ['.', ''], $name);
    $raw = old($errorKey, $value);
    // Always display just the local part, whatever form the stored/old
    // value happens to be in (+9665XXXXXXXX, legacy 05XXXXXXXX, or bare).
    $display = $raw ? preg_replace('/^(\+966|0)/', '', $raw) : '';
@endphp

<div class="input-group aroma-phone-input" dir="ltr">
    <span class="input-group-text"><span class="aroma-phone-plus">+</span>966</span>
    <input type="tel" name="{{ $name }}" dir="ltr" inputmode="numeric" autocomplete="tel-national"
           maxlength="9" placeholder="5XXXXXXXX" value="{{ $display }}"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'form-control js-phone-local'.($errors->has($errorKey) ? ' is-invalid' : '')]) }}>
</div>
@error($errorKey)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
