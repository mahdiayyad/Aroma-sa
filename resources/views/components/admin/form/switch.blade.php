@props(['name', 'label', 'checked' => false])

<label class="admin-switch mb-2 d-flex">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="form-check-input mt-0" {{ old($name, $checked) ? 'checked' : '' }}>
    <span>{{ $label }}</span>
</label>
