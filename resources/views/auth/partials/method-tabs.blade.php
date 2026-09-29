{{--
    Phone / email segmented tabs shared by login and register — both end in the
    same verified-code step, just reached a different way. Auto-wired by
    otp-auth.js's initAuthTabs() via [data-auth-tabs] / data-pane, so no inline
    script is needed per page.

    Required: $phonePaneId, $emailPaneId. Optional: $emailFirst (bool, honours
    ?method=email on either page), $name (radio group name — must be unique
    per page since login and register can theoretically share no DOM, but two
    tab groups on one page would still need distinct names).
--}}
@php($emailFirst = $emailFirst ?? (request()->query('method') === 'email'))
@php($tabName = $name ?? 'authMethod')

<div class="aroma-segmented aroma-segmented-block" role="tablist" data-auth-tabs>
    <input type="radio" class="btn-check" name="{{ $tabName }}" id="{{ $tabName }}Phone"
           data-pane="#{{ $phonePaneId }}" {{ $emailFirst ? '' : 'checked' }}>
    <label class="aroma-segmented-option" for="{{ $tabName }}Phone">{{ __('email_auth.tab_phone') }}</label>

    <input type="radio" class="btn-check" name="{{ $tabName }}" id="{{ $tabName }}Email"
           data-pane="#{{ $emailPaneId }}" {{ $emailFirst ? 'checked' : '' }}>
    <label class="aroma-segmented-option" for="{{ $tabName }}Email">{{ __('email_auth.tab_email') }}</label>
</div>
