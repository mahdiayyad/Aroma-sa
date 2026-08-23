/**
 * Aroma — Saudi phone prefix. Every .js-phone-local input (see
 * <x-phone-input>) shows a fixed "+966" badge and only accepts the 9-digit
 * local subscriber number. On submit, each one is normalized to the full
 * +9665XXXXXXXX form the backend's phone regex already expects — so the
 * visible field only ever holds the short, easy-to-type part.
 *
 * Loaded once, sitewide (see layouts/app.blade.php) — capture-phase so it
 * still runs even if a page's own submit handler stops propagation.
 */
(function () {
    'use strict';

    function normalize(input) {
        var digits = input.value.replace(/\D/g, '');
        // Tolerate a pasted local (05...) or already-international (966...) value.
        digits = digits.replace(/^0/, '').replace(/^966/, '');
        input.value = digits ? '+966' + digits : '';
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        form.querySelectorAll('.js-phone-local').forEach(normalize);
    }, true);
})();
