@props(['label', 'value', 'icon' => 'bi-graph-up', 'tone' => 'brown', 'hint' => null])

<div class="admin-stat admin-stat-{{ $tone }}">
    <div class="admin-stat-icon"><i class="bi {{ $icon }}"></i></div>
    <div class="admin-stat-body">
        <div class="admin-stat-label">{{ $label }}</div>
        <div class="admin-stat-value">{{ $value }}</div>
        @if ($hint)<div class="admin-stat-hint">{{ $hint }}</div>@endif
    </div>
</div>
