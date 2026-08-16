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

    function bumpCount(selector, count) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.textContent = count;
            el.classList.toggle('d-none', !count);
            el.classList.remove('is-bumped');
            // reflow so the animation restarts on every change
            void el.offsetWidth;
            el.classList.add('is-bumped');
        });
    }

    function updateCartCount(count) { bumpCount('.js-cart-count', count); }
    function updateWishlistCount(count) { bumpCount('.js-wishlist-count', count); }

    // Drops a cart row from the DOM; if it was the last one, reload so the
    // page can render the "empty cart" state instead of a bare table.
    function removeCartRow(row) {
        if (row) { row.parentNode.removeChild(row); }
        if (!document.querySelector('[data-row]')) { window.location.reload(); }
    }

    var toastContainer;

    // action: {url,label} to show a link · null to suppress · undefined for the
    // default "view cart" link (used by add-to-cart).
    function showToast(message, type, action) {
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

        var link = action;
        if (typeof link === 'undefined' && !isError) {
            var cartUrl = document.body.getAttribute('data-cart-url');
            if (cartUrl) { link = { url: cartUrl, label: document.body.getAttribute('data-cart-label') || 'Cart' }; }
        }
        if (link && link.url) {
            var a = document.createElement('a');
            a.className = 'aroma-toast-link';
            a.href = link.url;
            a.textContent = link.label || '';
            toast.appendChild(a);
        }

        toastContainer.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('is-visible'); });

        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 3500);
    }

    // Dispatches a real, bubbling DOM event so existing input/change
    // listeners (live totals, cart AJAX update) fire exactly as if the user
    // had typed into the field themselves.
    function fireEvent(el, type) {
        var evt;
        try { evt = new Event(type, { bubbles: true }); }
        catch (e) { evt = document.createEvent('Event'); evt.initEvent(type, true, true); }
        el.dispatchEvent(evt);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // -3) Success flash → toast. Server-side redirects still set the
        // session('status') flash the usual Laravel way; this just renders it
        // as a toast (matching cart/wishlist feedback) instead of a banner.
        var flashSuccess = document.body.getAttribute('data-flash-success');
        if (flashSuccess) { showToast(flashSuccess, 'success'); }

        // -2) Brand-themed tooltips (see .tooltip overrides in aroma.css) —
        // replaces the native title="" hover tooltip everywhere it was used.
        // data-bs-tooltip="true" is a second marker for elements that already
        // use data-bs-toggle for something else (e.g. a dropdown toggle).
        document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-tooltip="true"]').forEach(function (el) {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                window.bootstrap.Tooltip.getOrCreateInstance(el);
            }
        });

        // -1) Quantity stepper — "+"/"-" buttons drive the number input.
        document.querySelectorAll('.aroma-qty-stepper').forEach(function (stepper) {
            var input = stepper.querySelector('.aroma-qty-input');
            if (!input) { return; }
            var minusBtn = stepper.querySelector('.aroma-qty-minus');
            var plusBtn = stepper.querySelector('.aroma-qty-plus');

            function bounds() {
                var min = parseInt(input.getAttribute('min'), 10);
                var max = parseInt(input.getAttribute('max'), 10);
                return { min: isNaN(min) ? 0 : min, max: isNaN(max) ? 99 : max };
            }
            function refreshDisabled() {
                var b = bounds();
                var val = parseInt(input.value, 10);
                if (isNaN(val)) { val = b.min; }
                if (minusBtn) { minusBtn.disabled = val <= b.min; }
                if (plusBtn) { plusBtn.disabled = val >= b.max; }
            }
            function step(delta) {
                var b = bounds();
                var val = parseInt(input.value, 10);
                if (isNaN(val)) { val = b.min; }
                var next = Math.min(b.max, Math.max(b.min, val + delta));
                if (next === val) { return; }
                input.value = next;
                fireEvent(input, 'input');
                fireEvent(input, 'change');
                refreshDisabled();
            }

            if (minusBtn) { minusBtn.addEventListener('click', function () { step(-1); }); }
            if (plusBtn) { plusBtn.addEventListener('click', function () { step(1); }); }
            input.addEventListener('input', refreshDisabled);
            refreshDisabled();
        });


        // 0) Confirm destructive actions: <form data-confirm="Delete this?"> —
        // a brand-themed SweetAlert2 dialog instead of the native browser
        // confirm(). Falls back to the native one if the CDN failed to load.
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var message = form.getAttribute('data-confirm');

                if (!window.Swal) {
                    if (!window.confirm(message)) { e.preventDefault(); }
                    return;
                }

                e.preventDefault();
                var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

                Swal.fire({
                    title: message,
                    icon: 'warning',
                    showCancelButton: true,
                    focusCancel: true,
                    reverseButtons: isRtl,
                    confirmButtonText: document.body.getAttribute('data-confirm-yes') || 'Yes',
                    cancelButtonText: document.body.getAttribute('data-confirm-cancel') || 'Cancel',
                    buttonsStyling: false,
                    customClass: {
                        popup: 'aroma-swal-popup',
                        confirmButton: 'aroma-swal-btn aroma-swal-btn-danger',
                        cancelButton: 'aroma-swal-btn aroma-swal-btn-outline'
                    }
                }).then(function (result) {
                    if (result.isConfirmed) { form.submit(); }
                });
            });
        });

        // 1) Loading state for regular navigating forms (not the live ones).
        document.querySelectorAll('form:not(.js-add-to-cart):not(.js-wishlist)').forEach(function (form) {
            form.addEventListener('submit', function () {
                brandSubmitButtons(form).forEach(function (btn) { btn.classList.add('is-loading'); });
            });
        });

        // 1c) Live wishlist toggle — flip the heart + header count, no reload.
        document.querySelectorAll('form.js-wishlist').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = form.querySelector('button');
                if (btn) { btn.disabled = true; }

                window.AromaHttp.post(form.action, new FormData(form))
                    .then(function (data) {
                        if (btn) {
                            btn.classList.toggle('is-active', !!data.active);
                            btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
                            var icon = btn.querySelector('i.bi');
                            if (icon) {
                                icon.classList.toggle('bi-heart-fill', !!data.active);
                                icon.classList.toggle('bi-heart', !data.active);
                            }
                        }
                        if (typeof data.count !== 'undefined') { updateWishlistCount(data.count); }

                        var wl = document.body.getAttribute('data-wishlist-url');
                        showToast(data.message || '', 'success',
                            wl ? { url: wl, label: document.body.getAttribute('data-wishlist-label') } : null);
                    })
                    .catch(function () {
                        showToast(document.body.getAttribute('data-cart-error') || 'Something went wrong.', 'danger', null);
                    })
                    .finally(function () { if (btn) { btn.disabled = false; } });
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
                body.append('qty', qty);

                input.disabled = true;
                window.AromaHttp.post(url, body)
                    .then(function (data) {
                        if (typeof data.count !== 'undefined') { updateCartCount(data.count); }
                        var row = input.closest('[data-row]');
                        if (data.removed) {
                            removeCartRow(row);
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

        // 1d) Live cart remove — delete a line via fetch, no reload.
        document.querySelectorAll('form.js-cart-remove').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var row = form.closest('[data-row]');
                var btn = form.querySelector('button');
                if (btn) { btn.disabled = true; }

                window.AromaHttp.post(form.action, new FormData(form))
                    .then(function (data) {
                        if (typeof data.count !== 'undefined') { updateCartCount(data.count); }
                        document.querySelectorAll('.js-cart-subtotal').forEach(function (el) { el.textContent = data.subtotal; });
                        removeCartRow(row);
                    })
                    .catch(function () {
                        showToast(document.body.getAttribute('data-cart-error') || 'Error', 'danger');
                        if (btn) { btn.disabled = false; }
                    });
            });
        });

        // 2) Live add-to-cart.
        document.querySelectorAll('form.js-add-to-cart').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var buttons = brandSubmitButtons(form);
                buttons.forEach(function (btn) { btn.classList.add('is-loading'); });

                window.AromaHttp.post(form.action, new FormData(form))
                    .then(function (data) {
                        if (typeof data.count !== 'undefined') { updateCartCount(data.count); }

                        // Prefer the confirmation modal (with gift suggestions);
                        // fall back to the toast if it isn't on the page.
                        if (window.AromaCartModal && data.item) {
                            window.AromaCartModal.open(data);
                        } else {
                            showToast(data.message || (document.body.getAttribute('data-cart-label') || 'Added'), 'success');
                        }
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

        // 3) Live contact form — AJAX submit with inline field errors, no reload.
        document.querySelectorAll('form.js-contact-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.textContent = ''; });

                var buttons = brandSubmitButtons(form);
                buttons.forEach(function (btn) { btn.classList.add('is-loading'); });

                window.AromaHttp.post(form.action, new FormData(form))
                    .then(function (data) {
                        showToast(data.message || 'Sent', 'success');
                        form.reset();

                        var topicSelect = form.querySelector('select[name="topic"]');
                        if (topicSelect && window.jQuery) {
                            window.jQuery(topicSelect).val('').trigger('change');
                        }
                    })
                    .catch(function (err) {
                        if (err && err.errors) {
                            Object.keys(err.errors).forEach(function (field) {
                                var input = form.querySelector('[name="' + field + '"]');
                                if (!input) { return; }
                                input.classList.add('is-invalid');
                                var feedback = input.parentElement.querySelector('.invalid-feedback');
                                if (feedback) { feedback.textContent = err.errors[field][0]; }
                            });
                        }

                        var msg = (err && err.message) || document.body.getAttribute('data-cart-error') || 'Something went wrong.';
                        showToast(msg, 'danger');
                    })
                    .finally(function () {
                        buttons.forEach(function (btn) { btn.classList.remove('is-loading'); });
                    });
            });
        });
    });
})();
