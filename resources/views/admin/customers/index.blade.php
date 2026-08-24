@extends('admin.layouts.master')

@section('title', __('admin.customers.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.customers.title'))

@section('content')
    <x-admin.page-header :title="__('admin.customers.title')" :subtitle="__('admin.customers.subtitle')" />

    <form method="get" class="admin-toolbar admin-autofilter">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="admin-input" placeholder="{{ __('admin.common.search') }}">
        <select name="role" class="admin-select">
            <option value="all">{{ __('admin.customers.role') }}: {{ __('admin.common.all') }}</option>
            @foreach (['customer', 'staff', 'admin'] as $role)
                <option value="{{ $role }}" {{ ($filters['role'] ?? '') === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
            @endforeach
        </select>
        <noscript><button class="admin-btn admin-btn-outline btn-sm">{{ __('admin.common.apply') }}</button></noscript>
    </form>

    <x-admin.card :padding="false">
        @if ($customers->isEmpty())
            <x-admin.empty :message="__('admin.customers.no_customers')" icon="bi-people" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.customers.name') }}</th>
                            <th>{{ __('admin.customers.email') }}</th>
                            <th class="text-center">{{ __('admin.customers.orders') }}</th>
                            <th class="text-center">{{ __('admin.customers.loyalty') }}</th>
                            <th>{{ __('admin.customers.role') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="admin-avatar" style="width:32px;height:32px;font-size:.7rem">{{ $customer->initials() }}</span>
                                        <span class="admin-cell-main">{{ $customer->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $customer->email ?? '—' }}</div>
                                    <div class="admin-cell-sub" dir="ltr">{{ $customer->phone }}</div>
                                </td>
                                <td class="text-center">{{ $customer->orders_count }}</td>
                                <td class="text-center">{{ $customer->loyalty_points }}</td>
                                <td><span class="admin-badge admin-badge-{{ $customer->role === 'customer' ? 'neutral' : 'info' }}">{{ ucfirst($customer->role) }}</span></td>
                                <td><x-admin.badge :tone="$customer->is_active ? 'success' : 'danger'" :label="$customer->is_active ? __('admin.common.active') : __('admin.common.inactive')" /></td>
                                <td class="text-end"><a href="{{ route('admin.customers.show', $customer) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-eye"></i></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $customers->links() }}
@endsection
