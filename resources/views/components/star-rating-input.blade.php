@props(['name' => 'rating', 'value' => null])

{{-- Reusable 1-5 star radio input. DOM order is 5,4,3,2,1 with flex
     row-reverse so it displays 1..5 left-to-right — the CSS ~ sibling
     selector then lights up "this star and everything after it in DOM
     order", which is visually "this star and everything to its left".
     dir="ltr" keeps that consistent under Arabic too. See public/css/
     components/reviews.css for the (non-brand, review-specific) gold token. --}}
<div {{ $attributes->merge(['class' => 'aroma-star-input']) }} dir="ltr">
    @for ($i = 5; $i >= 1; $i--)
        <input type="radio" name="{{ $name }}" id="{{ $name }}Star{{ $i }}" value="{{ $i }}"
               {{ (string) old($name, $value) === (string) $i ? 'checked' : '' }} required>
        <label for="{{ $name }}Star{{ $i }}" aria-label="{{ $i }}"><i class="bi bi-star-fill"></i></label>
    @endfor
</div>
