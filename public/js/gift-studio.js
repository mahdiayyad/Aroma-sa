/**
 * Gift Studio — checkout "Gift Options" step.
 *
 *  1. Is-it-a-gift toggle — show/hide the gift panel, swap the submit label.
 *  2. Greeting card picker — updates the live preview image.
 *  3. Message studio — live character/line counters, to/from/message preview,
 *     message-suggestion picker (fills the textarea from the modal).
 *  4. Signature pad — a plain <canvas> pointer-drawn signature, saved as a
 *     base64 PNG into a hidden input for the server to persist.
 *
 * Loaded only on checkout.gift-options (see @push('scripts') in
 * resources/views/checkout/gift-options.blade.php), so it can assume every
 * element below exists.
 */
(function () {
    'use strict';

    var form = document.getElementById('giftForm');
    if (!form) {
        return;
    }

    /* --- Is this a gift? toggle ------------------------------------------- */
    var panel = document.getElementById('giftPanel');
    var submitLabel = document.getElementById('giftSubmitLabel');
    var toggleRadios = form.querySelectorAll('input[name="is_gift"]');

    function applyToggleState() {
        var isGift = form.querySelector('input[name="is_gift"]:checked').value === '1';
        panel.classList.toggle('d-none', !isGift);
        if (submitLabel) {
            submitLabel.textContent = isGift ? submitLabel.dataset.continueLabel : submitLabel.dataset.skipLabel;
        }
    }

    toggleRadios.forEach(function (radio) {
        radio.addEventListener('change', applyToggleState);
    });

    /* --- Greeting card preview ---------------------------------------------- */
    var previewImage = document.getElementById('giftPreviewImage');
    var previewEmpty = document.getElementById('giftPreviewEmpty');

    function applyCardPreview() {
        var checked = form.querySelector('input[name="greeting_card_id"]:checked');
        var label = checked && document.querySelector('label[for="' + checked.id + '"]');
        var image = label ? label.dataset.image : '';

        if (image) {
            previewImage.src = image;
            previewImage.classList.remove('d-none');
            previewEmpty.classList.add('d-none');
        } else {
            previewImage.src = '';
            previewImage.classList.add('d-none');
            previewEmpty.classList.remove('d-none');
        }
    }

    form.querySelectorAll('input[name="greeting_card_id"]').forEach(function (radio) {
        radio.addEventListener('change', applyCardPreview);
    });

    /* --- Message studio: counters + to/from/message preview ----------------- */
    var messageField = document.getElementById('giftMessage');
    var toField = document.getElementById('giftTo');
    var fromField = document.getElementById('giftFrom');
    var charsRemaining = document.getElementById('charsRemaining');
    var linesRemaining = document.getElementById('linesRemaining');
    var previewTo = document.getElementById('giftPreviewTo');
    var previewFrom = document.getElementById('giftPreviewFrom');
    var previewMessage = document.getElementById('giftPreviewMessage');

    function updateCounters() {
        var maxChars = parseInt(messageField.getAttribute('maxlength'), 10) || 0;
        var maxLines = parseInt(messageField.dataset.maxLines, 10) || 0;
        var value = messageField.value;
        var lines = value === '' ? 0 : value.split('\n').length;

        var charsLeft = Math.max(0, maxChars - value.length);
        var linesLeft = Math.max(0, maxLines - lines);

        charsRemaining.textContent = charsRemaining.dataset.template.replace(':n', charsLeft);
        linesRemaining.textContent = linesRemaining.dataset.template.replace(':n', linesLeft);
        charsRemaining.classList.toggle('is-limit', charsLeft === 0);
        linesRemaining.classList.toggle('is-limit', linesLeft === 0);
    }

    function updatePreviewText(el, template, value) {
        if (!value) {
            el.textContent = '';
            el.classList.add('d-none');
            return;
        }
        el.textContent = template.replace(':name', value);
        el.classList.remove('d-none');
    }

    function updateMessagePreview() {
        updateCounters();
        previewMessage.textContent = messageField.value;
        updatePreviewText(previewTo, previewTo.dataset.template, toField.value.trim());
        updatePreviewText(previewFrom, previewFrom.dataset.template, fromField.value.trim());
    }

    messageField.addEventListener('input', updateMessagePreview);
    toField.addEventListener('input', updateMessagePreview);
    fromField.addEventListener('input', updateMessagePreview);

    /* --- Message suggestions -------------------------------------------------- */
    document.querySelectorAll('.aroma-suggestion-item').forEach(function (item) {
        item.addEventListener('click', function () {
            messageField.value = item.dataset.text;
            updateMessagePreview();

            var modalEl = document.getElementById('suggestionsModal');
            var modal = window.bootstrap && window.bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        });
    });

    /* --- Signature pad --------------------------------------------------------- */
    var canvas = document.getElementById('signatureCanvas');
    var ctx = canvas.getContext('2d');
    var signatureData = document.getElementById('giftSignatureData');
    var signatureThumb = document.getElementById('signatureThumb');
    var signatureBtnLabel = document.getElementById('signatureBtnLabel');
    var previewSignature = document.getElementById('giftPreviewSignature');
    var clearBtn = document.getElementById('signatureClear');
    var saveBtn = document.getElementById('signatureSave');
    var signatureModalEl = document.getElementById('signatureModal');
    var drawing = false;
    var hasDrawn = false;

    function canvasPoint(evt) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        var source = evt.touches && evt.touches.length ? evt.touches[0] : evt;

        return {
            x: (source.clientX - rect.left) * scaleX,
            y: (source.clientY - rect.top) * scaleY,
        };
    }

    function startDraw(evt) {
        drawing = true;
        hasDrawn = true;
        var point = canvasPoint(evt);
        ctx.beginPath();
        ctx.moveTo(point.x, point.y);
        evt.preventDefault();
    }

    function moveDraw(evt) {
        if (!drawing) {
            return;
        }
        var point = canvasPoint(evt);
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#2b2016';
        ctx.lineTo(point.x, point.y);
        ctx.stroke();
        evt.preventDefault();
    }

    function stopDraw() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', moveDraw);
    window.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', moveDraw, { passive: false });
    canvas.addEventListener('touchend', stopDraw);

    function clearCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
    }

    clearBtn.addEventListener('click', clearCanvas);

    // Fresh canvas every time the pad opens — editing means redrawing, not
    // continuing on top of a signature that isn't actually rendered on it
    // (a previously-saved signature lives server-side, not on this canvas).
    if (signatureModalEl) {
        signatureModalEl.addEventListener('shown.bs.modal', clearCanvas);
    }

    saveBtn.addEventListener('click', function () {
        if (hasDrawn) {
            var dataUri = canvas.toDataURL('image/png');
            signatureData.value = dataUri;
            signatureThumb.src = dataUri;
            signatureThumb.classList.remove('d-none');
            previewSignature.src = dataUri;
            previewSignature.classList.remove('d-none');
            signatureBtnLabel.textContent = signatureBtnLabel.dataset.editLabel;
        }

        var modal = window.bootstrap && window.bootstrap.Modal.getInstance(signatureModalEl);
        if (modal) {
            modal.hide();
        }
    });

    /* --- Greeting card large preview modal ----------------------------------- *
     * Populated per-click via Bootstrap's own relatedTarget convention (one
     * shared modal, not one per design) — see .aroma-gift-card-zoom buttons
     * in gift-options.blade.php. Selecting a design from inside the modal
     * checks the matching grid radio and dispatches `change` on it, which is
     * what keeps applyCardPreview() above in sync — no duplicated preview
     * logic between the grid and the modal. */
    var previewModalEl = document.getElementById('giftCardPreviewModal');
    if (previewModalEl) {
        var previewTriggers = Array.prototype.slice.call(document.querySelectorAll('.aroma-gift-card-zoom'));
        var previewImg = document.getElementById('giftCardPreviewLarge');
        var previewName = document.getElementById('giftCardPreviewName');
        var previewSelectBtn = document.getElementById('giftCardPreviewSelect');
        var previewPrevBtn = document.getElementById('giftCardPreviewPrev');
        var previewNextBtn = document.getElementById('giftCardPreviewNext');
        var previewIndex = 0;

        function showPreviewByIndex(i) {
            if (!previewTriggers.length) { return; }
            previewIndex = (i + previewTriggers.length) % previewTriggers.length;
            var t = previewTriggers[previewIndex].dataset;
            previewImg.src = t.cardImage;
            previewImg.alt = t.cardName;
            previewName.textContent = t.cardName;

            var radio = document.getElementById('card-' + t.cardId);
            var alreadySelected = !!(radio && radio.checked);
            previewSelectBtn.textContent = alreadySelected ? previewSelectBtn.dataset.selectedLabel : previewSelectBtn.dataset.selectLabel;
            previewSelectBtn.disabled = alreadySelected;
            previewSelectBtn.dataset.cardId = t.cardId;
        }

        previewModalEl.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            var idx = trigger ? previewTriggers.indexOf(trigger) : -1;
            showPreviewByIndex(idx === -1 ? 0 : idx);
        });

        if (previewPrevBtn) { previewPrevBtn.addEventListener('click', function () { showPreviewByIndex(previewIndex - 1); }); }
        if (previewNextBtn) { previewNextBtn.addEventListener('click', function () { showPreviewByIndex(previewIndex + 1); }); }

        if (previewSelectBtn) {
            previewSelectBtn.addEventListener('click', function () {
                var radio = document.getElementById('card-' + previewSelectBtn.dataset.cardId);
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
                var modalInstance = window.bootstrap && window.bootstrap.Modal.getInstance(previewModalEl);
                if (modalInstance) { modalInstance.hide(); }
            });
        }
    }

    /* --- Init ------------------------------------------------------------------ */
    applyToggleState();
    applyCardPreview();
    updateMessagePreview();
})();
