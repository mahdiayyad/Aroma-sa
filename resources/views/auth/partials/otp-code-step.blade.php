{{--
    The second step shared by every verification panel: six digit boxes, error /
    success feedback, verify button, "back" link and resend timer. Driven by
    public/js/otp-auth.js. Optional variables: $backLabel, $verifyTitle.
--}}
    <div class="js-otp-code-step d-none">
        <div class="aroma-otp-step">
            <h2 class="aroma-otp-title">{{ $verifyTitle ?? __('otp.verify_title') }}</h2>
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
                    {{ $backLabel ?? __('otp.change_number') }}
                </button>
                <div class="aroma-otp-resend-wrap">
                    <span class="aroma-otp-countdown js-otp-countdown"></span>
                    <button type="button" class="aroma-otp-resend js-otp-resend" disabled>{{ __('otp.resend') }}</button>
                </div>
            </div>
        </div>
    </div>
