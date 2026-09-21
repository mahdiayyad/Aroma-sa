/**
 * Live progress for an active product import run (admin/products/import/show).
 *
 * Polls the run's status endpoint, moves the progress bar, and reloads the page
 * once the run reaches a state that has its own screen (ready for review,
 * finished, failed…), so the review/confirm UI is rendered by the server.
 */
(function () {
    'use strict';

    var card = document.getElementById('importStatusCard');
    if (!card || card.getAttribute('data-active') !== '1') { return; }

    var url = card.getAttribute('data-status-url');
    var initialStatus = card.getAttribute('data-status');
    var bar = document.getElementById('importProgressBar');
    var text = document.getElementById('importProgressText');
    var phaseLabels = {
        validate: card.getAttribute('data-phase-validate'),
        apply: card.getAttribute('data-phase-apply')
    };
    var progressTpl = card.getAttribute('data-progress-text') || ':processed / :total';
    var failures = 0;

    function render(data) {
        if (bar) {
            bar.style.width = Math.max(data.percent, 3) + '%';
            bar.parentNode.setAttribute('aria-valuenow', data.percent);
        }
        if (text && data.status !== 'queued') {
            var label = phaseLabels[data.phase] || '';
            var counts = data.total > 0 ? ' — ' + progressTpl.replace(':processed', data.processed).replace(':total', data.total) : '';
            text.textContent = label + counts;
        }
    }

    function poll() {
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.json();
            })
            .then(function (data) {
                failures = 0;
                render(data);

                // Every state change has its own server-rendered screen (queued hint, review,
                // errors, summary), so a change — or the run finishing — simply reloads the page.
                if (!data.active || data.status !== initialStatus) {
                    window.location.reload();
                    return;
                }

                setTimeout(poll, 1500);
            })
            .catch(function () {
                // Transient network/server hiccup: back off, give up quietly after a while.
                failures += 1;
                if (failures < 20) { setTimeout(poll, Math.min(1500 * failures, 10000)); }
            });
    }

    setTimeout(poll, 1000);
})();
