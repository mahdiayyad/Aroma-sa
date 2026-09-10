@extends('admin.layouts.master')

@section('title', __('admin.product_options.title').' — '.$product->name)
@section('page_title', __('admin.product_options.title'))

@section('content')
    <x-admin.page-header :title="__('admin.product_options.title')" :subtitle="$product->name" :back="route('admin.products.edit', $product)">
        <x-slot name="actions">
            <a href="{{ route('admin.products.options.create', $product) }}" class="admin-btn admin-btn-primary">
                <i class="bi bi-plus-lg"></i>{{ __('admin.product_options.new') }}
            </a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($options->isEmpty())
            <x-admin.empty :message="__('admin.product_options.empty')" icon="bi-sliders"
                :action="route('admin.products.options.create', $product)" :actionLabel="__('admin.product_options.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.product_options.label') }}</th>
                            <th>{{ __('admin.product_options.values') }}</th>
                            <th class="text-center">{{ __('admin.product_options.required') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-center">{{ __('admin.product_options.sort_order') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($options as $option)
                            <tr>
                                <td>
                                    <div class="admin-cell-main">{{ $option->label }}</div>
                                    <div class="admin-cell-sub">{{ $option->key }}</div>
                                </td>
                                <td>
                                    <div class="admin-cell-sub">
                                        @foreach ($option->values as $value)
                                            <span>{{ $value->label }}@if ($value->priceDeltaLabel()) ({{ $value->priceDeltaLabel() }})@endif @if ($value->is_default)· {{ __('admin.product_options.default') }}@endif</span><br>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-center">{{ $option->is_required ? __('admin.common.yes') : __('admin.common.no') }}</td>
                                <td>
                                    <x-admin.badge :tone="$option->is_active ? 'success' : 'neutral'" :label="$option->is_active ? __('admin.common.active') : __('admin.common.inactive')" />
                                </td>
                                <td class="text-center">{{ $option->sort_order }}</td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.options.edit', $option) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.options.destroy', $option) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>
@endsection
