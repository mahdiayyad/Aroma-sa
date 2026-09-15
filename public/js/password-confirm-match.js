/**
 * Aroma — live "passwords match" hint for password_confirmation fields.
 *
 * Purely additive polish: RegisterRequest/PasswordResetController already
 * validate `password` with the `confirmed` rule and render that error
 * inline via the existing @error('password') binding, so a mismatch is
 * never silently accepted — this just gives faster feedback than a full
 * round-trip while typing, using the same .valid-feedback/.invalid-feedback
 * classes and .is-valid/.is-invalid states the rest of the form system
 * already styles.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var confirmField = document.querySelector('input[name="password_confirmation"]');
        var form = confirmField ? confirmField.closest('form') : null;
        var passwordField = form ? form.querySelector('input[name="password"]') : null;
        if (!confirmField || !passwordField) { return; }

        var matchLabel = document.body.getAttribute('data-passwords-match') || '';
        var mismatchLabel = document.body.getAttribute('data-passwords-mismatch') || '';

        var hint = document.createElement('div');
        hint.className = 'd-block';
        hint.hidden = true;
        confirmField.insertAdjacentElement('afterend', hint);

        function check() {
            if (!confirmField.value) {
                hint.hidden = true;
                confirmField.classList.remove('is-valid', 'is-invalid');
                return;
            }

            var matches = confirmField.value === passwordField.value;
            hint.hidden = false;
            hint.textContent = matches ? matchLabel : mismatchLabel;
            hint.className = 'd-block ' + (matches ? 'valid-feedback' : 'invalid-feedback');
            confirmField.classList.toggle('is-valid', matches);
            confirmField.classList.toggle('is-invalid', !matches);
        }

        confirmField.addEventListener('input', check);
        passwordField.addEventListener('input', function () {
            if (confirmField.value) { check(); }
        });
    });
})();
