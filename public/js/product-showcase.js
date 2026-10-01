/* ============================================================================
   Aroma — homepage featured-products 3D showcase
   State machine for the perspective carousel in product-showcase.css. All
   cards are persistent DOM siblings; the only thing this file ever does is
   compute each card's signed offset from the current center and toggle its
   tier class — click-to-recenter, drag/swipe, arrow keys, and the slow
   auto-advance all funnel through one goTo(index), so an arbitrary jump and
   a single step resolve through the exact same code path.

   prefers-reduced-motion gates autoplay (and the caption's own timing)
   explicitly via matchMedia — the sitewide blanket rule in animations.css
   only neutralises CSS transition/animation durations, it does nothing for
   a JS setInterval/setTimeout.
   ========================================================================== */
(function () {
    document.querySelectorAll('.aroma-showcase').forEach(function (root) {
        var stage = root.querySelector('.aroma-showcase-stage');
        if (!stage) { return; }

        var cards = Array.prototype.slice.call(stage.querySelectorAll('.aroma-showcase-card'));
        var n = cards.length;
        if (n < 3) { return; } // not enough items for a meaningful depth stack

        var caption = root.querySelector('.aroma-showcase-caption');
        var captionLink = caption ? caption.querySelector('.aroma-showcase-caption-link') : null;
        var captionName = caption ? caption.querySelector('.aroma-showcase-caption-name') : null;
        var captionPrice = caption ? caption.querySelector('.aroma-showcase-caption-price') : null;
        var prevBtn = root.querySelector('.aroma-showcase-control-prev');
        var nextBtn = root.querySelector('.aroma-showcase-control-next');

        var isRTL = document.documentElement.dir === 'rtl';
        var reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
        var reduceMotion = !!(reduceMotionQuery && reduceMotionQuery.matches);
        var MOVE_MS = reduceMotion ? 0 : 650; // mirrors --aroma-transition-slower
        var AUTOPLAY_MS = 5500;

        var centerIndex = Math.floor((n - 1) / 2); // both sides populated with depth on first paint
        var autoplayTimer = null;
        var dragging = false;
        var startX = 0;
        var deltaX = 0;
        var suppressClick = false;
        var DEAD_ZONE = 6;
        var COMMIT_DISTANCE = 50;

        function signedOffset(i) {
            var raw = ((i - centerIndex) % n + n) % n;
            if (raw > n / 2) { raw -= n; }
            return raw;
        }

        function tierClass(offset) {
            if (offset === 0) { return 'is-center'; }
            var side = offset < 0 ? 'prev' : 'next';
            var mag = Math.abs(offset);
            return mag <= 3 ? 'is-' + side + '-' + mag : 'is-beyond-' + side;
        }

        function updateCaption(centerCard) {
            if (!caption) { return; }
            caption.classList.remove('is-visible');
            setTimeout(function () {
                captionLink.href = centerCard.getAttribute('data-href');
                captionName.textContent = centerCard.getAttribute('data-name');
                captionPrice.textContent = centerCard.getAttribute('data-price');
                caption.classList.add('is-visible');
            }, MOVE_MS);
        }

        var TIER_CLASSES = ['is-center', 'is-prev-1', 'is-next-1', 'is-prev-2', 'is-next-2', 'is-prev-3', 'is-next-3', 'is-beyond-prev', 'is-beyond-next'];

        function render(previousCenter) {
            cards.forEach(function (card, i) {
                var offset = signedOffset(i);
                // classList.remove/add rather than a wholesale className
                // reset — a card's .is-broken fallback flag (set once, by
                // the image error handler below) must survive every later
                // tier change, not just its first render.
                card.classList.remove.apply(card.classList, TIER_CLASSES);
                card.classList.add(tierClass(offset));
                card.setAttribute('tabindex', Math.abs(offset) <= 2 ? '0' : '-1');
                card.setAttribute('aria-hidden', offset === 0 ? 'false' : 'true');
            });
            var newCenter = cards[centerIndex];
            if (newCenter !== previousCenter) { updateCaption(newCenter); }
        }

        function goTo(index) {
            var target = ((index % n) + n) % n;
            if (target === centerIndex) { return; }
            var previousCenter = cards[centerIndex];
            centerIndex = target;
            render(previousCenter);
            restartAutoplay();
        }

        function advance(delta) { goTo(centerIndex + delta); }

        function stopAutoplay() {
            if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; }
        }

        function startAutoplay() {
            if (reduceMotion) { return; }
            stopAutoplay();
            autoplayTimer = setInterval(function () { advance(1); }, AUTOPLAY_MS);
        }

        function restartAutoplay() { startAutoplay(); }

        // Initial paint — no previous center, so no caption fade-delay needed.
        render(null);
        if (caption) {
            var initial = cards[centerIndex];
            captionLink.href = initial.getAttribute('data-href');
            captionName.textContent = initial.getAttribute('data-name');
            captionPrice.textContent = initial.getAttribute('data-price');
            caption.classList.add('is-visible');
        }
        startAutoplay();

        // --- Click: non-center card recenters, center card's own <a> navigates ---
        stage.addEventListener('click', function (e) {
            if (suppressClick) { suppressClick = false; return; }
            var card = e.target.closest('.aroma-showcase-card');
            if (!card) { return; }
            var idx = cards.indexOf(card);
            if (idx === centerIndex) { return; } // let the real link navigate
            e.preventDefault();
            goTo(idx);
        });

        // --- Keyboard ---
        stage.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); advance(isRTL ? -1 : 1); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); advance(isRTL ? 1 : -1); }
        });

        // --- Hover / touch / focus pause ---
        stage.addEventListener('mouseenter', stopAutoplay);
        stage.addEventListener('mouseleave', restartAutoplay);
        stage.addEventListener('focusin', stopAutoplay);
        stage.addEventListener('focusout', restartAutoplay);

        // --- Prev/next buttons ---
        if (prevBtn) { prevBtn.addEventListener('click', function () { advance(isRTL ? 1 : -1); }); }
        if (nextBtn) { nextBtn.addEventListener('click', function () { advance(isRTL ? -1 : 1); }); }

        // --- Drag / swipe: one damped "nudge" on the whole stage, committing
        // to a single step on release — not a live per-card interpolation,
        // which would mean animating 7+ tiers in lockstep and risks a
        // busy/jittery feel the brand's motion language avoids. ---
        stage.addEventListener('pointerdown', function (e) {
            if (dragging) { return; }
            if (typeof e.button === 'number' && e.button !== 0) { return; }
            // The prev/next buttons sit inside the stage, so without this
            // guard every tap on them also starts a drag + pointer capture
            // on the stage, which can swallow/confuse the button's own
            // click — same reason the hero's drag handler opts its CTA
            // link out the same way.
            if (e.target.closest('.aroma-showcase-control')) { return; }
            dragging = true;
            startX = e.clientX;
            deltaX = 0;
            stopAutoplay();
            stage.classList.add('is-dragging');
            try { stage.setPointerCapture(e.pointerId); } catch (err) {}
        });

        stage.addEventListener('pointermove', function (e) {
            if (!dragging) { return; }
            deltaX = e.clientX - startX;
            if (Math.abs(deltaX) < DEAD_ZONE) { return; }
            var nudge = Math.max(-40, Math.min(40, deltaX * 0.25));
            stage.style.transition = 'none';
            stage.style.transform = 'translateX(' + nudge + 'px)';
        });

        function onRelease(e) {
            if (!dragging) { return; }
            dragging = false;
            stage.classList.remove('is-dragging');
            try { stage.releasePointerCapture(e.pointerId); } catch (err) {}
            stage.style.transition = 'transform ' + MOVE_MS + 'ms var(--aroma-ease)';
            stage.style.transform = '';
            if (Math.abs(deltaX) > COMMIT_DISTANCE) {
                suppressClick = true;
                var toNext = (deltaX < 0) !== isRTL;
                advance(toNext ? 1 : -1);
            }
            restartAutoplay();
        }
        stage.addEventListener('pointerup', onRelease);
        stage.addEventListener('pointercancel', onRelease);

        // --- Broken images (today's placeholder data) fade out of the depth
        // stack instead of showing the browser's native broken-image icon
        // floating in 3D space. ---
        stage.querySelectorAll('img').forEach(function (img) {
            img.addEventListener('error', function () {
                img.closest('.aroma-showcase-card').classList.add('is-broken');
            }, { once: true });
        });

        // --- Toggling the OS reduced-motion setting mid-visit ---
        if (reduceMotionQuery && reduceMotionQuery.addEventListener) {
            reduceMotionQuery.addEventListener('change', function (e) {
                reduceMotion = e.matches;
                MOVE_MS = reduceMotion ? 0 : 650;
                if (reduceMotion) { stopAutoplay(); } else { restartAutoplay(); }
            });
        }
    });
})();
