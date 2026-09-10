@props([
    // Either session-cart rows ([{label:{ar,en}, value_label:{ar,en}, price_delta}])
    // or OrderItem::optionRows() ([{label, value, price_delta}]) — both accepted.
    'options' => [],
    // Wrapper class for each line; caller matches it to the surrounding context.
    'lineClass' => 'small text-aroma-muted',
])
@php($locale = app()->getLocale())
@foreach (($options ?? []) as $opt)
    @php($rawLabel = $opt['label'] ?? '')
    @php($label = is_array($rawLabel) ? ($rawLabel[$locale] ?? reset($rawLabel)) : $rawLabel)
    @php($rawValue = $opt['value'] ?? ($opt['value_label'] ?? ''))
    @php($value = is_array($rawValue) ? ($rawValue[$locale] ?? reset($rawValue)) : $rawValue)
    @php($delta = (float) ($opt['price_delta'] ?? 0))
    {{-- The "+" glyph has no proper form in the Arabic display face (see
         --aroma-input-font), so the amount is set in the Latin-first stack. --}}
    <div class="{{ $lineClass }}">{{ $label }}@if ($delta <= 0 && filled($value)): {{ $value }}@endif @if ($delta > 0)<span class="fw-semibold text-nowrap" style="font-family:var(--aroma-input-font)">+@price($delta)</span>@endif</div>
@endforeach
