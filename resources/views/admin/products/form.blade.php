@extends('admin.layouts.master')

@php($editing = $product->exists)
@section('title', ($editing ? __('admin.products.edit') : __('admin.products.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.products.edit') : __('admin.products.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $product->name : __('admin.products.new')" :back="route('admin.products.index')" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.products.basic_info')">
                    <x-admin.form.bilingual name="name" :label="__('admin.products.name')" :translations="$product->getTranslations('name')" required />
                    <x-admin.form.bilingual name="short_description" :label="__('admin.products.short_description')" :translations="$product->getTranslations('short_description')" type="textarea" :rows="2" />
                    <x-admin.form.bilingual name="description" :label="__('admin.products.description')" :translations="$product->getTranslations('description')" type="textarea" :rows="5" />
                </x-admin.card>

                <x-admin.card :title="__('admin.products.pricing')">
                    <div class="admin-form-grid">
                        <x-admin.form.group :label="__('admin.products.base_price')" name="base_price" required>
                            <input type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $product->base_price) }}" class="admin-input">
                        </x-admin.form.group>
                        <x-admin.form.group :label="__('admin.products.compare_price')" name="compare_at_price">
                            <input type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="admin-input">
                        </x-admin.form.group>
                    </div>
                </x-admin.card>

                <x-admin.card :title="__('admin.products.inventory')">
                    <div class="admin-form-grid">
                        <x-admin.form.group :label="__('admin.products.sku')" name="sku">
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" class="admin-input">
                        </x-admin.form.group>
                        <x-admin.form.group :label="__('admin.products.stock')" name="stock_quantity" required>
                            <input type="number" min="0" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" class="admin-input">
                        </x-admin.form.group>
                    </div>
                    <x-admin.form.switch name="has_variants" :label="__('admin.products.has_variants')" :checked="(bool) $product->has_variants" />
                    <div class="admin-hint">{{ __('admin.products.has_variants_hint') }}</div>
                </x-admin.card>

                <x-admin.card :title="__('admin.products.media')">
                    @if ($editing && $product->images->isNotEmpty())
                        <div class="d-flex flex-wrap mb-3">
                            @foreach ($product->images as $img)
                                <img src="{{ $img->url() }}" alt="" class="admin-thumb me-2 mb-2" style="width:64px;height:64px">
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="images[]" multiple accept="image/*" class="admin-input" data-preview="#imgPreview">
                    <div class="admin-hint">{{ __('admin.products.images_hint') }}</div>
                    <div id="imgPreview" class="d-flex flex-wrap mt-2"></div>
                </x-admin.card>

                <x-admin.card :title="__('admin.products.seo')">
                    <x-admin.form.bilingual name="meta_title" :label="__('admin.products.meta_title')" :translations="$product->getTranslations('meta_title')" />
                    <x-admin.form.bilingual name="meta_description" :label="__('admin.products.meta_description')" :translations="$product->getTranslations('meta_description')" type="textarea" :rows="2" />
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card :title="__('admin.products.flags')">
                    @php($catId = old('category_id', $product->category_id))
                    <x-admin.form.group :label="__('admin.products.category')" name="category_id" required>
                        <select name="category_id" class="admin-select">
                            <option value="">{{ __('admin.common.none') }}</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" {{ (string) $catId === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>
                    @php($brandId = old('brand_id', $product->brand_id))
                    <x-admin.form.group :label="__('admin.products.brand')" name="brand_id">
                        <select name="brand_id" class="admin-select">
                            <option value="">{{ __('admin.common.none') }}</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b->id }}" {{ (string) $brandId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.products.scent_family')" name="scent_family">
                        <input type="text" name="scent_family" value="{{ old('scent_family', $product->scent_family) }}" class="admin-input">
                    </x-admin.form.group>

                    <hr class="my-3">
                    <x-admin.form.switch name="is_active" :label="__('admin.products.is_active')" :checked="(bool) $product->is_active" />
                    <x-admin.form.switch name="is_featured" :label="__('admin.products.is_featured')" :checked="(bool) $product->is_featured" />
                    <x-admin.form.switch name="is_new_arrival" :label="__('admin.products.is_new_arrival')" :checked="(bool) $product->is_new_arrival" />
                    <x-admin.form.switch name="is_gift_eligible" :label="__('admin.products.is_gift_eligible')" :checked="(bool) $product->is_gift_eligible" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.products.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
