@extends('admin.layouts.master')

@php($editing = $brand->exists)
@section('title', ($editing ? __('admin.brands.edit') : __('admin.brands.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.brands.edit') : __('admin.brands.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $brand->name : __('admin.brands.new')" :back="route('admin.brands.index')" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.brands.name')">
                    <x-admin.form.bilingual name="name" :label="__('admin.brands.name')" :translations="$brand->getTranslations('name')" required />
                    <x-admin.form.bilingual name="description" :label="__('admin.brands.description')" :translations="$brand->getTranslations('description')" type="textarea" :rows="3" />
                </x-admin.card>

                <x-admin.card :title="__('admin.brands.seo')">
                    <x-admin.form.bilingual name="meta_title" :label="__('admin.brands.meta_title')" :translations="$brand->getTranslations('meta_title')" />
                    <x-admin.form.bilingual name="meta_description" :label="__('admin.brands.meta_description')" :translations="$brand->getTranslations('meta_description')" type="textarea" :rows="2" />
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.group :label="__('admin.brands.slug')" name="slug">
                        <input type="text" name="slug" value="{{ old('slug', $brand->slug) }}" class="admin-input">
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.brands.logo')" name="logo">
                        @if ($editing && $brand->logo)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo) }}" alt="" class="admin-thumb mb-2" style="width:64px;height:64px">
                        @endif
                        <input type="file" name="logo" accept="image/*" class="admin-input" data-preview="#brandLogo">
                        <div id="brandLogo" class="mt-2"></div>
                    </x-admin.form.group>

                    <hr class="my-2">
                    <x-admin.form.switch name="is_active" :label="__('admin.brands.is_active')" :checked="(bool) $brand->is_active" />
                    <x-admin.form.switch name="is_featured" :label="__('admin.brands.is_featured')" :checked="(bool) $brand->is_featured" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.brands.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
