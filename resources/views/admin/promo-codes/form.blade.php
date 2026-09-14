@extends('admin.layouts.master')

@php($editing = $promoCode->exists)
@section('title', ($editing ? __('admin.promo_codes.edit') : __('admin.promo_codes.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.promo_codes.edit') : __('admin.promo_codes.new'))

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@endpush

@section('content')
    <x-admin.page-header :title="$editing ? $promoCode->code : __('admin.promo_codes.new')" :back="route('admin.promo-codes.index')" />

    <form method="post" action="{{ $editing ? route('admin.promo-codes.update', $promoCode) : route('admin.promo-codes.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.promo_codes.basic_info')">
                    <x-admin.form.group :label="__('admin.promo_codes.code')" name="code" required :hint="__('admin.promo_codes.code_hint')">
                        <input type="text" name="code" value="{{ old('code', $promoCode->code) }}" class="admin-input" style="text-transform:uppercase" dir="ltr" required>
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.promo_codes.name')" name="name" required>
                        <input type="text" name="name" value="{{ old('name', $promoCode->name) }}" class="admin-input" required>
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.promo_codes.description')" name="description">
                        <textarea name="description" rows="3" class="admin-input">{{ old('description', $promoCode->description) }}</textarea>
                    </x-admin.form.group>
                </x-admin.card>

                <x-admin.card :title="__('admin.promo_codes.discount')">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.discount_type')" name="discount_type" required>
                                <select name="discount_type" class="admin-select" required>
                                    <option value="percentage" {{ old('discount_type', $promoCode->discount_type) === 'percentage' ? 'selected' : '' }}>{{ __('admin.promo_codes.type_percentage') }}</option>
                                    <option value="fixed" {{ old('discount_type', $promoCode->discount_type) === 'fixed' ? 'selected' : '' }}>{{ __('admin.promo_codes.type_fixed') }}</option>
                                </select>
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.discount_value')" name="discount_value" required>
                                <input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value', $promoCode->discount_value) }}" class="admin-input" required>
                            </x-admin.form.group>
                        </div>
                    </div>
                    <x-admin.form.switch name="free_shipping" :label="__('admin.promo_codes.free_shipping')" :checked="(bool) old('free_shipping', $promoCode->free_shipping)" />
                </x-admin.card>

                <x-admin.card :title="__('admin.promo_codes.restrictions')">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.min_order_amount')" name="min_order_amount" :hint="__('admin.promo_codes.min_order_amount_hint')">
                                <input type="number" step="0.01" min="0" name="min_order_amount" value="{{ old('min_order_amount', $promoCode->min_order_amount) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.max_discount_amount')" name="max_discount_amount" :hint="__('admin.promo_codes.max_discount_amount_hint')">
                                <input type="number" step="0.01" min="0" name="max_discount_amount" value="{{ old('max_discount_amount', $promoCode->max_discount_amount) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.starts_at')" name="starts_at">
                                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($promoCode->starts_at)->format('Y-m-d\TH:i')) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.expires_at')" name="expires_at">
                                <input type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($promoCode->expires_at)->format('Y-m-d\TH:i')) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.usage_limit')" name="usage_limit" :hint="__('admin.promo_codes.usage_limit_hint')">
                                <input type="number" min="1" name="usage_limit" value="{{ old('usage_limit', $promoCode->usage_limit) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.group :label="__('admin.promo_codes.usage_limit_per_customer')" name="usage_limit_per_customer" :hint="__('admin.promo_codes.usage_limit_per_customer_hint')">
                                <input type="number" min="1" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer', $promoCode->usage_limit_per_customer) }}" class="admin-input">
                            </x-admin.form.group>
                        </div>
                    </div>

                    <x-admin.form.switch name="first_order_only" :label="__('admin.promo_codes.first_order_only')" :checked="(bool) old('first_order_only', $promoCode->first_order_only)" />

                    <hr class="my-3">

                    <x-admin.form.group :label="__('admin.promo_codes.restrict_products')" name="product_ids" :hint="__('admin.promo_codes.restrict_products_hint')">
                        <select name="product_ids[]" class="admin-select js-select2-multi" multiple data-placeholder="{{ __('admin.promo_codes.restrict_products') }}">
                            @php($selectedProducts = old('product_ids', $editing ? $promoCode->products->pluck('id')->all() : []))
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ in_array($product->id, $selectedProducts) ? 'selected' : '' }}>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>

                    <x-admin.form.group :label="__('admin.promo_codes.restrict_categories')" name="category_ids" :hint="__('admin.promo_codes.restrict_categories_hint')">
                        <select name="category_ids[]" class="admin-select js-select2-multi" multiple data-placeholder="{{ __('admin.promo_codes.restrict_categories') }}">
                            @php($selectedCategories = old('category_ids', $editing ? $promoCode->categories->pluck('id')->all() : []))
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ in_array($category->id, $selectedCategories) ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>

                    <x-admin.form.switch name="customer_restricted" :label="__('admin.promo_codes.customer_restricted')" :checked="(bool) old('customer_restricted', $promoCode->customer_restricted)" />

                    <x-admin.form.group :label="__('admin.promo_codes.restrict_customers')" name="customer_ids" :hint="__('admin.promo_codes.restrict_customers_hint')">
                        <select name="customer_ids[]" class="admin-select js-select2-multi" multiple data-placeholder="{{ __('admin.promo_codes.restrict_customers') }}">
                            @php($selectedCustomers = old('customer_ids', $editing ? $promoCode->customers->pluck('id')->all() : []))
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ in_array($customer->id, $selectedCustomers) ? 'selected' : '' }}>{{ $customer->name }} ({{ $customer->email ?? $customer->phone }})</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.switch name="is_active" :label="__('admin.promo_codes.is_active')" :checked="(bool) old('is_active', $editing ? $promoCode->is_active : true)" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.promo-codes.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(function () {
            var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            $('.js-select2-multi').each(function () {
                $(this).select2({
                    theme: 'default',
                    dir: isRtl ? 'rtl' : 'ltr',
                    width: '100%',
                    placeholder: $(this).data('placeholder') || '',
                });
            });
        });
    </script>
@endpush
