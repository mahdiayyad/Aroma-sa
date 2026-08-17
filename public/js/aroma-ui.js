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
        // -3.5) Hero carousel — respect prefers-reduced-motion by turning off
        // autoplay entirely (arrows/indicators still work); Bootstrap's own
        // Carousel component drives everything else declaratively via the
        // data-bs-* attributes already on the markup.
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion) {
            document.querySelectorAll('.aroma-hero-carousel').forEach(function (el) {
                if (window.bootstrap && window.bootstrap.Carousel) {
                    var instance = window.bootstrap.Carousel.getOrCreateInstance(el, { ride: false });
                    instance.pause();
                }
            });
        }

        // -3.4) Hero carousel — mouse drag-to-navigate, with the slide
        // actually tracking the cursor (not just a gesture-detect-then-jump).
        // Bootstrap's own swipe handling only listens for touch pointers, so
        // mouse users on desktop have no drag at all; this adds that parity
        // without touching Bootstrap's touch path (pointerType is filtered to
        // non-touch here, so the two never double-handle the same gesture).
        //
        // Bootstrap's carousel shows one item at a time via display:none/
        // block toggling — there's no permanent "all slides side by side"
        // track to translate as a whole, so a live drag has to temporarily
        // take over exactly two items (the active one and whichever neighbor
        // is being revealed), move them together as a two-frame filmstrip,
        // then either finish the transition (swap .active) or spring back —
        // all built by hand here rather than hooking into Bootstrap's own
        // (private, transform-driven) transition classes.
        document.querySelectorAll('.aroma-hero-carousel .carousel-inner').forEach(function (inner) {
            var carouselEl = inner.closest('.aroma-hero-carousel');
            if (!carouselEl || !window.bootstrap || !window.bootstrap.Carousel) return;

            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var isRTL = document.documentElement.dir === 'rtl';
            var DEAD_ZONE = 6; // px of movement before a direction/neighbor is committed to
            var SETTLE_MS = 380;

            var dragging = false;
            var settling = false;
            var locked = false; // a neighbor has been picked for this gesture
            var suppressClick = false;
            var startX = 0;
            var deltaX = 0;
            var width = 1;
            var enterFromRight = false; // which physical edge the neighbor slides in from
            var activeEl = null;
            var neighborEl = null;

            function items() {
                return Array.prototype.slice.call(inner.querySelectorAll(':scope > .carousel-item'));
            }

            function sibling(el, offset) {
                var list = items();
                var idx = list.indexOf(el);
                if (idx === -1) return null;
                var n = list.length;
                return list[(idx + offset + n) % n];
            }

            function resetItem(el) {
                if (!el) return;
                el.style.transition = '';
                el.style.transform = '';
                el.style.position = '';
                el.style.inset = '';
                el.style.display = '';
            }

            function syncIndicators(newActiveItem) {
                var idx = items().indexOf(newActiveItem);
                if (idx === -1) return;
                carouselEl.querySelectorAll('.carousel-indicators [data-bs-target]').forEach(function (dot, i) {
                    dot.classList.toggle('active', i === idx);
                    if (i === idx) { dot.setAttribute('aria-current', 'true'); }
                    else { dot.removeAttribute('aria-current'); }
                });
            }

            function finishInteraction() {
                settling = false;
                inner.classList.remove('is-dragging');
                inner.style.height = '';
                if (!reduceMotion) {
                    window.bootstrap.Carousel.getOrCreateInstance(carouselEl).cycle();
                }
            }

            function settle(committed) {
                settling = true;
                var activeEnd = committed ? (enterFromRight ? -width : width) : 0;
                var neighborEnd = committed ? 0 : (enterFromRight ? width : -width);

                [activeEl, neighborEl].forEach(function (el) {
                    el.style.transition = 'transform ' + SETTLE_MS + 'ms cubic-bezier(.25,.75,.4,1)';
                });
                void inner.offsetWidth; // flush so the transition applies going forward, not retroactively
                activeEl.style.transform = 'translateX(' + activeEnd + 'px)';
                neighborEl.style.transform = 'translateX(' + neighborEnd + 'px)';

                var done = false;
                var finish = function () {
                    if (done) return;
                    done = true;
                    if (committed) {
                        activeEl.classList.remove('active');
                        neighborEl.classList.add('active');
                        syncIndicators(neighborEl);
                    }
                    resetItem(activeEl);
                    resetItem(neighborEl);
                    activeEl = null;
                    neighborEl = null;
                    finishInteraction();
                };
                activeEl.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, SETTLE_MS + 80); // safety net if transitionend doesn't fire
            }

            inner.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'touch') return;
                if (typeof e.button === 'number' && e.button !== 0) return;
                if (dragging || settling) return;
                // The CTA link opts out of the drag entirely, so it behaves
                // as a completely normal link — no pointer capture to fight
                // over, no risk of a real click on it being swallowed.
                if (e.target.closest('.aroma-hero-slide-cta')) return;
                dragging = true;
                locked = false;
                startX = e.clientX;
                deltaX = 0;
                var rect = inner.getBoundingClientRect();
                width = rect.width || 1;
                activeEl = inner.querySelector(':scope > .carousel-item.active');
                neighborEl = null;
                inner.classList.add('is-dragging');
                window.bootstrap.Carousel.getOrCreateInstance(carouselEl).pause();
                try { inner.setPointerCapture(e.pointerId); } catch (err) {}
            });

            inner.addEventListener('pointermove', function (e) {
                if (!dragging || !activeEl) return;
                deltaX = e.clientX - startX;

                if (!locked) {
                    if (Math.abs(deltaX) < DEAD_ZONE) return;
                    // Physical entry edge, independent of RTL: dragging left
                    // always pulls the current slide left, revealing the
                    // neighbor from the right (and vice versa).
                    enterFromRight = deltaX < 0;
                    // Which DOM neighbor that is, RTL-aware: matches the
                    // arrow buttons, which sit on physically opposite sides
                    // in RTL (positioned via inset-inline-start/end).
                    var revealPrev = (deltaX > 0) !== isRTL;
                    neighborEl = revealPrev ? sibling(activeEl, -1) : sibling(activeEl, 1);
                    if (!neighborEl || neighborEl === activeEl) { neighborEl = null; return; }

                    locked = true;
                    inner.style.height = inner.getBoundingClientRect().height + 'px';
                    [activeEl, neighborEl].forEach(function (el) {
                        el.style.transition = 'none';
                        el.style.position = 'absolute';
                        el.style.inset = '0';
                        el.style.display = 'block';
                    });
                    neighborEl.style.transform = 'translateX(' + (enterFromRight ? width : -width) + 'px)';
                }

                var clamped = Math.max(-width, Math.min(width, deltaX));
                activeEl.style.transform = 'translateX(' + clamped + 'px)';
                neighborEl.style.transform = 'translateX(' + (clamped + (enterFromRight ? width : -width)) + 'px)';
            });

            var onRelease = function (e) {
                if (!dragging) return;
                dragging = false;
                try { inner.releasePointerCapture(e.pointerId); } catch (err) {}

                if (!locked || !neighborEl) {
                    inner.classList.remove('is-dragging');
                    if (activeEl) resetItem(activeEl);
                    activeEl = null;
                    if (!reduceMotion) {
                        window.bootstrap.Carousel.getOrCreateInstance(carouselEl).cycle();
                    }
                    return;
                }

                suppressClick = true;
                var threshold = Math.min(width * 0.15, 120);
                settle(Math.abs(deltaX) > threshold);
            };

            inner.addEventListener('pointerup', onRelease);
            inner.addEventListener('pointercancel', onRelease);

            // A real drag shouldn't also fire the slide-link's navigation.
            inner.addEventListener('click', function (e) {
                if (suppressClick) {
                    e.preventDefault();
                    e.stopPropagation();
                    suppressClick = false;
                }
            }, true);
        });

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

        // -1.5) Password visibility toggle — wraps every input[type=password]
        // on the page (login, register, reset, admin login, …) with an eye
        // icon that flips the field between password/text. Pure progressive
        // enhancement: works identically wherever this script is loaded, no
        // per-page markup needed.
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.closest('.aroma-password-field')) { return; }

            var wrapper = document.createElement('div');
            wrapper.className = 'aroma-password-field';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            var showLabel = document.body.getAttribute('data-show-password') || 'Show password';
            var hideLabel = document.body.getAttribute('data-hide-password') || 'Hide password';

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'aroma-password-toggle';
            toggle.setAttribute('aria-label', showLabel);

            var icon = document.createElement('i');
            icon.className = 'bi bi-eye';
            toggle.appendChild(icon);

            toggle.addEventListener('click', function () {
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
                toggle.setAttribute('aria-label', isHidden ? hideLabel : showLabel);
            });

            wrapper.appendChild(toggle);
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
