{{-- Checkout progress indicator. Pass the 1-based current step:
     1 = review, 2 = account, 3 = address, 4 = payment.
     Uses only the inline @php directive on purpose (see project Blade note). --}}
@php($current = $step ?? 1)
@php($checkoutSteps = ['review' => 'bi-bag-check', 'account' => 'bi-person', 'address' => 'bi-geo-alt', 'payment' => 'bi-credit-card'])

<nav class="checkout-steps" aria-label="{{ __('checkout.review') }}">
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
