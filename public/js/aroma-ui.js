/**
 * Aroma storefront UI behaviour.
 *
 *  1. Loading state — adds the .is-loading spinner (buttons.css) to a form's
 *     brand buttons while it submits (including buttons linked via form="...").
 *  2. Live add-to-cart — forms with .js-add-to-cart post via fetch so the page
 *     never reloads; on success a toast appears and the header count updates.
 *     Non-JS clients still work via the normal redirect + flash fallback.
 */
(function () {
    'use strict';

    function brandSubmitButtons(form) {
        var buttons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]'));
        if (form.id) {
            buttons = buttons.concat(
                Array.prototype.slice.call(document.querySelectorAll('button[form="' + form.id + '"]'))
            );
        }
        return buttons.filter(function (btn) {
            return btn.classList.contains('btn-aroma') ||
                btn.classList.contains('btn-aroma-outline') ||
                btn.classList.contains('btn-aroma-secondary');
        });
    }

    function updateCartCount(count) {
        document.querySelectorAll('.js-cart-count').forEach(function (el) {
            el.textContent = count;
            el.classList.toggle('d-none', !count);
            el.classList.remove('is-bumped');
            // reflow so the animation restarts on every add
            void el.offsetWidth;
            el.classList.add('is-bumped');
        });
    }

    var toastContainer;

    function showToast(message, type) {
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'aroma-toast-container';
            document.body.appendChild(toastContainer);
        }

        var isError = type === 'danger';
        var toast = document.createElement('div');
        toast.className = 'aroma-toast aroma-toast-' + (isError ? 'danger' : 'success');
        toast.setAttribute('role', 'status');

        var icon = document.createElement('i');
        icon.className = 'bi ' + (isError ? 'bi-exclamation-circle' : 'bi-bag-check');

        var text = document.createElement('span');
        text.textContent = message;

        toast.appendChild(icon);
        toast.appendChild(text);

        var cartUrl = document.body.getAttribute('data-cart-url');
        var cartLabel = document.body.getAttribute('data-cart-label');
        if (!isError && cartUrl) {
            var link = document.createElement('a');
            link.className = 'aroma-toast-link';
            link.href = cartUrl;
            link.textContent = cartLabel || 'Cart';
            toast.appendChild(link);
        }

        toastContainer.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('is-visible'); });

        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3500);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // 1) Loading state for regular navigating forms.
        document.querySelectorAll('form:not(.js-add-to-cart)').forEach(function (form) {
            form.addEventListener('submit', function () {
                brandSubmitButtons(form).forEach(function (btn) { btn.classList.add('is-loading'); });
            });
        });

        // 1b) Live cart quantity — patch on change, update line total + subtotal + count.
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
        document.querySelectorAll('.js-cart-qty').forEach(function (input) {
            input.addEventListener('change', function () {
                var url = input.getAttribute('data-url');
                var qty = Math.max(0, parseInt(input.value, 10) || 0);
                input.value = qty;

                var body = new FormData();
                body.append('_method', 'PATCH');
                body.append('_token', csrf);
                body.append('qty', qty);

                input.disabled = true;
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (typeof data.count !== 'undefined') { updateCartCount(data.count); }
                        var row = input.closest('[data-row]');
                        if (data.removed) {
                            if (row) { row.parentNode.removeChild(row); }
                            if (!document.querySelector('.js-cart-qty')) { window.location.reload(); return; }
                        } else if (row) {
                            var cell = row.querySelector('.js-line-total');
                            if (cell) { cell.textContent = data.line_total; }
                        }
                        document.querySelectorAll('.js-cart-subtotal').forEach(function (el) { el.textContent = data.subtotal; });
                    })
                    .catch(function () { showToast(document.body.getAttribute('data-cart-error') || 'Error', 'danger'); })
                    .finally(function () { input.disabled = false; });
            });
        });

        // 2) Live add-to-cart.
        document.querySelectorAll('form.js-add-to-cart').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var buttons = brandSubmitButtons(form);
                buttons.forEach(function (btn) { btn.classList.add('is-loading'); });

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form),
                    credentials: 'same-origin'
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return res.ok ? data : Promise.reject(data);
                        });
                    })
                    .then(function (data) {
                        if (typeof data.count !== 'undefined') { updateCartCount(data.count); }
                        showToast(data.message || (document.body.getAttribute('data-cart-label') || 'Added'), 'success');
                    })
                    .catch(function (err) {
                        var msg = (err && err.errors && Object.values(err.errors)[0] && Object.values(err.errors)[0][0]) ||
                            (err && err.message) ||
                            document.body.getAttribute('data-cart-error') ||
                            'Something went wrong.';
                        showToast(msg, 'danger');
                    })
                    .finally(function () {
                        buttons.forEach(function (btn) { btn.classList.remove('is-loading'); });
                    });
            });
        });
    });
})();
