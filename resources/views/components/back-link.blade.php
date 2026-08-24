@props(['href', 'label' => null])

{{-- Shared storefront "back" affordance — checkout's 7 steps, and any future
     page, all render this instead of copy-pasting the same RTL-aware chevron
     + label markup. Route target is always the caller's job (this component
     has no opinion on where "back" goes); `label` overrides the default
     checkout.buttons.back copy for non-checkout contexts. --}}
<a href="{{ $href }}" {{ $attributes->class(['btn btn-aroma-outline']) }}>
    <i class="bi {{ app()->getLocale() === 'ar' ? 'bi-chevron-right' : 'bi-chevron-left' }} me-2"></i>{{ $label ?? __('checkout.buttons.back') }}
</a>
