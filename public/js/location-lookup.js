/**
 * Aroma — Saudi National Address location-code live preview.
 *
 * Wraps every `.js-location-code` input on the page (checkout billing/
 * shipping, the gift-recipient address, and the account address form all use
 * the same markup: the input plus a sibling `.aroma-location-preview` inside
 * a shared `.aroma-location-field` wrapper).
 *
 * This is a PREVIEW only. The server always re-resolves the code itself
 * before persisting anything (LocationLookupController is hit here purely so
 * the shopper sees what their code resolves to before submitting — the
 * checkout/account controllers never trust this result). There is
 * intentionally no hidden lat/lng/city field for this script to fill in.
 *
 * Per-input translated strings come from data attributes (data-lang-*),
 * rendered server-side by Blade — the same convention address-map.js already
 * uses for data-lat/data-lng/data-mapbox-token.
 */
(function () {
    'use strict';

    var FORMAT = /^[A-Za-z]{4}\d{4}$/;
    var DEBOUNCE_MS = 500;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('.js-location-code');
        if (!inputs.length || !window.AromaHttp) { return; }

        inputs.forEach(function (input) {
            var field = input.closest('.aroma-location-field') || input.parentElement;
            var preview = field ? field.querySelector('.aroma-location-preview') : null;
            var timer = null;
            var lastRequested = '';

            function setPreview(text, state) {
                if (!preview) { return; }
                preview.innerHTML = '<i class="bi bi-geo-alt" aria-hidden="true"></i><span>' + escapeHtml(text) + '</span>';
                preview.hidden = false;
                preview.className = 'aroma-location-preview is-' + state;
            }

            function clearPreview() {
                if (!preview) { return; }
                preview.hidden = true;
                preview.innerHTML = '';
            }

            function errorText(errorCode) {
                if (errorCode === 'not_found') { return input.dataset.langNotFound || ''; }
                if (errorCode === 'invalid_format') { return input.dataset.langInvalid || ''; }
                return input.dataset.langFailed || '';
            }

            function runLookup(code) {
                setPreview(input.dataset.langLoading || '', 'loading');

                var body = new FormData();
                body.set('code', code);

                window.AromaHttp.post('/location/lookup', body).then(function (res) {
                    if (code !== lastRequested) { return; } // a newer keystroke superseded this request
                    if (res && res.success) {
                        var d = res.data || {};
                        setPreview(d.formatted_address || [d.district, d.city, d.region].filter(Boolean).join(', '), 'success');
                    } else {
                        setPreview(errorText(res && res.error), 'error');
                    }
                }).catch(function (res) {
                    if (code !== lastRequested) { return; }
                    setPreview(errorText(res && res.error), 'error');
                });
            }

            function handleInput() {
                var caret = input.selectionStart;
                input.value = input.value.toUpperCase();
                if (caret !== null && input.setSelectionRange) { input.setSelectionRange(caret, caret); }

                clearTimeout(timer);
                input.classList.remove('is-invalid');

                var code = input.value.trim();
                lastRequested = code;

                if (!FORMAT.test(code)) {
                    clearPreview();
                    return;
                }

                timer = setTimeout(function () { runLookup(code); }, DEBOUNCE_MS);
            }

            input.addEventListener('input', handleInput);

            input.addEventListener('blur', function () {
                var code = input.value.trim();
                if (code && !FORMAT.test(code)) {
                    input.classList.add('is-invalid');
                }
            });

            // A saved-address picker (checkout) sets input.value directly and
            // dispatches this to trigger the same preview/validation path.
            input.addEventListener('aroma:location-code-set', handleInput);

            if (FORMAT.test(input.value.trim())) { handleInput(); }
        });
    });
})();
