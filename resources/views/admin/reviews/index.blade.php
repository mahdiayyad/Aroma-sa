@extends('admin.layouts.master')

@section('title', __('admin.reviews.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.reviews.title'))

@section('content')
    <x-admin.page-header :title="__('admin.reviews.title')" :subtitle="__('admin.reviews.subtitle')" />

    <form method="get" class="admin-toolbar admin-autofilter">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
        <select name="status" class="admin-select">
            <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>{{ __('admin.common.status') }}: {{ __('admin.common.all') }}</option>
            <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>{{ __('reviews.status.pending') }}</option>
            <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>{{ __('reviews.status.approved') }}</option>
            <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('reviews.status.rejected') }}</option>
            <option value="hidden" {{ ($filters['status'] ?? '') === 'hidden' ? 'selected' : '' }}>{{ __('reviews.status.hidden') }}</option>
        </select>
        <select name="rating" class="admin-select">
            <option value="">{{ __('admin.reviews.rating') }}: {{ __('admin.common.all') }}</option>
            @for ($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}" {{ (string) ($filters['rating'] ?? '') === (string) $i ? 'selected' : '' }}>{{ $i }} ★</option>
            @endfor
        </select>
        <label class="admin-switch mb-0 d-flex align-items-center">
            <input type="checkbox" name="reported" value="1" class="form-check-input mt-0" {{ ($filters['reported'] ?? false) ? 'checked' : '' }} onchange="this.form.submit()">
            <span>{{ __('admin.reviews.reported_only') }}</span>
        </label>
        <noscript><button class="admin-btn admin-btn-outline btn-sm">{{ __('admin.common.apply') }}</button></noscript>
    </form>

    <x-admin.card :padding="false">
        @if ($reviews->isEmpty())
            <x-admin.empty :message="__('admin.reviews.no_reviews')" icon="bi-chat-square-text" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.reviews.product') }}</th>
                            <th>{{ __('admin.reviews.customer') }}</th>
                            <th>{{ __('admin.reviews.rating') }}</th>
                            <th>{{ __('admin.reviews.review') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reviews as $review)
                            <tr>
                                <td>{{ optional($review->product)->name ?? '—' }}</td>
                                <td>
                                    {{ optional($review->user)->name ?? '—' }}
                                    @if ($review->is_verified_purchase)
                                        <i class="bi bi-patch-check-fill text-success ms-1" title="{{ __('reviews.verified_purchase') }}"></i>
                                    @endif
                                </td>
                                <td>{{ $review->rating }} ★</td>
                                <td style="max-width:260px">
                                    <div class="admin-cell-main">{{ $review->title }}</div>
                                    <div class="admin-cell-sub text-truncate" style="max-width:260px">{{ $review->body }}</div>
                                    @if ($review->reported_count > 0)
                                        <span class="admin-badge admin-badge-danger mt-1"><i class="bi bi-flag-fill"></i> {{ __('admin.reviews.reported') }} ({{ $review->reported_count }})</span>
                                    @endif
                                </td>
                                <td><x-admin.badge :tone="$review->status === 'approved' ? 'success' : ($review->status === 'pending' ? 'warning' : 'neutral')" :label="__('reviews.status.'.$review->status)" /></td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        @if ($review->status !== 'approved')
                                            <form method="post" action="{{ route('admin.reviews.approve', $review) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.reviews.approve') }}"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        @endif
                                        @if ($review->status !== 'rejected')
                                            <form method="post" action="{{ route('admin.reviews.reject', $review) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.reviews.reject') }}"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif
                                        @if ($review->status !== 'hidden')
                                            <form method="post" action="{{ route('admin.reviews.hide', $review) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn admin-btn-outline admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.reviews.hide') }}"><i class="bi bi-eye-slash"></i></button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('admin.reviews.destroy', $review) }}" data-confirm="{{ __('admin.reviews.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon" data-bs-toggle="tooltip" title="{{ __('admin.common.delete') }}"><i class="bi bi-trash"></i></button>
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

    {{ $reviews->links() }}
@endsection
