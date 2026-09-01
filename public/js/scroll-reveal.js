/* ============================================================================
   Aroma — scroll-reveal
   Adds .is-revealed to .aroma-reveal elements as they enter the viewport;
   animations.css owns the actual hidden/visible visual states and timing —
   this file only decides *when* each element flips.

   Progressive enhancement: the hidden pre-reveal CSS state is scoped to
   html.js-reveal-ready, added by an early inline <head> script in
   layouts/app.blade.php (only when IntersectionObserver exists) — so a
   missing/blocked/slow-loading copy of *this* file just leaves every
   .aroma-reveal element at its normal, fully visible state. This file is
   the "wire it up" half, not the "keep content safe" half.
   ========================================================================== */
(function () {
    if (!('IntersectionObserver' in window)) { return; }

    var els = document.querySelectorAll('.aroma-reveal');
    if (!els.length) { return; }

    // Per-group stagger: elements sharing the nearest [data-reveal-group]
    // ancestor (typically a grid/row wrapper) fan in one after another
    // instead of popping in as a single flat block. Elements with no such
    // ancestor each get their own implicit one-item group (no stagger).
    var groupCounters = {};
    var STAGGER_MS = 70;
    var STAGGER_CAP_MS = 420; // long grids stop adding delay past this, so a 20-card grid doesn't take seconds to finish revealing

    els.forEach(function (el, i) {
        var groupEl = el.closest('[data-reveal-group]');
        var key = groupEl ? groupEl.dataset.revealGroup : 'solo-' + i;
        var index = groupCounters[key] || 0;
        groupCounters[key] = index + 1;
        el.style.transitionDelay = Math.min(index * STAGGER_MS, STAGGER_CAP_MS) + 'ms';
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) { return; }
            entry.target.classList.add('is-revealed');
            observer.unobserve(entry.target);
        });
    }, {
        threshold: 0.15,
        rootMargin: '0px 0px -40px 0px', // reveal a little before the element's bottom edge fully clears the viewport
    });

    els.forEach(function (el) { observer.observe(el); });
})();
