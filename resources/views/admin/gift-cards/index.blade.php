@extends('admin.layouts.master')

@section('title', __('admin.gift_cards.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.gift_cards.title'))

@section('content')
    <x-admin.page-header :title="__('admin.gift_cards.title')" :subtitle="__('admin.gift_cards.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.gift-cards.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.gift_cards.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($giftCards->isEmpty())
            <x-admin.empty :message="__('admin.gift_cards.no_gift_cards')" icon="bi-postcard" :action="route('admin.gift-cards.create')" :actionLabel="__('admin.gift_cards.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.gift_cards.design') }}</th>
                            <th class="text-center">{{ __('admin.gift_cards.sort_order') }}</th>
                            <th class="text-center">{{ __('admin.gift_cards.used') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($giftCards as $giftCard)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $giftCard->imageUrl() }}" alt="" class="admin-thumb">
                                        <div>
                                            <div class="admin-cell-main">{{ $giftCard->name }}</div>
                                            <div class="admin-cell-sub">{{ $giftCard->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">{{ $giftCard->sort_order }}</td>
                                <td class="text-center">{{ $giftCard->orders_count }}</td>
                                <td><x-admin.badge :tone="$giftCard->is_active ? 'success' : 'neutral'" :label="$giftCard->is_active ? __('admin.common.active') : __('admin.common.inactive')" /></td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.gift-cards.edit', $giftCard) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.gift-cards.destroy', $giftCard) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
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

    {{ $giftCards->links() }}
@endsection
