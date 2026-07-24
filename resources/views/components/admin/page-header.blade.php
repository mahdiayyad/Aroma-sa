@props(['title', 'subtitle' => null, 'back' => null])

<div class="admin-page-header">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="admin-back-link"><i class="bi bi-arrow-{{ (($direction ?? 'ltr') === 'rtl') ? 'right' : 'left' }}-short"></i>{{ __('admin.back') }}</a>
        @endif
        <h2 class="admin-page-title">{{ $title }}</h2>
        @if ($subtitle)<p class="admin-page-subtitle">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="admin-page-actions">{{ $actions }}</div>
    @endisset
</div>
