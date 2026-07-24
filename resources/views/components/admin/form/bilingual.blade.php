@props([
    'name',
    'label',
    'translations' => [],   // ['ar' => '...', 'en' => '...'] (e.g. $model->getTranslations('name'))
    'type' => 'input',      // input | textarea
    'required' => false,
    'rows' => 3,
])

<div class="admin-field">
    <label class="admin-label">{{ $label }}@if ($required)<span class="admin-req">*</span>@endif</label>

    <div class="admin-bilingual">
        @foreach (['en' => 'English', 'ar' => 'العربية'] as $loc => $locLabel)
            @php($val = old($name.'.'.$loc, $translations[$loc] ?? ''))
            <div class="admin-bilingual-field" @if ($loc === 'ar') dir="rtl" @endif>
                <span class="admin-bilingual-tag">{{ $locLabel }}</span>
                @if ($type === 'textarea')
                    <textarea name="{{ $name }}[{{ $loc }}]" rows="{{ $rows }}"
                              class="admin-input @error($name.'.'.$loc) is-invalid @enderror">{{ $val }}</textarea>
                @else
                    <input type="text" name="{{ $name }}[{{ $loc }}]" value="{{ $val }}"
                           class="admin-input @error($name.'.'.$loc) is-invalid @enderror">
                @endif
                @error($name.'.'.$loc)<div class="admin-error">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
</div>
