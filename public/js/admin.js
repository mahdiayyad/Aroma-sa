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

        // Confirm destructive actions: <form data-confirm="Delete this?"> —
        // a brand-themed SweetAlert2 dialog instead of the native browser
        // confirm(). Falls back to the native one if the CDN failed to load.
        $(document).on('submit', 'form[data-confirm]', function (e) {
            var form = this;
            var message = $(form).data('confirm');

            if (!window.Swal) {
                if (!window.confirm(message)) { e.preventDefault(); }
                return;
            }

            e.preventDefault();
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
                if (result.isConfirmed) { form.submit(); }
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
