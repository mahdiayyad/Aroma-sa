@extends('admin.layouts.master')

@section('title', __('admin.products_io.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.products_io.title'))

@section('content')
    <x-admin.page-header :title="__('admin.products_io.title')" :subtitle="__('admin.products_io.subtitle')" :back="route('admin.products.index')">
        <x-slot name="actions">
            @can('products.template')
                <a href="{{ route('admin.products.template') }}" class="admin-btn admin-btn-outline"><i class="bi bi-file-earmark-arrow-down"></i>{{ __('admin.products_io.template') }}</a>
            @endcan
            @can('products.import.history')
                <a href="{{ route('admin.products.import.history') }}" class="admin-btn admin-btn-outline"><i class="bi bi-clock-history"></i>{{ __('admin.products_io.history') }}</a>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.card :title="__('admin.products_io.upload_title')">
                <form method="post" action="{{ route('admin.products.import.store') }}" enctype="multipart/form-data" id="importUploadForm">
                    @csrf

                    <x-admin.form.group :label="__('admin.products_io.file')" name="file" required :hint="__('admin.products_io.file_hint', ['max' => $maxMb])">
                        <input type="file" name="file" id="importFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               class="admin-input @error('file') is-invalid @enderror" required>
                    </x-admin.form.group>

                    <hr class="my-3">
                    <div class="admin-label mb-2">{{ __('admin.products_io.options') }}</div>

                    <label class="admin-switch mb-1 d-flex">
                        <input type="checkbox" name="replace_images" value="1" class="form-check-input admin-check mt-0" {{ old('replace_images') ? 'checked' : '' }}>
                        <span>{{ __('admin.products_io.opt_replace_images') }}</span>
                    </label>
                    <div class="admin-hint mb-3 ms-4">{{ __('admin.products_io.opt_replace_images_hint') }}</div>

                    <label class="admin-switch mb-1 d-flex">
                        <input type="checkbox" name="ignore_image_failures" value="1" class="form-check-input admin-check mt-0" {{ old('ignore_image_failures') ? 'checked' : '' }}>
                        <span>{{ __('admin.products_io.opt_ignore_image_failures') }}</span>
                    </label>
                    <div class="admin-hint mb-3 ms-4">{{ __('admin.products_io.opt_ignore_image_failures_hint') }}</div>

                    <label class="admin-switch mb-1 d-flex">
                        <input type="checkbox" name="auto_apply" value="1" class="form-check-input admin-check mt-0" {{ old('auto_apply') ? 'checked' : '' }}>
                        <span>{{ __('admin.products_io.opt_auto_apply') }}</span>
                    </label>
                    <div class="admin-hint mb-3 ms-4">{{ __('admin.products_io.opt_auto_apply_hint') }}</div>

                    <div class="admin-form-actions">
                        <a href="{{ route('admin.products.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                        <button type="submit" class="admin-btn admin-btn-primary" id="importUploadBtn"><i class="bi bi-cloud-arrow-up"></i>{{ __('admin.products_io.start') }}</button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <x-admin.card :title="__('admin.products_io.how_title')">
                <ol class="mb-3 ps-3">
                    <li class="mb-2">{{ __('admin.products_io.how_1') }}</li>
                    <li class="mb-2">{{ __('admin.products_io.how_2') }}</li>
                    <li class="mb-2">{{ __('admin.products_io.how_3') }}</li>
                    <li>{{ __('admin.products_io.how_4') }}</li>
                </ol>
                @can('products.template')
                    <a href="{{ route('admin.products.template') }}" class="admin-btn admin-btn-primary"><i class="bi bi-file-earmark-arrow-down"></i>{{ __('admin.products_io.template') }}</a>
                @endcan
            </x-admin.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Disable the button once submitted so a large file isn't uploaded twice.
    document.getElementById('importUploadForm').addEventListener('submit', function () {
        var btn = document.getElementById('importUploadBtn');
        btn.disabled = true;
        btn.querySelector('i').className = 'bi bi-hourglass-split';
    });
</script>
@endpush
