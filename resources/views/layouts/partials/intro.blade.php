{{-- Brand loading animation.
     The logotype is revealed left-to-right so the letters appear in order, then
     the slogan settles in beneath it, then the curtain lifts.

     Assets come from `php artisan aroma:prepare-logo` (transparent PNGs). Until
     those exist we fall back to the full mark, then to the embossed logo shot,
     so the page never shows a broken image. --}}
@php
    $wordmark = public_path('images/brand/aroma-wordmark.png');
    $slogan   = public_path('images/brand/aroma-slogan.png');
    $mark     = public_path('images/brand/aroma-logo-mark.png');

    $hasSplit = is_file($wordmark) && is_file($slogan);
    $hasMark  = is_file($mark);
@endphp

<div class="aroma-intro" id="aromaIntro" role="presentation" aria-hidden="true">
    <div class="aroma-intro-stage">
        @if ($hasSplit)
            <div class="aroma-intro-word">
                <img src="{{ asset('images/brand/aroma-wordmark.png') }}" alt="{{ $brand['name'] ?? 'Aroma' }}" decoding="async">
            </div>
            <div class="aroma-intro-tag">
                <img src="{{ asset('images/brand/aroma-slogan.png') }}" alt="{{ $brand['tagline'] ?? 'Awaken your Senses' }}" decoding="async">
            </div>
        @elseif ($hasMark)
            <div class="aroma-intro-word">
                <img src="{{ asset('images/brand/aroma-logo-mark.png') }}" alt="{{ $brand['name'] ?? 'Aroma' }}" decoding="async">
            </div>
        @else
            {{-- No transparent assets yet — reveal the logotype set in the brand font. --}}
            <div class="aroma-intro-word">
                <span class="aroma-intro-fallback">{{ $brand['name'] ?? 'Aroma' }}</span>
            </div>
            <div class="aroma-intro-tag">
                <span class="aroma-intro-fallback-tag aroma-script">{{ $brand['tagline'] ?? 'Awaken your Senses' }}</span>
            </div>
        @endif
    </div>
</div>
<script>
    (function () {
        var el = document.getElementById('aromaIntro');
        if (!el) { return; }

        // Show only on the shopper's first-ever visit, not on every new tab.
        // localStorage (not sessionStorage) is what makes that true: sessionStorage
        // is scoped per-tab, so a new tab counts as a fresh session and would
        // still replay the intro every time.
        try {
            if (localStorage.getItem('aromaIntroSeen')) {
                el.parentNode.removeChild(el);
                return;
            }
            localStorage.setItem('aromaIntroSeen', '1');
        } catch (e) { /* storage blocked (private mode, etc.) — just play the intro */ }

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.documentElement.classList.add('aroma-intro-lock');

        function lift() {
            el.classList.add('is-done');
            document.documentElement.classList.remove('aroma-intro-lock');
            setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 650);
        }

        var started = false;
        function start() {
            if (started) { return; }
            started = true;
            el.classList.add('is-ready');           // ← animation begins here
            setTimeout(lift, reduce ? 450 : 2150);  // hold, then reveal the page
        }

        // Only start once the logo artwork has decoded, otherwise a slow image
        // arrives after the reveal has already played and simply pops into view.
        var images = Array.prototype.slice.call(el.querySelectorAll('img'));

        if (!images.length) {
            start();
        } else {
            var remaining = images.length;
            var settle = function () { if (--remaining <= 0) { start(); } };

            images.forEach(function (img) {
                if (img.complete && img.naturalWidth > 0) {
                    settle();
                } else {
                    img.addEventListener('load', settle, { once: true });
                    img.addEventListener('error', settle, { once: true });
                }
            });

            // Never hold the page hostage to a slow network.
            setTimeout(start, 1600);
        }
    })();
</script>
