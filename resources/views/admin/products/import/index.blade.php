@php
    $tones = [
        \App\Models\ProductImportRun::STATUS_PENDING => 'info', \App\Models\ProductImportRun::STATUS_QUEUED => 'info', \App\Models\ProductImportRun::STATUS_VALIDATING => 'info', \App\Models\ProductImportRun::STATUS_APPLYING => 'info',
        \App\Models\ProductImportRun::STATUS_READY => 'warning', \App\Models\ProductImportRun::STATUS_COMPLETED => 'success',
        \App\Models\ProductImportRun::STATUS_INVALID => 'danger', \App\Models\ProductImportRun::STATUS_FAILED => 'danger', \App\Models\ProductImportRun::STATUS_CANCELLED => 'neutral',
    ];
@endphp

@extends('admin.layouts.master')

@section('title', __('admin.products_io.history_title').' — '.__('admin.app_name'))
@section('page_title', __('admin.products_io.history_title'))

@section('content')
    <x-admin.page-header :title="__('admin.products_io.history_title')" :subtitle="__('admin.products_io.history_subtitle')" :back="route('admin.products.index')">
        <x-slot name="actions">
            @can('products.import')
                <a href="{{ route('admin.products.import.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-cloud-arrow-up"></i>{{ __('admin.products_io.import') }}</a>
            @endcan
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($runs->isEmpty())
            <x-admin.empty :message="__('admin.products_io.history_empty')" icon="bi-clock-history" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('admin.products_io.col_file') }}</th>
                            <th>{{ __('admin.products_io.col_status') }}</th>
                            <th>{{ __('admin.products_io.col_result') }}</th>
                            <th>{{ __('admin.products_io.col_by') }}</th>
                            <th>{{ __('admin.products_io.col_when') }}</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            @php($summary = $run->summary ?? [])
                            <tr>
                                <td><strong>{{ $run->id }}</strong></td>
                                <td>{{ $run->original_name }}</td>
                                <td><x-admin.badge :tone="$tones[$run->status] ?? 'neutral'" :label="__('admin.products_io.status.'.$run->status)" /></td>
                                <td class="admin-cell-sub">
                                    @if ((int) $run->error_count > 0)
                                        {{ __('admin.products_io.errors_title', ['count' => $run->error_count]) }}
                                    @elseif (! empty($summary))
                                        {{ __('admin.products_io.created') }} {{ $summary['created'] ?? 0 }} ·
                                        {{ __('admin.products_io.updated') }} {{ $summary['updated'] ?? 0 }} ·
                                        {{ __('admin.products_io.unchanged') }} {{ $summary['unchanged'] ?? 0 }}
                                    @else — @endif
                                </td>
                                <td>{{ optional($run->user)->name ?? '—' }}</td>
                                <td class="admin-cell-sub">{{ $run->created_at->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.products.import.show', $run) }}" class="admin-btn admin-btn-outline btn-sm">{{ __('admin.products_io.view') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $runs->links() }}
@endsection
