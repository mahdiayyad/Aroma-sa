/* Aroma Admin — jQuery interactions (progressive enhancement). */
(function ($) {
    'use strict';

    $(function () {
        // Success flash → toast. Server-side redirects still set the
        // session('status') flash the usual Laravel way; this just renders it
        // as a brand-themed SweetAlert2 toast instead of a banner.
        var flashSuccess = document.body.getAttribute('data-flash-success');
        if (flashSuccess && window.Swal) {
            Swal.fire({
                toast: true,
                position: document.documentElement.getAttribute('dir') === 'rtl' ? 'top-start' : 'top-end',
                icon: 'success',
                title: flashSuccess,
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                customClass: { popup: 'aroma-swal-popup' }
            });
        }

        // Brand-themed tooltips (see .tooltip overrides in aroma.css) —
        // replaces the native title="" hover tooltip on icon-only actions.
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                window.bootstrap.Tooltip.getOrCreateInstance(el);
            }
        });

        // Mobile sidebar toggle + backdrop.
        var $sidebar = $('#adminSidebar');
        var $backdrop = $('#adminBackdrop');
        function closeSidebar() { $sidebar.removeClass('open'); $backdrop.removeClass('show'); }
        $('#adminMenuToggle').on('click', function () { $sidebar.toggleClass('open'); $backdrop.toggleClass('show'); });
        $backdrop.on('click', closeSidebar);

        // Shared destructive-action confirm dialog — a brand-themed SweetAlert2
        // dialog instead of the native browser confirm(). Falls back to the
        // native confirm() if the CDN failed to load. `onConfirmed` runs only
        // after the user accepts.
        function confirmDestructive(message, onConfirmed) {
            if (!window.Swal) {
                if (window.confirm(message)) { onConfirmed(); }
                return;
            }

            var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

            Swal.fire({
                title: message,
                icon: 'warning',
                showCancelButton: true,
                focusCancel: true,
                reverseButtons: isRtl,
                confirmButtonText: document.body.getAttribute('data-confirm-yes') || 'Yes',
                cancelButtonText: document.body.getAttribute('data-confirm-cancel') || 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'aroma-swal-popup',
                    confirmButton: 'aroma-swal-btn aroma-swal-btn-danger',
                    cancelButton: 'aroma-swal-btn aroma-swal-btn-outline'
                }
            }).then(function (result) {
                if (result.isConfirmed) { onConfirmed(); }
            });
        }

        function toast(icon, message) {
            if (!window.Swal || !message) { return; }
            Swal.fire({
                toast: true,
                position: document.documentElement.getAttribute('dir') === 'rtl' ? 'top-start' : 'top-end',
                icon: icon,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                customClass: { popup: 'aroma-swal-popup' }
            });
        }

        // <form data-confirm="Delete this?"> — plain form submits, confirmed first.
        $(document).on('submit', 'form[data-confirm]', function (e) {
            var form = this;
            e.preventDefault();
            confirmDestructive($(form).data('confirm'), function () { form.submit(); });
        });

        // Product media manager: <button class="js-delete-image" data-url
        // data-confirm> — no nested <form> possible inside the product edit
        // form, so this confirms then deletes via AJAX and fades the thumbnail out.
        $(document).on('click', '.js-delete-image', function (e) {
            e.preventDefault();
            var btn = this;
            var item = $(btn).closest('.admin-image-item');
            var grid = item.closest('.admin-image-grid');
            var wasPrimary = item.find('.admin-image-primary-badge').length > 0;

            confirmDestructive($(btn).data('confirm'), function () {
                item.addClass('is-removing');

                $.ajax({
                    url: $(btn).data('url'),
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                }).done(function (data) {
                    // The server promotes the lowest-sort_order remaining
                    // image to primary when the deleted one was it — DOM
                    // order already matches sort_order, so mirror it here
                    // rather than waiting for a reload.
                    if (wasPrimary) {
                        var next = grid.find('.admin-image-item').not(item).first();
                        if (next.length) {
                            $('<span class="admin-image-primary-badge"></span>').text(grid.data('primary-label')).appendTo(next);
                        }
                    }
                    item.fadeOut(150, function () { $(this).remove(); });
                    toast('success', data && data.message);
                }).fail(function () {
                    item.removeClass('is-removing');
                    toast('error', document.body.getAttribute('data-error-generic') || 'Something went wrong.');
                });
            });
        });

        // Image upload preview: <input type="file" data-preview="#target">
        $(document).on('change', 'input[type="file"][data-preview]', function () {
            var target = $($(this).data('preview'));
            var files = this.files;
            if (!files || !files.length) { return; }
            target.empty();
            Array.prototype.forEach.call(files, function (file) {
                if (!/^image\//.test(file.type)) { return; }
                var reader = new FileReader();
                reader.onload = function (ev) {
                    $('<img>').attr('src', ev.target.result).addClass('admin-thumb me-2 mb-2').appendTo(target);
                };
                reader.readAsDataURL(file);
            });
        });

        // Live slug preview from the EN name: [data-slug-source] -> [data-slug-preview]
        $(document).on('input', '[data-slug-source]', function () {
            var slug = $(this).val().toString().toLowerCase()
                .replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-');
            $('[data-slug-preview]').text(slug || '—');
        });

        // Submit-on-change for filter selects/search (.admin-autofilter form).
        $(document).on('change', '.admin-autofilter select', function () { $(this).closest('form').submit(); });
    });
})(jQuery);
