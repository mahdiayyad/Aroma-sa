/**
 * Aroma — add-to-cart confirmation modal.
 *
 * Exposes window.AromaCartModal.open(payload) where payload is the JSON from
 * POST /cart: { item, subtotal, count, suggestions[] }.
 *
 * Suggestions ("اجعل هديتك مثالية") add straight to the cart; once added the
 * button becomes a quantity stepper that can add more or remove the line again.
 */
(function () {
    'use strict';

    var modal = document.getElementById('aromaCartModal');
    if (!modal) { return; }

    var panel      = modal.querySelector('.aroma-cart-modal-panel');
    var strip      = document.getElementById('aromaSuggestStrip');
    var suggestBox = document.getElementById('aromaGiftSuggest');
    var totalEl    = document.getElementById('aromaCartTotal');

    var addUrl    = modal.getAttribute('data-add-url');
    var updateUrl = modal.getAttribute('data-update-url'); // + /{rowId}

    var i18n = {
        add:     modal.getAttribute('data-i18n-add') || 'Add',
        options: modal.getAttribute('data-i18n-options') || 'Options',
        remove:  modal.getAttribute('data-i18n-remove') || 'Remove',
        qty:     modal.getAttribute('data-i18n-qty') || 'Qty'
    };

    var lastFocused = null;

    /* ------------------------------------------------------------- open/close */

    function open(payload) {
        render(payload);

        lastFocused = document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        document.documentElement.style.overflow = 'hidden';

        var first = panel.querySelector('.aroma-cart-modal-close');
        if (first) { first.focus(); }
    }

    function close() {
        modal.classList.remove('is-open');
        document.documentElement.style.overflow = '';
        setTimeout(function () { modal.hidden = true; }, 250);
        if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
    }

    modal.addEventListener('click', function (e) {
        if (e.target.closest('[data-close]')) { close(); }
    });

    document.addEventListener('keydown', function (e) {
        if (modal.hidden) { return; }
        if (e.key === 'Escape') { close(); return; }

        // Keep focus inside the dialog while it is open.
        if (e.key === 'Tab') {
            var focusable = panel.querySelectorAll('button, a[href], input, [tabindex]:not([tabindex="-1"])');
            if (!focusable.length) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    /* ---------------------------------------------------------------- render */

    function render(payload) {
        var item = payload.item || {};

        setSrc('aromaAddedImg', item.image);
        setText('aromaAddedName', item.name);
        setText('aromaAddedTotal', item.line_total);
        // "Qty: 2" reads correctly in both languages; the × sign has no glyph
        // in the Arabic brand font.
        setText('aromaAddedQty', i18n.qty + ': ' + (item.qty || 1));

        var variant = document.getElementById('aromaAddedVariant');
        if (variant) {
            variant.textContent = item.variant || '';
            variant.hidden = !item.variant;
        }

        var optionsEl = document.getElementById('aromaAddedOptions');
        if (optionsEl) {
            var opts = item.options || [];
            optionsEl.textContent = opts.map(function (o) {
                return o.price ? (o.label + ' ' + o.price) : (o.label + ': ' + o.value);
            }).join('  ·  ');
            optionsEl.hidden = opts.length === 0;
        }

        if (totalEl) { totalEl.textContent = payload.subtotal || ''; }

        strip.innerHTML = '';
        var suggestions = payload.suggestions || [];
        suggestBox.hidden = suggestions.length === 0;
        suggestions.forEach(function (s) { strip.appendChild(card(s)); });
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) { el.textContent = value || ''; }
    }

    function setSrc(id, value) {
        var el = document.getElementById(id);
        if (el) { el.src = value || ''; }
    }

    /* ----------------------------------------------------- suggestion cards */

    function card(item) {
        var wrap = document.createElement('div');
        wrap.className = 'aroma-suggest-card';

        var img = document.createElement('img');
        img.src = item.image;
        img.alt = item.name;
        img.loading = 'lazy';
        wrap.appendChild(img);

        var body = document.createElement('div');
        body.className = 'aroma-suggest-body';

        var name = document.createElement('a');
        name.className = 'aroma-suggest-name';
        name.href = item.url;
        name.textContent = item.name;
        body.appendChild(name);

        var price = document.createElement('span');
        price.className = 'aroma-suggest-price';
        price.textContent = item.price;
        body.appendChild(price);

        var slot = document.createElement('div');
        body.appendChild(slot);

        // Variant / required-option products can't be chosen inside the modal
        // — send them to the PDP.
        if (item.has_variants || item.needs_options) {
            var link = document.createElement('a');
            link.className = 'aroma-suggest-add';
            link.href = item.url;
            link.textContent = i18n.options || 'Options';
            slot.appendChild(link);
        } else {
            slot.appendChild(addButton(item, slot));
        }

        wrap.appendChild(body);
        return wrap;
    }

    function addButton(item, slot) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'aroma-suggest-add';
        btn.textContent = i18n.add || 'Add';

        btn.addEventListener('click', function () {
            btn.disabled = true;

            post(addUrl, { product_id: item.id, qty: 1 })
                .then(function (data) {
                    bumpHeader(data.count);
                    if (totalEl && data.subtotal) { totalEl.textContent = data.subtotal; }

                    var rowId = data.item && data.item.row_id;
                    var qty = (data.item && data.item.qty) || 1;
                    slot.innerHTML = '';
                    slot.appendChild(stepper(item, slot, rowId, qty));
                })
                .catch(function () { btn.disabled = false; });
        });

        return btn;
    }

    function stepper(item, slot, rowId, qty) {
        var box = document.createElement('div');
        box.className = 'aroma-suggest-stepper';

        var minus = document.createElement('button');
        minus.type = 'button';
        // Bootstrap Icons rather than − / + characters: the Arabic brand face
        // has no glyphs for those, so they rendered as tofu boxes.
        minus.innerHTML = '<i class="bi bi-dash-lg" aria-hidden="true"></i>';
        minus.setAttribute('aria-label', i18n.remove || 'Remove');

        var count = document.createElement('span');
        count.className = 'aroma-suggest-qty';
        count.textContent = qty;

        var plus = document.createElement('button');
        plus.type = 'button';
        plus.innerHTML = '<i class="bi bi-plus-lg" aria-hidden="true"></i>';
        plus.setAttribute('aria-label', i18n.add || 'Add');

        function change(next) {
            minus.disabled = plus.disabled = true;

            post(updateUrl + '/' + rowId, { _method: 'PATCH', qty: next })
                .then(function (data) {
                    bumpHeader(data.count);
                    if (totalEl && data.subtotal) { totalEl.textContent = data.subtotal; }

                    if (data.removed) {
                        // Back to a plain "Add" button so it can be re-added.
                        slot.innerHTML = '';
                        slot.appendChild(addButton(item, slot));
                        return;
                    }
                    count.textContent = data.qty;
                })
                .finally(function () { minus.disabled = plus.disabled = false; });
        }

        minus.addEventListener('click', function () { change(Math.max(0, parseInt(count.textContent, 10) - 1)); });
        plus.addEventListener('click', function () { change(parseInt(count.textContent, 10) + 1); });

        box.appendChild(minus);
        box.appendChild(count);
        box.appendChild(plus);
        return box;
    }

    /* ---------------------------------------------------------------- helpers */

    // Routed through AromaHttp so a stale CSRF token is refreshed and retried
    // rather than failing the shopper's click.
    function post(url, fields) {
        var body = new FormData();
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });

        return window.AromaHttp.post(url, body);
    }

    function bumpHeader(count) {
        if (typeof count === 'undefined') { return; }
        document.querySelectorAll('.js-cart-count').forEach(function (el) {
            el.textContent = count;
            el.classList.toggle('d-none', !count);
            el.classList.remove('is-bumped');
            void el.offsetWidth;
            el.classList.add('is-bumped');
        });
    }

    window.AromaCartModal = { open: open, close: close };
})();
