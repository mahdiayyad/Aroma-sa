/**
 * Shared UI behavior: shows the .is-loading spinner state (buttons.css) on
 * the submit button of any form when it's submitted — including buttons
 * outside the form that reference it via the form="..." attribute (e.g. the
 * sticky mobile add-to-cart bar on the product detail page).
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]'));

            if (form.id) {
                buttons = buttons.concat(
                    Array.prototype.slice.call(document.querySelectorAll('button[form="' + form.id + '"]'))
                );
            }

            buttons.forEach(function (btn) {
                if (
                    btn.classList.contains('btn-aroma') ||
                    btn.classList.contains('btn-aroma-outline') ||
                    btn.classList.contains('btn-aroma-secondary')
                ) {
                    btn.classList.add('is-loading');
                }
            });
        });
    });
});
