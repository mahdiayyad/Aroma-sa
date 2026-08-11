@extends('layouts.app')

@section('title', __('account.addresses.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4">
    <h1 class="aroma-section-title">{{ __('account.addresses.title') }}</h1>
    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'addresses'])
        </div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="text-aroma-muted mb-0">{{ __('account.addresses.subtitle') }}</p>
                <a href="{{ route('account.addresses.create') }}" class="btn btn-aroma btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('account.addresses.new') }}
                </a>
            </div>

            @if ($addresses->isEmpty())
                <div class="aroma-trust p-5 text-center">
                    <i class="bi bi-geo-alt fs-1 d-block mb-3" style="color:var(--aroma-light-brown)"></i>
                    <h5 class="mb-2">{{ __('account.addresses.empty') }}</h5>
                    <p class="text-aroma-muted small mb-3">{{ __('account.addresses.empty_hint') }}</p>
                    <a href="{{ route('account.addresses.create') }}" class="btn btn-aroma btn-sm">{{ __('account.addresses.new') }}</a>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($addresses as $address)
                        <div class="col-md-6">
                            <div class="aroma-trust p-3 h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong>{{ $address->label ?: __('account.addresses.default_label') }}</strong>
                                    @if ($address->is_default)
                                        <span class="aroma-badge-status aroma-badge-success">{{ __('account.addresses.default') }}</span>
                                    @endif
                                </div>
                                <div class="text-aroma-muted small flex-grow-1">
                                    <div>{{ $address->recipient_name }}</div>
                                    <div>{{ $address->street_address }}</div>
                                    <div>{{ $address->city }}, {{ $address->region }} {{ $address->postal_code }}</div>
                                    <div dir="ltr">{{ $address->phone }}</div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="{{ route('account.addresses.edit', $address) }}" class="btn btn-aroma-outline btn-sm flex-grow-1">
                                        <i class="bi bi-pencil me-1"></i>{{ __('account.addresses.edit') }}
                                    </a>
                                    @unless ($address->is_default)
                                        <form method="post" action="{{ route('account.addresses.default', $address) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-aroma-outline btn-sm" title="{{ __('account.addresses.make_default') }}">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        </form>
                                    @endunless
                                    <form method="post" action="{{ route('account.addresses.destroy', $address) }}"
                                          data-confirm="{{ __('account.addresses.confirm_delete') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('account.addresses.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
