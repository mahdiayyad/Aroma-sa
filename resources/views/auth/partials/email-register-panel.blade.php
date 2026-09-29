{{--
    Sign-up's second door: create an account with email + password, confirmed by
    a code emailed to that address. Same engine as the other email panels
    (public/js/otp-auth.js, data-otp-mode="email-register"): step 1 posts every
    [data-otp-field] (referral_code is data-otp-optional — it may stay blank);
    step 2 posts only the code — the server holds the pending account details in
    the session until the code checks out.
--}}
@php($referral = old('referral_code', session('referral_code_prefill', '')))

<div id="emailRegisterPanel" data-otp-panel data-otp-mode="email-register"
     data-send-url="{{ route('register.email') }}"
     data-verify-url="{{ route('register.email.verify') }}"
     data-send-text="{{ __('email_auth.register.submit') }}"
     data-sending-text="{{ __('otp.sending') }}"
     data-verify-text="{{ __('otp.verify') }}"
     data-verifying-text="{{ __('otp.verifying') }}"
     data-verified-text="{{ __('otp.verified') }}"
     data-network-error="{{ __('otp.errors.network') }}"
     data-resend-template="{{ __('otp.resend_in', ['time' => '%TIME%']) }}">

    <div class="js-otp-phone-step">
        <div class="mb-3">
            <label class="form-label" for="registerName">{{ __('email_auth.register.name') }}</label>
            <input type="text" id="registerName" class="form-control" data-otp-field="name" autocomplete="name">
        </div>
        <div class="mb-3">
            <label class="form-label" for="registerEmail">{{ __('email_auth.register.email') }}</label>
            <input type="email" id="registerEmail" class="form-control" data-otp-field="email"
                   autocomplete="email" inputmode="email" dir="ltr">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="registerPassword">{{ __('email_auth.register.password') }}</label>
                <input type="password" id="registerPassword" class="form-control" data-otp-field="password" autocomplete="new-password">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="registerPasswordConfirm">{{ __('email_auth.register.confirm') }}</label>
                <input type="password" id="registerPasswordConfirm" class="form-control" data-otp-field="password_confirmation" autocomplete="new-password">
            </div>
        </div>

        {{-- Referral code: optional, so it stays out of the way unless the
             shopper came from an invite link (then it's already filled in
             and the field is open) or asks for it — same pattern as the
             phone sign-up's "your name" step. --}}
        @if ($referral === '')
            <p class="mb-3">
                <a href="#registerReferralField" class="small" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="registerReferralField">
                    {{ __('auth_ui.register.referral_toggle') }}
                </a>
            </p>
        @endif
        <div class="mb-3 collapse {{ $referral !== '' ? 'show' : '' }}" id="registerReferralField">
            <label class="form-label" for="registerReferralCode">{{ __('auth_ui.register.referral_code') }}</label>
            <input type="text" id="registerReferralCode" class="form-control" data-otp-field="referral_code" data-otp-optional
                   value="{{ $referral }}" placeholder="{{ __('auth_ui.register.referral_code_placeholder') }}" dir="ltr" autocomplete="off">
            <p class="form-text mt-1 mb-0">{{ __('auth_ui.register.referral_code_hint') }}</p>
        </div>

        <div class="aroma-otp-error d-none js-otp-step1-error mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span class="js-otp-step1-error-text"></span>
        </div>
        <button type="button" class="btn btn-aroma w-100 mb-3 js-otp-send">{{ __('email_auth.register.submit') }}</button>
    </div>

    @include('auth.partials.otp-code-step', ['backLabel' => __('email_auth.back'), 'verifyTitle' => __('email_auth.verify_title')])
</div>
