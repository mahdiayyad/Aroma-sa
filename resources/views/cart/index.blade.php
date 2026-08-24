@extends('layouts.app')

@section('title', __('cart.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
<div class="container my-4">
    <h1 class="aroma-section-title">{{ __('cart.title') }}</h1>

    @if (empty($rows))
        <div class="aroma-trust p-5 text-center">
            <i class="bi bi-bag fs-1 d-block mb-3" style="color:var(--aroma-light-brown)"></i>
            <p class="text-aroma-muted">{{ __('cart.empty') }}</p>
            <a href="{{ route('home', $locale) }}" class="btn btn-aroma">{{ __('cart.empty_cta') }}</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="aroma-trust p-3">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-aroma-muted">
                                <th>{{ __('cart.product') }}</th>
                                <th>{{ __('cart.price') }}</th>
                                <th style="width:150px">{{ __('cart.qty') }}</th>
                                <th class="text-end">{{ __('cart.total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $rowId => $row)
                                <tr data-row="{{ $rowId }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $row['image'] }}" alt="" width="56" height="56"
                                                 class="rounded" style="object-fit:cover;background:var(--aroma-skin)">
                                            <div>
                                                <a href="{{ route('product.show', [$locale, $row['slug']]) }}"
                                                   class="fw-semibold text-decoration-none" style="color:var(--aroma-ink)">
                                                    {{ $row['name'][$locale] ?? reset($row['name']) }}
                                                </a>
                                                @if (!empty($row['variant']))
                                                    <div class="small text-aroma-muted">{{ $row['variant'][$locale] ?? reset($row['variant']) }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>@price($row['unit_price'])</td>
                                    <td>
                                        {{-- Live: changing the quantity updates totals without a reload (Item 3) --}}
                                        <div class="aroma-qty-stepper aroma-qty-sm">
                                            <button type="button" class="aroma-qty-btn aroma-qty-minus" aria-label="{{ __('cart.decrease') }}">&minus;</button>
                                            <input type="number" value="{{ $row['qty'] }}" min="0" max="99"
                                                   class="form-control form-control-sm js-cart-qty aroma-qty-input"
                                                   data-url="{{ route('cart.update', $rowId) }}"
                                                   aria-label="{{ __('cart.qty') }}">
                                            <button type="button" class="aroma-qty-btn aroma-qty-plus" aria-label="{{ __('cart.increase') }}">+</button>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold js-line-total">@price($row['unit_price'] * $row['qty'])</td>
                                    <td class="text-end">
                                        {{-- Live: removing a line deletes the row without a reload --}}
                                        <form method="post" action="{{ route('cart.remove', $rowId) }}" class="js-cart-remove">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger" type="submit" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('cart.remove') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="post" action="{{ route('cart.clear') }}" class="mt-2">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-link text-aroma-muted" type="submit">{{ __('cart.clear') }}</button>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="aroma-trust p-4">
                    <h5 class="mb-3">{{ __('cart.summary') }}</h5>
                    <div class="d-flex justify-content-between mb-3">
                        <span>{{ __('cart.subtotal') }}</span>
                        <span class="fw-bold js-cart-subtotal" style="color:var(--aroma-brown)">{{ $subtotal }}</span>
                    </div>
                    <a href="{{ route('checkout.review') }}" class="btn btn-aroma w-100 mb-2">{{ __('cart.checkout') }}</a>
                    <a href="{{ route('home', $locale) }}" class="btn btn-aroma-outline w-100">{{ __('cart.continue') }}</a>
                    <p class="small text-aroma-muted mt-3 mb-0"><i class="bi bi-wallet2 me-1"></i>{{ __('cart.bnpl') }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
