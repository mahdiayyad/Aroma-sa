/**
 * Aroma AI concierge.
 *
 * Vanilla, dependency-free and progressive: without JS the widget simply never
 * opens and the WhatsApp link still works as a plain anchor.
 *
 * Handles: open/close with focus management, message send + typing indicator,
 * suggested questions, conversation reset, and the scroll-revealed WhatsApp FAB.
 */
(function () {
    'use strict';

    var root = document.getElementById('aromaAssistant');
    if (!root) { return; }

    var panel       = document.getElementById('aromaChatPanel');
    var toggle      = document.getElementById('aromaChatToggle');
    var closeBtn    = document.getElementById('aromaChatClose');
    var resetBtn    = document.getElementById('aromaChatReset');
    var log         = document.getElementById('aromaChatLog');
    var form        = document.getElementById('aromaChatForm');
    var input       = document.getElementById('aromaChatInput');
    var suggestions = document.getElementById('aromaSuggestions');
    var sendBtn     = form ? form.querySelector('.aroma-chat-send') : null;

    var endpoint  = root.getAttribute('data-endpoint');
    var resetUrl  = root.getAttribute('data-reset');
    var errorText = root.getAttribute('data-error') || 'Something went wrong.';
    var csrf      = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    var isOpen = false;
    var pending = false;
    var closeTimer = null;

    /* ---------------------------------------------------------------- open/close */

    function openPanel() {
        if (isOpen) { return; }
        isOpen = true;
        clearTimeout(closeTimer);

        panel.hidden = false;
        // Next frame so the entrance transition actually runs.
        requestAnimationFrame(function () {
            root.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            scrollToLatest();
            if (input) { input.focus(); }
        });
    }

    function closePanel(returnFocus) {
        if (!isOpen) { return; }
        isOpen = false;

        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');

        // Keep it in the DOM until the exit transition finishes.
        closeTimer = setTimeout(function () { panel.hidden = true; }, 260);
        if (returnFocus) { toggle.focus(); }
    }

    toggle.addEventListener('click', function () {
        isOpen ? closePanel(true) : openPanel();
    });
    if (closeBtn) { closeBtn.addEventListener('click', function () { closePanel(true); }); }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) { closePanel(true); }
    });

    /* ------------------------------------------------------------------ messages */

    function addMessage(text, who) {
        var el = document.createElement('div');
        el.className = 'aroma-msg aroma-msg-' + (who === 'me' ? 'me' : 'bot');
        el.textContent = text;
        log.appendChild(el);
        scrollToLatest();
        return el;
    }

    function showTyping() {
        var el = document.createElement('div');
        el.className = 'aroma-msg aroma-msg-bot aroma-typing';
        el.setAttribute('aria-label', 'typing');
        el.innerHTML = '<span></span><span></span><span></span>';
        log.appendChild(el);
        scrollToLatest();
        return el;
    }

    function scrollToLatest() {
        if (log) { log.scrollTop = log.scrollHeight; }
    }

    function setPending(state) {
        pending = state;
        if (sendBtn) { sendBtn.disabled = state; }
        if (input) { input.disabled = state; }
    }

    function context() {
        return {
            title: root.getAttribute('data-page-title') || '',
            path: root.getAttribute('data-page-path') || '',
            product: root.getAttribute('data-product') || ''
        };
    }

    function send(text) {
        var message = (text || '').trim();
        if (!message || pending) { return; }

        if (suggestions) { suggestions.hidden = true; }
        addMessage(message, 'me');
        input.value = '';
        autoGrow();
        setPending(true);

        var typing = showTyping();

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: message, context: context() })
        })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (data) {
                typing.remove();
                addMessage((data && data.reply) || errorText, 'bot');
            })
            .catch(function () {
                typing.remove();
                addMessage(errorText, 'bot');
            })
            .finally(function () {
                setPending(false);
                if (isOpen && input) { input.focus(); }
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            send(input.value);
        });
    }

    // Enter sends, Shift+Enter makes a new line.
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send(input.value);
            }
        });
        input.addEventListener('input', autoGrow);
    }

    function autoGrow() {
        if (!input) { return; }
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    }

    /* --------------------------------------------------------------- suggestions */

    if (suggestions) {
        suggestions.addEventListener('click', function (e) {
            var chip = e.target.closest('[data-suggestion]');
            if (chip) { send(chip.textContent); }
        });
    }

    /* --------------------------------------------------------------------- reset */

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            fetch(resetUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).finally(function () {
                // Keep the greeting and quick-action menu, drop the conversation.
                Array.prototype.slice.call(log.children).forEach(function (child) {
                    if (!child.classList.contains('aroma-chat-greeting') &&
                        !child.classList.contains('aroma-quick-actions')) {
                        child.remove();
                    }
                });
                if (suggestions) { suggestions.hidden = false; }
                if (input) { input.focus(); }
            });
        });
    }

    /* ----------------------------------------------- WhatsApp reveal on scroll */

    var revealAt = Math.min(600, Math.round(window.innerHeight * 0.6));
    var ticking = false;

    function syncWhatsApp() {
        ticking = false;
        root.classList.toggle('show-wa', window.pageYOffset > revealAt);
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(syncWhatsApp);
        }
    }, { passive: true });
    syncWhatsApp();

    /* --------------------------- keep clear of the sticky mobile add-to-cart bar */

    if (document.querySelector('.aroma-mobile-cart-bar')) {
        root.classList.add('has-bottom-bar');
    }

    scrollToLatest();
})();
