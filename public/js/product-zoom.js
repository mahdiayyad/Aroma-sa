/**
 * Aroma — product gallery zoom.
 *
 * Two independent features, neither touching the existing slide-transition
 * or lightbox nav scripts in catalog/product.blade.php:
 *
 *  1. Desktop hover-magnify: a cursor-following magnified pane layered over
 *     the main gallery image (#galleryZoomPane, see cards.css), shown only
 *     for real mouse/trackpad pointers (matchMedia hover:hover).
 *  2. Mobile pinch/double-tap zoom: scales the lightbox's own image
 *     (#lightboxImg) via a CSS transform, reset whenever the modal closes.
 *     Only #lightboxImg's transform is ever touched — the main gallery image
 *     is never scaled, so this can't desync from the slide/thumbnail logic.
 */
(function () {
    'use strict';

    /* --- Desktop hover-magnify -------------------------------------------- */
    (function () {
        var wrap = document.querySelector('.aroma-gallery-main');
        var main = document.getElementById('galleryMainImg');
        var pane = document.getElementById('galleryZoomPane');
        if (!wrap || !main || !pane) { return; }
        if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) { return; }

        function move(e) {
            var rect = wrap.getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width) * 100;
            var y = ((e.clientY - rect.top) / rect.height) * 100;
            x = Math.max(0, Math.min(100, x));
            y = Math.max(0, Math.min(100, y));

            // Read the main image's src fresh every move rather than once on
            // enter — it's what keeps this in sync across thumbnail/slide
            // switches with zero changes to that script.
            var src = main.getAttribute('src');
            if (pane.dataset.src !== src) {
                pane.style.backgroundImage = 'url(' + src + ')';
                pane.dataset.src = src;
            }
            pane.style.backgroundPosition = x + '% ' + y + '%';
        }

        wrap.addEventListener('mouseenter', function (e) {
            wrap.classList.add('is-zooming');
            move(e);
        });
        wrap.addEventListener('mousemove', move);
        wrap.addEventListener('mouseleave', function () {
            wrap.classList.remove('is-zooming');
        });
    })();

    /* --- Mobile pinch / double-tap zoom (lightbox only) --------------------- */
    (function () {
        var lightbox = document.getElementById('galleryLightbox');
        var stage = lightbox ? lightbox.querySelector('.aroma-lightbox-stage') : null;
        var img = document.getElementById('lightboxImg');
        if (!lightbox || !stage || !img) { return; }

        var MAX_SCALE = 3;
        var DOUBLE_TAP_SCALE = 2;
        var scale = 1;
        var originX = 50;
        var originY = 50;
        var pinchStartDistance = 0;
        var pinchStartScale = 1;
        var lastTapAt = 0;

        function apply() {
            img.style.transformOrigin = originX + '% ' + originY + '%';
            img.style.transform = 'scale(' + scale + ')';
        }

        function reset() {
            scale = 1;
            originX = 50;
            originY = 50;
            apply();
        }

        // Scale/position never persist across images or modal re-opens — a
        // fresh view always starts unzoomed.
        lightbox.addEventListener('hidden.bs.modal', reset);
        lightbox.addEventListener('show.bs.modal', reset);

        function distance(touches) {
            var dx = touches[0].clientX - touches[1].clientX;
            var dy = touches[0].clientY - touches[1].clientY;
            return Math.sqrt(dx * dx + dy * dy);
        }

        function originFromTouches(touches) {
            var rect = img.getBoundingClientRect();
            var midX = (touches[0].clientX + touches[1].clientX) / 2;
            var midY = (touches[0].clientY + touches[1].clientY) / 2;
            return {
                x: Math.max(0, Math.min(100, ((midX - rect.left) / rect.width) * 100)),
                y: Math.max(0, Math.min(100, ((midY - rect.top) / rect.height) * 100)),
            };
        }

        stage.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                pinchStartDistance = distance(e.touches);
                pinchStartScale = scale;
                var origin = originFromTouches(e.touches);
                originX = origin.x;
                originY = origin.y;
            } else if (e.touches.length === 1) {
                var now = Date.now();
                if (now - lastTapAt < 300) {
                    // Double-tap toggles between unzoomed and a fixed zoom
                    // level, centered on the tap point.
                    if (scale > 1) {
                        reset();
                    } else {
                        var rect = img.getBoundingClientRect();
                        originX = Math.max(0, Math.min(100, ((e.touches[0].clientX - rect.left) / rect.width) * 100));
                        originY = Math.max(0, Math.min(100, ((e.touches[0].clientY - rect.top) / rect.height) * 100));
                        scale = DOUBLE_TAP_SCALE;
                        apply();
                    }
                }
                lastTapAt = now;
            }
        }, { passive: true });

        stage.addEventListener('touchmove', function (e) {
            if (e.touches.length !== 2 || !pinchStartDistance) { return; }
            e.preventDefault();
            var ratio = distance(e.touches) / pinchStartDistance;
            scale = Math.max(1, Math.min(MAX_SCALE, pinchStartScale * ratio));
            apply();
        }, { passive: false });

        stage.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) { pinchStartDistance = 0; }
            // A pinch that ends back below 1x snaps fully back to centered/unzoomed.
            if (scale <= 1.02) { reset(); }
        });
    })();
})();
