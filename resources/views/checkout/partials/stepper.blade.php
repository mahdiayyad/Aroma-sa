{{-- Checkout progress indicator. Pass the 1-based current step:
     1 = review, 2 = account, 3 = address, 4 = gift,
     5 = order-review, 6 = payment.
     Uses only the inline @php directive on purpose (see project Blade note). --}}
@php($current = $step ?? 1)
@php($checkoutSteps = [
    'review'        => 'bi-bag-check',
    'account'       => 'bi-person',
    'address'       => 'bi-geo-alt',
    'gift'          => 'bi-gift',
    'order_review'  => 'bi-clipboard-check',
    'payment'       => 'bi-credit-card',
])

<nav class="checkout-steps" data-count="{{ count($checkoutSteps) }}" aria-label="{{ __('checkout.review') }}">
    @foreach($checkoutSteps as $key => $icon)
        @php($n = $loop->iteration)
        @php($state = $n < $current ? 'is-done' : ($n === $current ? 'is-active' : ''))
        <div class="checkout-step {{ $state }}" @if($n === $current) aria-current="step" @endif>
            <span class="checkout-step-dot">
                <i class="bi {{ $n < $current ? 'bi-check-lg' : $icon }}"></i>
            </span>
            <span class="checkout-step-label">{{ __('checkout.steps.'.$key) }}</span>
        </div>
    @endforeach
</nav>
{{-- Compact fallback shown only on very narrow screens (see the @media rule
     that hides the labels above) so the current step is never unlabeled. --}}
<p class="checkout-steps-caption">{{ __('checkout.step_of', ['n' => $current, 'total' => count($checkoutSteps), 'label' => __('checkout.steps.'.array_keys($checkoutSteps)[$current - 1])]) }}</p>
