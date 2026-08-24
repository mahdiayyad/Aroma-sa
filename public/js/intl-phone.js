/**
 * Aroma — international phone input. Every .js-intl-phone field (see
 * <x-phone-input>) gets a flag + dial-code + searchable-country dropdown via
 * intl-tel-input (loaded just before this file — see layouts/app.blade.php /
 * layouts/auth.blade.php). On submit, each field's value is swapped for its
 * clean E.164 form (intl-tel-input's own documented pattern for a plain,
 * non-JS-intercepted form post) — that's the exact format
 * App\Rules\InternationalPhone validates server-side.
 *
 * v24 doesn't expose a global instance registry (no window.intlTelInputGlobals
 * in this version — confirmed live, not assumed from older docs), so each
 * instance is kept on the input element itself (input._iti) rather than
 * relied on from elsewhere.
 */
(function () {
    'use strict';

    if (typeof window.intlTelInput !== 'function') {
        return;
    }

    // A phone number is conventionally read left-to-right (dial code first)
    // even inside an RTL page — the same reason every stored-phone display
    // in this app forces dir="ltr". intl-tel-input auto-detects RTL from
    // document.dir and, by design, puts the flag/dial-code box on the right
    // in that case (confirmed live: showSelectedCountryOnLeft is computed
    // from isRTL and can't be overridden via the init option of the same
    // name — passing it has no effect). So the flag box's position and the
    // input's reserved padding are corrected by hand here instead.
    //
    // A field that starts inside a hidden panel (e.g. checkout's "is this a
    // gift?" recipient section, toggled via a d-none class) has zero size at
    // DOMContentLoaded, when this first runs — offsetWidth reads 0, so
    // "correcting" the padding to 0 would instead reserve no space for the
    // flag box at all, and once the panel is revealed the typed number
    // renders underneath it. Guarding on width > 0 avoids ever writing that
    // wrong value, and the ResizeObserver below re-measures whenever the
    // box's real size becomes known — whenever the field is revealed, by
    // whatever mechanism reveals it, not just this one toggle.
    function keepFlagOnLeft(input) {
        if (document.documentElement.dir !== 'rtl') {
            return;
        }
        var wrap = input.closest('.iti');
        var container = wrap && wrap.querySelector('.iti__country-container');
        if (!container) {
            return;
        }
        var width = container.offsetWidth;
        if (!width) {
            return;
        }
        container.style.right = 'auto';
        container.style.left = '0px';
        input.style.paddingRight = '';
        input.style.paddingLeft = width + 'px';
    }

    function init(input) {
        var iti = window.intlTelInput(input, {
            initialCountry: 'sa',
            separateDialCode: true,
            nationalMode: false,
            autoPlaceholder: 'polite',
            countrySearch: true,
            formatOnDisplay: true,
        });
        input._iti = iti;

        // A pre-filled international value (e.g. "+966501234567" from old())
        // needs an explicit setNumber() to parse + select the right flag —
        // just setting the raw HTML value attribute doesn't trigger that.
        var initial = input.value.trim();
        if (initial) {
            iti.setNumber(initial);
        }

        keepFlagOnLeft(input);
        input.addEventListener('countrychange', function () { keepFlagOnLeft(input); });

        if (window.ResizeObserver) {
            var wrap = input.closest('.iti');
            var container = wrap && wrap.querySelector('.iti__country-container');
            if (container) {
                new ResizeObserver(function () { keepFlagOnLeft(input); }).observe(container);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-intl-phone').forEach(init);
    });

    // Capture-phase so this still runs even if a page's own submit handler
    // stops propagation — same defensive pattern the old phone-prefix.js used.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('.js-intl-phone').forEach(function (input) {
            if (!input._iti) {
                return;
            }
            // getNumber() is '' for an empty (optional, untouched) field —
            // matches what the field should submit as when left blank.
            input.value = input.value.trim() ? input._iti.getNumber() : '';
        });
    }, true);
})();
