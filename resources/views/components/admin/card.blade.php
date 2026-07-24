@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'admin-card']) }}>
    @if ($title || isset($head))
        <div class="admin-card-head">
            @if ($title)<h3 class="admin-card-title">{{ $title }}</h3>@endif
            @isset($head){{ $head }}@endisset
        </div>
    @endif
    <div class="{{ $padding ? 'admin-card-body' : '' }}">
        {{ $slot }}
    </div>
</div>
