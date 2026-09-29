{{--
    Reusable phone/OTP verification panel — phone entry + 6-digit code entry.
    One implementation, driven entirely by data-* attributes and public/js/
    otp-auth.js (which initialises every [data-otp-panel] on the page); drop
    this include anywhere phone verification is needed rather than copying
    the markup. Optional variables: $panelId, $sendUrl, $verifyUrl.
--}}
<div @if (! empty($panelId)) id="{{ $panelId }}" @else id="otpLoginPanel" @endif data-otp-panel data-otp-mode="phone"
     data-send-url="{{ $sendUrl ?? route('otp.send') }}"
     data-verify-url="{{ $verifyUrl ?? route('otp.verify') }}"
     data-send-text="{{ __('otp.send_code') }}"
     data-sending-text="{{ __('otp.sending') }}"
     data-verify-text="{{ __('otp.verify') }}"
     data-verifying-text="{{ __('otp.verifying') }}"
     data-verified-text="{{ __('otp.verified') }}"
     data-network-error="{{ __('otp.errors.network') }}"
     data-resend-template="{{ __('otp.resend_in', ['time' => '%TIME%']) }}">

    <div class="js-otp-phone-step">
        <div class="mb-3">
            <label class="form-label">{{ __('otp.phone_label') }}</label>
            <x-phone-input name="otp_phone" :only-saudi="true" data-otp-field="phone" />
            <div class="form-text">{{ __('otp.phone_hint') }}</div>
        </div>
        <div class="aroma-otp-error d-none js-otp-step1-error mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span class="js-otp-step1-error-text"></span>
        </div>
        <button type="button" class="btn btn-aroma w-100 mb-3 js-otp-send">{{ __('otp.send_code') }}</button>
    </div>

    @include('auth.partials.otp-code-step', ['backLabel' => __('otp.change_number')])
</div>
