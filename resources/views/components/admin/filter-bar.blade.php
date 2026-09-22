@props(['chips' => []])

{{--
    The search/select toolbar above an admin list table, dressed as its own
    surface instead of floating loose on the page background, plus a row of
    dismissible chips summarising whatever filters are currently applied — a
    port of the storefront's own .aroma-filter-chip pattern (see
    public/css/components/category-page.css), keyed off --admin-* tokens.

    Usage: wrap the page's existing filter fields (the search input and
    <select>s it already builds) in the slot — nothing about those fields or
    the surrounding GET form/admin-autofilter behaviour changes. Pass
    `:chips="[<query key> => <human label>, ...]"` for whichever filters are
    currently non-default; each chip removes just that one query key, and a
    "clear all" chip appears once two or more are active.
--}}

<div class="admin-filter-bar">
    <form method="get" class="admin-toolbar admin-autofilter">
        <span class="admin-filter-bar-label"><i class="bi bi-funnel"></i>{{ __('admin.common.filters') }}</span>
        {{ $slot }}
        <noscript><button class="admin-btn admin-btn-outline btn-sm">{{ __('admin.common.apply') }}</button></noscript>
    </form>

    @if (count($chips))
        <div class="admin-filter-chips">
            @foreach ($chips as $key => $label)
                @php($remaining = collect(request()->except($key))->filter(fn ($v) => $v !== null && $v !== '')->all())
                <a href="{{ $remaining ? request()->url().'?'.http_build_query($remaining) : request()->url() }}"
                   class="admin-filter-chip">{{ $label }}<i class="bi bi-x" aria-hidden="true"></i></a>
            @endforeach
            @if (count($chips) > 1)
                <a href="{{ request()->url() }}" class="admin-filter-chip admin-filter-chip-clear">
                    {{ __('admin.common.reset') }}<i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
            @endif
        </div>
    @endif
</div>
