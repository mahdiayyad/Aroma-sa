{{--
    Email + password sign-in: credentials, then the 6-digit code emailed to that
    address. Same engine as the phone panel (public/js/otp-auth.js,
    data-otp-mode="email"): step 1 posts every [data-otp-field]; step 2 posts only
    the code, because the server remembers who passed step 1 in the session.
--}}
<div id="emailLoginPanel" data-otp-panel data-otp-mode="email"
     data-send-url="{{ route('login.email') }}"
     data-verify-url="{{ route('login.email.verify') }}"
     data-send-text="{{ __('email_auth.continue') }}"
     data-sending-text="{{ __('otp.sending') }}"
     data-verify-text="{{ __('otp.verify') }}"
     data-verifying-text="{{ __('otp.verifying') }}"
     data-verified-text="{{ __('otp.verified') }}"
     data-network-error="{{ __('otp.errors.network') }}"
     data-resend-template="{{ __('otp.resend_in', ['time' => '%TIME%']) }}">

    <div class="js-otp-phone-step">
        <div class="mb-3">
            <label class="form-label" for="emailLoginEmail">{{ __('email_auth.email_label') }}</label>
            <input type="email" id="emailLoginEmail" class="form-control" data-otp-field="email"
                   autocomplete="email" inputmode="email" dir="ltr">
        </div>
        <div class="mb-3">
            <label class="form-label" for="emailLoginPassword">{{ __('email_auth.password_label') }}</label>
            <input type="password" id="emailLoginPassword" class="form-control" data-otp-field="password"
                   autocomplete="current-password">
            <div class="form-text">{{ __('email_auth.hint') }}</div>
        </div>
        <div class="aroma-otp-error d-none js-otp-step1-error mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span class="js-otp-step1-error-text"></span>
        </div>
        <button type="button" class="btn btn-aroma w-100 mb-3 js-otp-send">{{ __('email_auth.continue') }}</button>
    </div>

    @include('auth.partials.otp-code-step', ['backLabel' => __('email_auth.back'), 'verifyTitle' => __('email_auth.verify_title')])
</div>
