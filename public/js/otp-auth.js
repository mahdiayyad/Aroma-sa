(function () {
    'use strict';

    var RESEND_COOLDOWN = 60; // seconds
    var SUCCESS_REDIRECT_DELAY = 700; // ms — long enough to actually see the success state

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('otpLoginPanel');
        if (!root || typeof window.AromaHttp === 'undefined') { return; }

        var phoneStep = root.querySelector('.js-otp-phone-step');
        var codeStep = root.querySelector('.js-otp-code-step');
        var phoneInput = root.querySelector('.js-intl-phone');
        var sendBtn = root.querySelector('.js-otp-send');
        var verifyBtn = root.querySelector('.js-otp-verify');
        var resendBtn = root.querySelector('.js-otp-resend');
        var changeNumberBtn = root.querySelector('.js-otp-change-number');
        var countdownEl = root.querySelector('.js-otp-countdown');
        var phoneDisplayEl = root.querySelector('.js-otp-entered-phone');
        var errorEl = root.querySelector('.js-otp-error');
        var errorTextEl = root.querySelector('.js-otp-error-text');
        var successEl = root.querySelector('.js-otp-success');
        var boxesWrap = root.querySelector('.aroma-otp-boxes');
        var boxes = boxesWrap ? Array.prototype.slice.call(boxesWrap.querySelectorAll('.aroma-otp-box')) : [];
        var hiddenValue = boxesWrap ? boxesWrap.querySelector('.js-otp-value') : null;

        var currentPhone = null;
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

        function clearFeedback() {
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

        function sendCode() {
            var phone = getPhoneNumber();
            if (!phone) { return; }

            clearFeedback();
            setLoading(sendBtn, true, root.getAttribute('data-sending-text'), root.getAttribute('data-send-text'));

            var form = new FormData();
            form.append('phone', phone);

            window.AromaHttp.post(root.getAttribute('data-send-url'), form)
                .then(function () {
                    currentPhone = phone;
                    if (phoneDisplayEl) { phoneDisplayEl.textContent = phone; }
                    phoneStep.classList.add('d-none');
                    codeStep.classList.remove('d-none');
                    startCountdown();
                    resetBoxes();
                    focusBox(0);
                })
                .catch(function (err) {
                    showError((err && err.errors && err.errors.phone && err.errors.phone[0]) || root.getAttribute('data-network-error'));
                })
                .finally(function () {
                    setLoading(sendBtn, false, root.getAttribute('data-sending-text'), root.getAttribute('data-send-text'));
                });
        }

        function verifyCode() {
            if (verifyInFlight || !isComplete() || !currentPhone) { return; }

            var code = collectCode();
            verifyInFlight = true;
            clearFeedback();
            setLoading(verifyBtn, true, root.getAttribute('data-verifying-text'), root.getAttribute('data-verify-text'));

            var form = new FormData();
            form.append('phone', currentPhone);
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

        if (sendBtn) { sendBtn.addEventListener('click', sendCode); }
        if (resendBtn) { resendBtn.addEventListener('click', sendCode); }
        if (verifyBtn) { verifyBtn.addEventListener('click', verifyCode); }
        if (changeNumberBtn) {
            changeNumberBtn.addEventListener('click', function () {
                clearInterval(countdownTimer);
                codeStep.classList.add('d-none');
                phoneStep.classList.remove('d-none');
                clearFeedback();
            });
        }
    });
})();
