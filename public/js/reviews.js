(function () {
    'use strict';

    // Live character counter for the review textarea — the only bit of
    // interactive behaviour the simplified review form needs.
    document.addEventListener('DOMContentLoaded', function () {
        var textarea = document.querySelector('.js-review-body');
        var counter = document.querySelector('.js-review-char-count');
        if (!textarea || !counter) { return; }

        var max = parseInt(counter.getAttribute('data-max'), 10) || 1000;

        function render() {
            counter.textContent = textarea.value.length + ' / ' + max;
        }

        textarea.addEventListener('input', render);
        render();
    });
})();
