{{--
    Profile: add an email + password, confirmed by a code emailed to that address.
    Same engine as the sign-in panels (public/js/otp-auth.js, data-otp-mode="email-add"):
    step 1 posts every [data-otp-field]; step 2 posts only the code — the server holds
    the pending (hashed) password + address in the session until the code checks out.
--}}
<div id="emailAddPanel" data-otp-panel data-otp-mode="email-add"
     data-send-url="{{ route('account.security.email.send') }}"
     data-verify-url="{{ route('account.security.email.verify') }}"
     data-send-text="{{ __('email_auth.profile.send_code') }}"
     data-sending-text="{{ __('otp.sending') }}"
     data-verify-text="{{ __('otp.verify') }}"
     data-verifying-text="{{ __('otp.verifying') }}"
     data-verified-text="{{ __('otp.verified') }}"
     data-network-error="{{ __('otp.errors.network') }}"
     data-resend-template="{{ __('otp.resend_in', ['time' => '%TIME%']) }}">

    <div class="js-otp-phone-step">
        <div class="mb-3">
            <label class="form-label" for="addEmail">{{ __('email_auth.profile.email') }}</label>
            <input type="email" id="addEmail" class="form-control" data-otp-field="email"
                   autocomplete="email" inputmode="email" dir="ltr">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="addPassword">{{ __('email_auth.profile.password') }}</label>
                <input type="password" id="addPassword" class="form-control" data-otp-field="password" autocomplete="new-password">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="addPasswordConfirm">{{ __('email_auth.profile.confirm') }}</label>
                <input type="password" id="addPasswordConfirm" class="form-control" data-otp-field="password_confirmation" autocomplete="new-password">
            </div>
        </div>
        <div class="aroma-otp-error d-none js-otp-step1-error mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span class="js-otp-step1-error-text"></span>
        </div>
        <button type="button" class="btn btn-aroma js-otp-send">{{ __('email_auth.profile.send_code') }}</button>
    </div>

    @include('auth.partials.otp-code-step', ['backLabel' => __('email_auth.back'), 'verifyTitle' => __('email_auth.verify_title')])
</div>
