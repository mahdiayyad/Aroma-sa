@extends('admin.layouts.master')

@php($editing = $category->exists)
@section('title', ($editing ? __('admin.categories.edit') : __('admin.categories.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.categories.edit') : __('admin.categories.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $category->name : __('admin.categories.new')" :back="route('admin.categories.index')" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.categories.name')">
                    <x-admin.form.bilingual name="name" :label="__('admin.categories.name')" :translations="$category->getTranslations('name')" required />
                    <x-admin.form.bilingual name="description" :label="__('admin.categories.description')" :translations="$category->getTranslations('description')" type="textarea" :rows="3" />
                </x-admin.card>

                <x-admin.card :title="__('admin.categories.seo')">
                    <x-admin.form.bilingual name="meta_title" :label="__('admin.categories.meta_title')" :translations="$category->getTranslations('meta_title')" />
                    <x-admin.form.bilingual name="meta_description" :label="__('admin.categories.meta_description')" :translations="$category->getTranslations('meta_description')" type="textarea" :rows="2" />
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.group :label="__('admin.categories.slug')" name="slug" :hint="__('admin.categories.slug_hint')">
                        <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" class="admin-input">
                    </x-admin.form.group>
                    @php($parentId = old('parent_id', $category->parent_id))
                    <x-admin.form.group :label="__('admin.categories.parent')" name="parent_id">
                        <select name="parent_id" class="admin-select">
                            <option value="">{{ __('admin.categories.no_parent') }}</option>
                            @foreach ($parents as $p)
                                <option value="{{ $p->id }}" {{ (string) $parentId === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.categories.icon')" name="icon">
                        <input type="text" name="icon" value="{{ old('icon', $category->icon) }}" class="admin-input" placeholder="bi-droplet">
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.categories.sort_order')" name="sort_order">
                        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="admin-input">
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.categories.image')" name="image">
                        @if ($editing && $category->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image) }}" alt="" class="admin-thumb mb-2" style="width:64px;height:64px">
                        @endif
                        <input type="file" name="image" accept="image/*" class="admin-input" data-preview="#catImg">
                        <div id="catImg" class="mt-2"></div>
                    </x-admin.form.group>

                    <hr class="my-2">
                    <x-admin.form.switch name="is_active" :label="__('admin.categories.is_active')" :checked="(bool) $category->is_active" />
                    <x-admin.form.switch name="is_featured" :label="__('admin.categories.is_featured')" :checked="(bool) $category->is_featured" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
