/* Aroma Admin — jQuery interactions (progressive enhancement). */
(function ($) {
    'use strict';

    $(function () {
        // Mobile sidebar toggle + backdrop.
        var $sidebar = $('#adminSidebar');
        var $backdrop = $('#adminBackdrop');
        function closeSidebar() { $sidebar.removeClass('open'); $backdrop.removeClass('show'); }
        $('#adminMenuToggle').on('click', function () { $sidebar.toggleClass('open'); $backdrop.toggleClass('show'); });
        $backdrop.on('click', closeSidebar);

        // Confirm destructive actions: <form data-confirm="Delete this?">
        $(document).on('submit', 'form[data-confirm]', function (e) {
            if (!window.confirm($(this).data('confirm'))) { e.preventDefault(); }
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
