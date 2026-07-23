/**
 * Initializes Select2 on every .form-select, matching the brand theme
 * defined in css/components/select2.css. RTL support follows the page's
 * dir attribute so the dropdown/arrow mirror correctly in Arabic.
 */
$(function () {
    var isRtl = document.documentElement.getAttribute('dir') === 'rtl';

    $('.form-select').each(function () {
        $(this).select2({
            theme: 'default',
            dir: isRtl ? 'rtl' : 'ltr',
            width: '100%',
            minimumResultsForSearch: 6,
            placeholder: $(this).data('placeholder') || '',
            allowClear: $(this).data('allow-clear') === true,
        });
    });
});
