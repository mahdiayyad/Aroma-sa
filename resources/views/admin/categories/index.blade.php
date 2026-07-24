@extends('admin.layouts.master')

@section('title', __('admin.categories.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.categories.title'))

@section('content')
    <x-admin.page-header :title="__('admin.categories.title')" :subtitle="__('admin.categories.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.categories.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.categories.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($categories->isEmpty())
            <x-admin.empty :message="__('admin.categories.no_categories')" icon="bi-diagram-3" :action="route('admin.categories.create')" :actionLabel="__('admin.categories.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.categories.name') }}</th>
                            <th>{{ __('admin.categories.parent') }}</th>
                            <th class="text-center">{{ __('admin.categories.products') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td>
                                    <div class="admin-cell-main"><i class="bi {{ $category->icon ?? 'bi-tag' }} me-1 text-muted"></i>{{ $category->name }}</div>
                                    <div class="admin-cell-sub">{{ $category->slug }}</div>
                                </td>
                                <td>{{ optional($category->parent)->name ?? '—' }}</td>
                                <td class="text-center">{{ $category->products_count }}</td>
                                <td>
                                    <x-admin.badge :tone="$category->is_active ? 'success' : 'neutral'" :label="$category->is_active ? __('admin.common.active') : __('admin.common.inactive')" />
                                    @if ($category->is_featured)<span class="admin-badge admin-badge-info ms-1"><i class="bi bi-star-fill"></i></span>@endif
                                </td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
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

    {{ $categories->links() }}
@endsection
