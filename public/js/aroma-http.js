/**
 * Aroma — CSRF-resilient POST helper.
 *
 * A tab left open past the session lifetime carries a stale CSRF token, so the
 * next add-to-cart / wishlist action dies with "CSRF token mismatch" (419).
 * This wrapper detects that, pulls a fresh token from /csrf-token, updates the
 * page (meta tag + every hidden _token input) and retries the request once.
 *
 * Usage: window.AromaHttp.post(url, FormData) -> Promise<json>
 */
(function () {
    'use strict';

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function applyToken(fresh) {
        if (!fresh) { return; }

        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) { meta.setAttribute('content', fresh); }

        // Keep normal (non-JS) forms on the page usable too.
        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = fresh;
        });
    }

    function refresh() {
        return fetch('/csrf-token', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) { applyToken(data.token); return data.token; });
    }

    function send(url, body) {
        // Send the token both ways: the header wins even if a form's hidden
        // input is stale, which is exactly the case we're recovering from.
        if (body instanceof FormData) { body.set('_token', token()); }

        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token()
            },
            body: body,
            credentials: 'same-origin'
        });
    }

    function post(url, body) {
        return send(url, body).then(function (res) {
            if (res.status !== 419) { return finish(res); }

            // Stale token — refresh and replay the request once.
            return refresh()
                .then(function () { return send(url, body); })
                .then(finish);
        });
    }

    function finish(res) {
        return res.json()
            .catch(function () { return {}; })
            .then(function (data) { return res.ok ? data : Promise.reject(data); });
    }

    window.AromaHttp = { post: post, token: token, refresh: refresh };
})();
