@extends('admin.layouts.master')

@section('title', $customer->name.' — '.__('admin.app_name'))
@section('page_title', $customer->name)

@section('content')
    <x-admin.page-header :title="$customer->name" :subtitle="$customer->email" :back="route('admin.customers.index')" />

    <div class="row g-3">
        <div class="col-lg-4">
            <x-admin.card :title="__('admin.customers.account')">
                <form method="post" action="{{ route('admin.customers.update', $customer) }}">
                    @csrf @method('PATCH')

                    <div class="admin-field">
                        <span class="admin-label">{{ __('admin.customers.email') }}</span>
                        <div class="admin-cell-sub">{{ $customer->email ?? '—' }}</div>
                        <div class="admin-cell-sub" dir="ltr">{{ $customer->phone ?? '—' }}</div>
                    </div>

                    <x-admin.form.group :label="__('admin.customers.role')" name="role">
                        <select name="role" class="admin-select">
                            @foreach (['customer', 'staff', 'admin'] as $role)
                                <option value="{{ $role }}" {{ old('role', $customer->role) === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </x-admin.form.group>

                    <x-admin.form.group :label="__('admin.customers.loyalty')" name="loyalty_points">
                        <input type="number" min="0" name="loyalty_points" value="{{ old('loyalty_points', $customer->loyalty_points) }}" class="admin-input">
                    </x-admin.form.group>

                    <x-admin.form.switch name="is_active" :label="__('admin.customers.is_active')" :checked="(bool) $customer->is_active" />

                    <div class="admin-form-actions">
                        <button type="submit" class="admin-btn admin-btn-primary w-100 justify-content-center"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                    </div>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-8">
            <x-admin.card :title="__('admin.customers.recent_orders')" :padding="false">
                @if ($customer->orders->isEmpty())
                    <x-admin.empty :message="__('admin.customers.no_orders')" icon="bi-receipt" />
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.orders.number') }}</th>
                                    <th>{{ __('admin.orders.date') }}</th>
                                    <th>{{ __('admin.common.status') }}</th>
                                    <th class="text-end">{{ __('admin.orders.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customer->orders as $order)
                                    <tr>
                                        <td><a href="{{ route('admin.orders.show', $order) }}" class="admin-cell-main text-decoration-none">{{ $order->order_number }}</a></td>
                                        <td>{{ $order->created_at->translatedFormat('j M Y') }}</td>
                                        <td><x-admin.badge :status="$order->status" /></td>
                                        <td class="text-end">@price($order->total_amount)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
