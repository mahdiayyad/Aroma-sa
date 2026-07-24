@props(['label' => null, 'name' => null, 'required' => false, 'hint' => null])

<div {{ $attributes->merge(['class' => 'admin-field']) }}>
    @if ($label)
        <label class="admin-label">{{ $label }}@if ($required)<span class="admin-req">*</span>@endif</label>
    @endif

    {{ $slot }}

    @if ($hint)<div class="admin-hint">{{ $hint }}</div>@endif

    @if ($name && $errors->has($name))
        <div class="admin-error">{{ $errors->first($name) }}</div>
    @endif
</div>
