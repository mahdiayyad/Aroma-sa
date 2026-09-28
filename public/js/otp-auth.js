(function () {
    'use strict';

    var RESEND_COOLDOWN = 60; // seconds
    var SUCCESS_REDIRECT_DELAY = 700; // ms — long enough to actually see the success state

    // One implementation for every phone-verification panel on a page (sign-in,
    // profile "change number") — each root is a [data-otp-panel] element that
    // carries its own send/verify URLs and labels.
    function initPanel(root) {
        var phoneStep = root.querySelector('.js-otp-phone-step');
        var codeStep = root.querySelector('.js-otp-code-step');
        var phoneInput = root.querySelector('.js-intl-phone');
        var sendBtn = root.querySelector('.js-otp-send');
        var verifyBtn = root.querySelector('.js-otp-verify');
        var resendBtn = root.querySelector('.js-otp-resend');
        var changeNumberBtn = root.querySelector('.js-otp-change-number');
        var countdownEl = root.querySelector('.js-otp-countdown');
        var phoneDisplayEl = root.querySelector('.js-otp-entered-phone');
        var mode = root.getAttribute('data-otp-mode') || 'phone';
        var step1ErrorEl = root.querySelector('.js-otp-step1-error');
        var step1ErrorTextEl = root.querySelector('.js-otp-step1-error-text');
        var errorEl = root.querySelector('.js-otp-error');
        var errorTextEl = root.querySelector('.js-otp-error-text');
        var successEl = root.querySelector('.js-otp-success');
        var boxesWrap = root.querySelector('.aroma-otp-boxes');
        var boxes = boxesWrap ? Array.prototype.slice.call(boxesWrap.querySelectorAll('.aroma-otp-box')) : [];
        var hiddenValue = boxesWrap ? boxesWrap.querySelector('.js-otp-value') : null;

        var currentPhone = null;
        var codeStepReady = false;
        var countdownTimer = null;
        var verifyInFlight = false;

        function collectCode() {
            return boxes.map(function (b) { return b.value; }).join('');
        }

        function isComplete() {
            return collectCode().length === boxes.length;
        }

        function syncHiddenAndButton() {
            if (hiddenValue) { hiddenValue.value = collectCode(); }
            if (verifyBtn) { verifyBtn.disabled = !isComplete() || verifyInFlight; }
        }

        function showError(message) {
            if (errorEl && errorTextEl) {
                errorTextEl.textContent = message;
                errorEl.classList.remove('d-none');
            }
            if (successEl) { successEl.classList.add('d-none'); }
            if (boxesWrap) {
                boxesWrap.classList.remove('is-success');
                boxesWrap.classList.add('is-error');
                // Re-triggerable: force reflow so a second consecutive error
                // still restarts the shake instead of silently no-op'ing.
                void boxesWrap.offsetWidth;
                setTimeout(function () { boxesWrap.classList.remove('is-error'); }, 400);
            }
        }

        // Step-1 problems (bad number, wrong password, throttled…) are shown
        // under the first step's own fields — the code step's error line is
        // hidden until a code has been sent.
        function showStep1Error(message) {
            if (step1ErrorEl && step1ErrorTextEl) {
                step1ErrorTextEl.textContent = message;
                step1ErrorEl.classList.remove('d-none');
            }
        }

        function clearFeedback() {
            if (step1ErrorEl) { step1ErrorEl.classList.add('d-none'); }
            if (errorEl) { errorEl.classList.add('d-none'); }
            if (successEl) { successEl.classList.add('d-none'); }
            if (boxesWrap) { boxesWrap.classList.remove('is-error', 'is-success'); }
        }

        function setLoading(btn, loading, loadingText, defaultText) {
            if (!btn) { return; }
            btn.disabled = loading;
            btn.textContent = loading ? loadingText : defaultText;
        }

        function formatTime(totalSeconds) {
            var m = Math.floor(totalSeconds / 60);
            var s = totalSeconds % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function startCountdown() {
            var remaining = RESEND_COOLDOWN;
            var template = root.getAttribute('data-resend-template') || '';
            if (resendBtn) { resendBtn.disabled = true; }
            clearInterval(countdownTimer);

            function tick() {
                if (countdownEl) {
                    countdownEl.textContent = remaining > 0 ? template.replace('%TIME%', formatTime(remaining)) : '';
                }
                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                    if (resendBtn) { resendBtn.disabled = false; }
                    return;
                }
                remaining -= 1;
            }

            tick();
            countdownTimer = setInterval(tick, 1000);
        }

        function getPhoneNumber() {
            if (phoneInput && phoneInput._iti) {
                return phoneInput.value.trim() ? phoneInput._iti.getNumber() : '';
            }
            return phoneInput ? phoneInput.value.trim() : '';
        }

        function resetBoxes() {
            boxes.forEach(function (b) { b.value = ''; b.classList.remove('is-filled'); });
            syncHiddenAndButton();
        }

        function focusBox(index) {
            var box = boxes[index];
            if (box) { box.focus(); }
        }

        // Every first-step input carries data-otp-field="<request field name>"; the
        // phone field keeps going through intl-tel-input so it posts clean E.164.
        function collectFields() {
            var form = new FormData();
            var complete = true;
            root.querySelectorAll('[data-otp-field]').forEach(function (el) {
                var value = (el === phoneInput) ? getPhoneNumber() : el.value;
                if (!value) { complete = false; }
                form.append(el.getAttribute('data-otp-field'), value);
            });
            return complete ? form : null;
        }

        // First field-level validation message Laravel returned, whichever field.
        function firstError(err) {
            if (!err || !err.errors) { return ''; }
            var keys = Object.keys(err.errors);
            return keys.length && err.errors[keys[0]] && err.errors[keys[0]][0] ? err.errors[keys[0]][0] : '';
        }

        function sendCode() {
            var form = collectFields();
            if (!form) { return; }
            var phone = form.get('phone');

            clearFeedback();
            setLoading(sendBtn, true, root.getAttribute('data-sending-text'), root.getAttribute('data-send-text'));

            window.AromaHttp.post(root.getAttribute('data-send-url'), form)
                .then(function (data) {
                    currentPhone = phone;
                    codeStepReady = true;
                    if (phoneDisplayEl) { phoneDisplayEl.textContent = (data && data.display) || phone || ''; }
                    phoneStep.classList.add('d-none');
                    codeStep.classList.remove('d-none');
                    startCountdown();
                    resetBoxes();
                    focusBox(0);
                })
                .catch(function (err) {
                    var message = firstError(err) || (err && err.message) || root.getAttribute('data-network-error');
                    // A failed *resend* happens while the code step is showing.
                    if (codeStepReady) { showError(message); } else { showStep1Error(message); }
                })
                .finally(function () {
                    setLoading(sendBtn, false, root.getAttribute('data-sending-text'), root.getAttribute('data-send-text'));
                });
        }

        function verifyCode() {
            if (verifyInFlight || !isComplete() || !codeStepReady) { return; }

            var code = collectCode();
            verifyInFlight = true;
            clearFeedback();
            setLoading(verifyBtn, true, root.getAttribute('data-verifying-text'), root.getAttribute('data-verify-text'));

            var form = new FormData();
            // Phone panels name the number; email panels don't — the server
            // remembers who passed the password step in the session.
            if (mode === 'phone') { form.append('phone', currentPhone); }
            form.append('code', code);

            window.AromaHttp.post(root.getAttribute('data-verify-url'), form)
                .then(function (data) {
                    if (boxesWrap) { boxesWrap.classList.add('is-success'); }
                    if (successEl) { successEl.classList.remove('d-none'); }
                    // Let the success state actually be seen before leaving.
                    setTimeout(function () { window.location.href = data.redirect; }, SUCCESS_REDIRECT_DELAY);
                })
                .catch(function (err) {
                    verifyInFlight = false;
                    showError((err && err.message) || root.getAttribute('data-network-error'));
                    resetBoxes();
                    focusBox(0);
                    setLoading(verifyBtn, false, root.getAttribute('data-verifying-text'), root.getAttribute('data-verify-text'));
                });
        }

        // ---- Digit-box behaviour -------------------------------------------
        boxes.forEach(function (box, index) {
            // A click/focus on a box with an existing digit selects it, so
            // typing immediately replaces it (native maxlength=1 otherwise
            // silently blocks new input once a character is already there).
            box.addEventListener('focus', function () { box.select(); });
            box.addEventListener('click', function () { box.select(); });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusBox(index - 1);
                    return;
                }
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusBox(index + 1);
                    return;
                }
                if (e.key === 'Backspace') {
                    e.preventDefault();
                    if (box.value) {
                        box.value = '';
                        box.classList.remove('is-filled');
                        syncHiddenAndButton();
                    } else if (index > 0) {
                        var prev = boxes[index - 1];
                        prev.value = '';
                        prev.classList.remove('is-filled');
                        prev.focus();
                        syncHiddenAndButton();
                    }
                    return;
                }
                if (e.key === 'Delete') {
                    e.preventDefault();
                    box.value = '';
                    box.classList.remove('is-filled');
                    syncHiddenAndButton();
                    return;
                }
                // Best-effort block of obviously-non-digit printable keys;
                // the 'input' handler below is the authoritative filter
                // (mobile keyboards don't always report a useful e.key).
                if (e.key.length === 1 && !/[0-9]/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    e.preventDefault();
                }
            });

            box.addEventListener('input', function () {
                box.value = box.value.replace(/[^0-9]/g, '').slice(-1);
                box.classList.toggle('is-filled', box.value !== '');

                if (box.value && boxes[index + 1]) {
                    focusBox(index + 1);
                }

                syncHiddenAndButton();

                if (isComplete()) {
                    verifyCode();
                }
            });

            box.addEventListener('paste', function (e) {
                var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                if (!pasted) { return; }
                e.preventDefault();
                boxes.forEach(function (b, i) {
                    b.value = pasted[i] || '';
                    b.classList.toggle('is-filled', b.value !== '');
                });
                syncHiddenAndButton();
                focusBox(Math.min(pasted.length, boxes.length - 1));
                if (isComplete()) { verifyCode(); }
            });
        });

        root.querySelectorAll('[data-otp-field]').forEach(function (el) {
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); sendCode(); }
            });
        });
        if (sendBtn) { sendBtn.addEventListener('click', sendCode); }
        if (resendBtn) { resendBtn.addEventListener('click', sendCode); }
        if (verifyBtn) { verifyBtn.addEventListener('click', verifyCode); }
        if (changeNumberBtn) {
            changeNumberBtn.addEventListener('click', function () {
                clearInterval(countdownTimer);
                codeStepReady = false;
                codeStep.classList.add('d-none');
                phoneStep.classList.remove('d-none');
                clearFeedback();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.AromaHttp === 'undefined') { return; }
        document.querySelectorAll('[data-otp-panel]').forEach(initPanel);
    });
})();
