@extends('admin.layouts.master')

@section('title', __('admin.refund_requests.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.refund_requests.title'))

@section('content')
    <x-admin.page-header :title="__('admin.refund_requests.title')" :subtitle="__('admin.refund_requests.subtitle')" />

    @php
        $filterChips = [];
        if (! empty($filters['status']) && $filters['status'] !== 'all') { $filterChips['status'] = __('admin.common.status').': '.__('refund.status.'.$filters['status']); }
    @endphp

    <x-admin.filter-bar :chips="$filterChips">
        <select name="status" class="admin-select">
            <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>{{ __('admin.common.status') }}: {{ __('admin.common.all') }}</option>
            <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>{{ __('refund.status.pending') }}</option>
            <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>{{ __('refund.status.approved') }}</option>
            <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('refund.status.rejected') }}</option>
        </select>
    </x-admin.filter-bar>

    <x-admin.card :padding="false">
        @if ($refundRequests->isEmpty())
            <x-admin.empty :message="__('admin.refund_requests.no_requests')" icon="bi-arrow-return-left" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.refund_requests.order') }}</th>
                            <th>{{ __('admin.refund_requests.customer') }}</th>
                            <th>{{ __('admin.refund_requests.reason') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th>{{ __('admin.refund_requests.date') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refundRequests as $req)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $req->order) }}">{{ $req->order->order_number }}</a>
                                </td>
                                <td>{{ optional($req->user)->name ?? $req->order->customer_name }}</td>
                                <td style="max-width:260px">
                                    <div class="admin-cell-main">{{ __('refund.reasons.'.$req->reason) }}</div>
                                    @if ($req->notes)
                                        <div class="admin-cell-sub text-truncate" style="max-width:260px">{{ $req->notes }}</div>
                                    @endif
                                </td>
                                <td><x-admin.badge :tone="$req->status === 'approved' ? 'success' : ($req->status === 'pending' ? 'warning' : 'neutral')" :label="__('refund.status.'.$req->status)" /></td>
                                <td>{{ $req->created_at->translatedFormat('j M Y') }}</td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        @if ($req->isPending())
                                            <form method="post" action="{{ route('admin.refund-requests.approve', $req) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.refund_requests.approve') }}"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="post" action="{{ route('admin.refund-requests.reject', $req) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.refund_requests.reject') }}"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $refundRequests->links() }}
@endsection
