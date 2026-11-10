/**
 * Aroma — e-commerce tracking.
 *
 * The single place that sends store events to the Meta Pixel, the TikTok Pixel
 * and the GTM dataLayer. Events are built on the server
 * (App\Services\TrackingService) in one GA4-style shape and mapped here to each
 * platform's own event names and parameters.
 *
 * Events arrive from:
 *   - #aromaTrackingData: this page's events, plus any queued before a redirect
 *     (e.g. Purchase after the payment gateway sends the shopper back);
 *   - JSON responses of cart/wishlist actions, via AromaHttp (aroma-http.js);
 *   - the payment form's submit (AddPaymentInfo), see bindDeferredSubmits().
 */
(function () {
    'use strict';

    if (window.AromaTracking) { return; }

    // Meta has no standard ViewCart / ViewCategory / RemoveFromCart, so those go
    // out as custom events (trackCustom); everything else is a standard event.
    var META = {
        view_item: ['track', 'ViewContent'],
        view_item_list: ['trackCustom', 'ViewCategory'],
        add_to_cart: ['track', 'AddToCart'],
        remove_from_cart: ['trackCustom', 'RemoveFromCart'],
        view_cart: ['trackCustom', 'ViewCart'],
        begin_checkout: ['track', 'InitiateCheckout'],
        add_payment_info: ['track', 'AddPaymentInfo'],
        purchase: ['track', 'Purchase'],
        add_to_wishlist: ['track', 'AddToWishlist'],
        search: ['track', 'Search']
    };

    var TIKTOK = {
        view_item: 'ViewContent',
        view_item_list: 'ViewCategory',
        add_to_cart: 'AddToCart',
        remove_from_cart: 'RemoveFromCart',
        view_cart: 'ViewCart',
        begin_checkout: 'InitiateCheckout',
        add_payment_info: 'AddPaymentInfo',
        purchase: 'CompletePayment',
        add_to_wishlist: 'AddToWishlist',
        search: 'Search'
    };

    var ONCE_KEY = 'aroma_tracked_once';
    var sentThisPage = {};
    var deferred = {};

    /* --------------------------------------------------------------- dispatch */

    function dispatch(events) {
        (events || []).forEach(function (event) {
            try { send(event); } catch (err) { /* tracking must never break the store */ }
        });
    }

    function send(event) {
        if (!event || !event.event || !event.ecommerce) { return; }

        if (event.event_id) {
            if (sentThisPage[event.event_id]) { return; }
            sentThisPage[event.event_id] = true;
        }

        // Purchase: the server hands it out once per order; this also covers a
        // browser re-running a cached copy of the page (back/forward).
        if (event.once && event.event_id) {
            if (alreadySentOnce(event.event_id)) { return; }
            rememberSentOnce(event.event_id);
        }

        toDataLayer(event);
        toMeta(event);
        toTikTok(event);
    }

    /* -------------------------------------------------------------- dataLayer */

    function toDataLayer(event) {
        window.dataLayer = window.dataLayer || [];
        // Google's recommendation: clear the previous ecommerce object first so
        // GTM never merges two events' items.
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({ event: event.event, event_id: event.event_id, ecommerce: event.ecommerce });
    }

    /* ------------------------------------------------------------------- Meta */

    function toMeta(event) {
        var mapping = META[event.event];
        if (!mapping || typeof window.fbq !== 'function') { return; }

        window.fbq(mapping[0], mapping[1], metaParams(event), { eventID: event.event_id });
    }

    function metaParams(event) {
        var ec = event.ecommerce;
        var items = ec.items || [];
        var params = {
            content_type: 'product',
            content_ids: items.map(function (item) { return item.item_id; }),
            contents: items.map(function (item) {
                return { id: item.item_id, quantity: item.quantity, item_price: item.price };
            }),
            currency: ec.currency
        };

        if (typeof ec.value === 'number') { params.value = ec.value; }
        if (items.length === 1) {
            params.content_name = items[0].item_name;
            if (items[0].item_category) { params.content_category = items[0].item_category; }
        }
        if (event.event === 'view_item_list' && ec.item_list_name) { params.content_category = ec.item_list_name; }
        if (event.event === 'begin_checkout' || event.event === 'purchase') { params.num_items = totalQuantity(items); }
        if (event.event === 'purchase' && ec.transaction_id) { params.order_id = ec.transaction_id; }
        if (ec.search_term) { params.search_string = ec.search_term; }

        return params;
    }

    /* ----------------------------------------------------------------- TikTok */

    function toTikTok(event) {
        var name = TIKTOK[event.event];
        if (!name || !window.ttq || typeof window.ttq.track !== 'function') { return; }

        window.ttq.track(name, tiktokParams(event), { event_id: event.event_id });
    }

    function tiktokParams(event) {
        var ec = event.ecommerce;
        var items = ec.items || [];
        var params = {
            content_type: 'product',
            contents: items.map(function (item) {
                return {
                    content_id: item.item_id,
                    content_type: 'product',
                    content_name: item.item_name,
                    quantity: item.quantity,
                    price: item.price
                };
            }),
            currency: ec.currency
        };

        if (typeof ec.value === 'number') { params.value = ec.value; }
        if (event.event === 'view_item_list' && ec.item_list_name) { params.description = ec.item_list_name; }
        if (ec.search_term) { params.query = ec.search_term; }

        return params;
    }

    /* -------------------------------------------------- form-submit events */

    // A form with data-track-submit="<name>" fires that deferred event when it
    // is really submitted (the browser only dispatches submit once its built-in
    // validation passes). The navigation is held for a moment so the pixel
    // requests leave before the browser goes to the payment gateway.
    function bindDeferredSubmits() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || e.defaultPrevented) { return; }

            var template = deferred[form.getAttribute('data-track-submit') || ''];
            if (!template || form.getAttribute('data-tracked') === '1') { return; }
            form.setAttribute('data-tracked', '1');

            try {
                var event = JSON.parse(JSON.stringify(template));
                var method = form.querySelector('input[name="gateway"]:checked');
                if (method) { event.ecommerce.payment_type = method.getAttribute('data-method') || method.value; }
                send(event);
            } catch (err) {
                return; // let the submit continue untouched
            }

            e.preventDefault();
            setTimeout(function () { HTMLFormElement.prototype.submit.call(form); }, 300);
        });
    }

    /* ---------------------------------------------------------------- helpers */

    function totalQuantity(items) {
        return items.reduce(function (sum, item) { return sum + (item.quantity || 0); }, 0);
    }

    function readOnceList() {
        try { return JSON.parse(window.localStorage.getItem(ONCE_KEY) || '[]'); } catch (err) { return []; }
    }

    function alreadySentOnce(id) {
        return readOnceList().indexOf(id) !== -1;
    }

    function rememberSentOnce(id) {
        try {
            var list = readOnceList();
            list.push(id);
            window.localStorage.setItem(ONCE_KEY, JSON.stringify(list.slice(-50)));
        } catch (err) { /* private mode: the server-side guard still applies */ }
    }

    /* ------------------------------------------------------------------- boot */

    window.AromaTracking = { dispatch: dispatch };

    var dataEl = document.getElementById('aromaTrackingData');
    var data = {};
    try { data = JSON.parse(dataEl ? dataEl.textContent : '{}') || {}; } catch (err) { data = {}; }

    deferred = data.deferred || {};
    bindDeferredSubmits();
    dispatch(data.events);
})();
