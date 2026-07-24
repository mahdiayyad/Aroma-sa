@props(['message', 'icon' => 'bi-inbox', 'action' => null, 'actionLabel' => null])

<div class="admin-empty">
    <i class="bi {{ $icon }}"></i>
    <p>{{ $message }}</p>
    @if ($action)
        <a href="{{ $action }}" class="btn admin-btn admin-btn-primary btn-sm">{{ $actionLabel }}</a>
    @endif
</div>
