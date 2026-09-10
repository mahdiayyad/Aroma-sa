@extends('admin.layouts.master')

@php($editing = $option->exists)
@section('title', ($editing ? __('admin.product_options.edit') : __('admin.product_options.new')).' — '.$product->name)
@section('page_title', $editing ? __('admin.product_options.edit') : __('admin.product_options.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $option->label : __('admin.product_options.new')" :subtitle="$product->name"
        :back="route('admin.products.options.index', $product)" />

    <form method="post"
          action="{{ $editing ? route('admin.options.update', $option) : route('admin.products.options.store', $product) }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        @php($rows = old('values', $editing ? $option->values->map(fn ($v) => [
            'label' => $v->getTranslations('label'),
            'price_delta' => rtrim(rtrim((string) $v->price_delta, '0'), '.') ?: '0',
            'is_active' => $v->is_active,
            'sort_order' => $v->sort_order,
        ])->all() : [
            ['label' => ['ar' => '', 'en' => ''], 'price_delta' => '0', 'is_active' => true, 'sort_order' => 0],
            ['label' => ['ar' => '', 'en' => ''], 'price_delta' => '0', 'is_active' => true, 'sort_order' => 1],
        ]))
        @php($defaultSearch = $editing ? $option->values->search(fn ($v) => $v->is_default) : false)
        @php($defaultIndex = old('default_index', $defaultSearch === false ? '' : (string) $defaultSearch))

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.product_options.option')">
                    <x-admin.form.bilingual name="label" :label="__('admin.product_options.label')"
                        :translations="$option->getTranslations('label')" required />

                    <x-admin.form.group :label="__('admin.product_options.key')" name="key" :hint="__('admin.product_options.key_hint')">
                        <input type="text" name="key" value="{{ old('key', $option->key) }}" class="admin-input" placeholder="closure_style">
                    </x-admin.form.group>
                </x-admin.card>

                <x-admin.card :title="__('admin.product_options.values')">
                    <div class="admin-hint mb-2">{{ __('admin.product_options.values_hint') }}</div>
                    <div class="admin-table-wrap">
                        <table class="admin-table" id="optionValues">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.product_options.value_en') }}</th>
                                    <th>{{ __('admin.product_options.value_ar') }}</th>
                                    <th>{{ __('admin.product_options.price_delta') }}</th>
                                    <th class="text-center">{{ __('admin.product_options.default') }}</th>
                                    <th class="text-center">{{ __('admin.common.active') }}</th>
                                    <th class="text-center">{{ __('admin.product_options.sort_order') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $i => $row)
                                    <tr class="js-value-row">
                                        <td><input type="text" name="values[{{ $i }}][label][en]" value="{{ $row['label']['en'] ?? '' }}" class="admin-input" style="min-width:11rem"></td>
                                        <td dir="rtl"><input type="text" name="values[{{ $i }}][label][ar]" value="{{ $row['label']['ar'] ?? '' }}" class="admin-input" style="min-width:11rem"></td>
                                        <td><input type="number" step="0.01" min="0" name="values[{{ $i }}][price_delta]" value="{{ $row['price_delta'] ?? '0' }}" class="admin-input" style="max-width:7rem"></td>
                                        <td class="text-center"><input type="radio" name="default_index" value="{{ $i }}" class="form-check-input mt-0" {{ (string) $defaultIndex === (string) $i ? 'checked' : '' }}></td>
                                        <td class="text-center">
                                            <input type="hidden" name="values[{{ $i }}][is_active]" value="0">
                                            <input type="checkbox" name="values[{{ $i }}][is_active]" value="1" class="form-check-input mt-0" {{ ($row['is_active'] ?? true) ? 'checked' : '' }}>
                                        </td>
                                        <td class="text-center"><input type="number" min="0" name="values[{{ $i }}][sort_order]" value="{{ $row['sort_order'] ?? $i }}" class="admin-input" style="max-width:5rem"></td>
                                        <td class="text-end"><button type="button" class="admin-btn admin-btn-danger admin-btn-icon js-remove-value"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('values')<div class="admin-error">{{ $message }}</div>@enderror
                    @error('values.*.label.en')<div class="admin-error">{{ $message }}</div>@enderror
                    @error('values.*.label.ar')<div class="admin-error">{{ $message }}</div>@enderror
                    @error('values.*.price_delta')<div class="admin-error">{{ $message }}</div>@enderror
                    <button type="button" class="admin-btn admin-btn-outline mt-2" id="addValue"><i class="bi bi-plus-lg"></i>{{ __('admin.product_options.add_value') }}</button>
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.switch name="is_required" :label="__('admin.product_options.is_required')" :checked="(bool) $option->is_required" />
                    <x-admin.form.switch name="is_active" :label="__('admin.product_options.is_active')" :checked="$editing ? (bool) $option->is_active : true" />
                    <x-admin.form.group :label="__('admin.product_options.sort_order')" name="sort_order">
                        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $option->sort_order ?? 0) }}" class="admin-input">
                    </x-admin.form.group>
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.products.options.index', $product) }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('optionValues');
    var tbody = table.querySelector('tbody');
    var addBtn = document.getElementById('addValue');

    function nextIndex() {
        var max = -1;
        tbody.querySelectorAll('input[name^="values["]').forEach(function (el) {
            var m = el.name.match(/^values\[(\d+)\]/);
            if (m) { max = Math.max(max, parseInt(m[1], 10)); }
        });
        return max + 1;
    }

    function rowHtml(i) {
        return '<tr class="js-value-row">' +
            '<td><input type="text" name="values[' + i + '][label][en]" class="admin-input" style="min-width:11rem"></td>' +
            '<td dir="rtl"><input type="text" name="values[' + i + '][label][ar]" class="admin-input" style="min-width:11rem"></td>' +
            '<td><input type="number" step="0.01" min="0" name="values[' + i + '][price_delta]" value="0" class="admin-input" style="max-width:7rem"></td>' +
            '<td class="text-center"><input type="radio" name="default_index" value="' + i + '" class="form-check-input mt-0"></td>' +
            '<td class="text-center"><input type="hidden" name="values[' + i + '][is_active]" value="0">' +
            '<input type="checkbox" name="values[' + i + '][is_active]" value="1" class="form-check-input mt-0" checked></td>' +
            '<td class="text-center"><input type="number" min="0" name="values[' + i + '][sort_order]" value="' + i + '" class="admin-input" style="max-width:5rem"></td>' +
            '<td class="text-end"><button type="button" class="admin-btn admin-btn-danger admin-btn-icon js-remove-value"><i class="bi bi-x-lg"></i></button></td>' +
            '</tr>';
    }

    addBtn.addEventListener('click', function () {
        tbody.insertAdjacentHTML('beforeend', rowHtml(nextIndex()));
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-remove-value');
        if (!btn) { return; }
        if (tbody.querySelectorAll('.js-value-row').length > 1) {
            btn.closest('tr').remove();
        }
    });
})();
</script>
@endpush
