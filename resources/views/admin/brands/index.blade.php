@extends('admin.layouts.master')

@section('title', __('admin.brands.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.brands.title'))

@section('content')
    <x-admin.page-header :title="__('admin.brands.title')" :subtitle="__('admin.brands.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.brands.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.brands.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($brands->isEmpty())
            <x-admin.empty :message="__('admin.brands.no_brands')" icon="bi-award" :action="route('admin.brands.create')" :actionLabel="__('admin.brands.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.brands.name') }}</th>
                            <th class="text-center">{{ __('admin.brands.products') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($brands as $brand)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($brand->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo) }}" alt="" class="admin-thumb">@endif
                                        <div>
                                            <div class="admin-cell-main">{{ $brand->name }}</div>
                                            <div class="admin-cell-sub">{{ $brand->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">{{ $brand->products_count }}</td>
                                <td>
                                    <x-admin.badge :tone="$brand->is_active ? 'success' : 'neutral'" :label="$brand->is_active ? __('admin.common.active') : __('admin.common.inactive')" />
                                    @if ($brand->is_featured)<span class="admin-badge admin-badge-info ms-1"><i class="bi bi-star-fill"></i></span>@endif
                                </td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.brands.edit', $brand) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.brands.destroy', $brand) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
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

    {{ $brands->links() }}
@endsection
