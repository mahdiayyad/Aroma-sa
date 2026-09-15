/**
 * Aroma — address method toggle: National Address code vs Full Address.
 *
 * Each `[data-address-input]` wraps two radios (name="...method", one value
 * per Address::METHOD_*) and two `[data-method-panel]` panels. Unlike the
 * retired location-method-toggle.js, switching NEVER clears the inactive
 * panel's inputs — both methods' values are preserved so the shopper can
 * switch back and forth without losing what they typed.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-address-input]').forEach(function (root) {
            var radios = root.querySelectorAll('input[type="radio"][data-method]');
            var panels = root.querySelectorAll('[data-method-panel]');

            function activate(method) {
                panels.forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.dataset.methodPanel !== method);
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
