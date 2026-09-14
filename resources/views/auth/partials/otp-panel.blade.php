{{--
    Reusable phone/OTP verification panel — phone entry + 6-digit code entry.
    One implementation, driven entirely by data-* attributes and public/js/
    otp-auth.js; drop this include anywhere the same passwordless flow is
    needed (currently: login) rather than copying the markup.
--}}
<div id="otpLoginPanel" class="d-none"
     data-send-url="{{ route('otp.send') }}"
     data-verify-url="{{ route('otp.verify') }}"
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
            <x-phone-input name="otp_phone" />
        </div>
        <button type="button" class="btn btn-aroma w-100 mb-3 js-otp-send">{{ __('otp.send_code') }}</button>
    </div>

    <div class="js-otp-code-step d-none">
        <div class="aroma-otp-step">
            <h2 class="aroma-otp-title">{{ __('otp.verify_title') }}</h2>
            <p class="aroma-otp-subtitle">
                {{ __('otp.enter_code_subtitle') }} <bdi class="aroma-otp-phone js-otp-entered-phone" dir="ltr"></bdi>
            </p>

            <x-otp-input name="otp_code" />

            <div class="aroma-otp-error d-none js-otp-error" role="alert">
                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                <span class="js-otp-error-text"></span>
            </div>

            <div class="aroma-otp-success d-none js-otp-success">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <span>{{ __('otp.verified') }}</span>
            </div>

            <button type="button" class="btn btn-aroma w-100 aroma-otp-submit js-otp-verify" disabled>{{ __('otp.verify') }}</button>

            <div class="aroma-otp-footer">
                <button type="button" class="aroma-otp-link js-otp-change-number">
                    <i class="bi {{ app()->getLocale() === 'ar' ? 'bi-chevron-right' : 'bi-chevron-left' }}" aria-hidden="true"></i>
                    {{ __('otp.change_number') }}
                </button>
                <div class="aroma-otp-resend-wrap">
                    <span class="aroma-otp-countdown js-otp-countdown"></span>
                    <button type="button" class="aroma-otp-resend js-otp-resend" disabled>{{ __('otp.resend') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
