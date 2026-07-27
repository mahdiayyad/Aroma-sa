@extends('admin.layouts.master')

@php($editing = $giftCard->exists)
@section('title', ($editing ? __('admin.gift_cards.edit') : __('admin.gift_cards.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.gift_cards.edit') : __('admin.gift_cards.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $giftCard->name : __('admin.gift_cards.new')" :back="route('admin.gift-cards.index')" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.gift-cards.update', $giftCard) : route('admin.gift-cards.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.gift_cards.name')">
                    <x-admin.form.bilingual name="name" :label="__('admin.gift_cards.name')" :translations="$giftCard->getTranslations('name')" required />
                </x-admin.card>

                <x-admin.card :title="__('admin.gift_cards.design')">
                    @if ($editing)
                        <img src="{{ $giftCard->imageUrl() }}" alt="" class="mb-2 rounded" style="width:180px;height:130px;object-fit:cover">
                    @endif
                    <input type="file" name="image" accept="image/*" class="admin-input" data-preview="#giftCardImg" {{ $editing ? '' : 'required' }}>
                    <div class="admin-hint">{{ __('admin.gift_cards.image_hint') }}</div>
                    <div id="giftCardImg" class="mt-2"></div>
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.group :label="__('admin.gift_cards.slug')" name="slug">
                        <input type="text" name="slug" value="{{ old('slug', $giftCard->slug) }}" class="admin-input">
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.gift_cards.sort_order')" name="sort_order">
                        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $giftCard->sort_order ?? 0) }}" class="admin-input">
                    </x-admin.form.group>

                    <hr class="my-2">
                    <x-admin.form.switch name="is_active" :label="__('admin.gift_cards.is_active')" :checked="(bool) $giftCard->is_active" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.gift-cards.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
