/**
 * Aroma — location method toggle: "location code" vs "pin my location".
 *
 * Each `.aroma-location-switcher` wraps a segmented-control radio pair plus
 * two `.js-method-panel[data-method]` panels (one "code", one "map"). Only
 * the active panel's inputs are meant to be submitted — switching away from
 * a panel clears its named inputs so a stale code and a pinned coordinate
 * pair can never both reach the server at once (the backend only trusts
 * exactly one of them anyway, but this keeps the form's own state honest).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.aroma-location-switcher').forEach(function (switcher) {
            var radios = switcher.querySelectorAll('input[type="radio"][data-method]');
            var panels = switcher.querySelectorAll('.js-method-panel');

            function activate(method) {
                panels.forEach(function (panel) {
                    var isActive = panel.dataset.method === method;
                    panel.classList.toggle('d-none', !isActive);

                    if (isActive) {
                        panel.dispatchEvent(new Event('aroma:location-map-shown'));
                        return;
                    }

                    panel.querySelectorAll('input[name]').forEach(function (input) {
                        input.value = '';
                        input.classList.remove('is-invalid');
                    });
                });
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) { activate(radio.dataset.method); }
                });
            });
        });
    });
})();
