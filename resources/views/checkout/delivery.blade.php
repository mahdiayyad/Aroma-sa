@extends('layouts.app')

@section('title', __('delivery.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
@php($selectedSlot = old('delivery_time_slot', $delivery['time_slot'] ?? null))
@php($slotIcons = ['morning' => 'bi-sunrise', 'afternoon' => 'bi-brightness-high', 'evening' => 'bi-moon-stars'])
<div class="container checkout-page my-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @include('checkout.partials.stepper', ['step' => 5])

            <h2 class="aroma-section-title">{{ __('delivery.title') }}</h2>

            <form method="POST" action="{{ route('checkout.delivery.store') }}" class="needs-validation" novalidate>
                @csrf

                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <label class="form-label fw-semibold" for="deliveryDate">{{ __('delivery.date_label') }}</label>
                        <input type="date" name="delivery_date" id="deliveryDate"
                               class="form-control @error('delivery_date') is-invalid @enderror"
                               min="{{ $minDate }}" max="{{ $maxDate }}"
                               value="{{ old('delivery_date', $delivery['date'] ?? $minDate) }}" required>
                        @error('delivery_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('delivery.slot_label') }}</h5>

                        <div class="aroma-slot-options">
                            @foreach(\App\Models\Order::DELIVERY_SLOTS as $slot)
                                <input type="radio" class="btn-check" name="delivery_time_slot" id="slot-{{ $slot }}"
                                       value="{{ $slot }}" {{ $selectedSlot === $slot ? 'checked' : '' }} required>
                                <label class="aroma-slot-option" for="slot-{{ $slot }}">
                                    <i class="bi {{ $slotIcons[$slot] }}"></i>
                                    <span class="aroma-slot-option-label">{{ __('delivery.slots.'.$slot) }}</span>
                                    <span class="aroma-slot-option-time">{{ __('delivery.slot_times.'.$slot) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('delivery_time_slot')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="aroma-card mb-4">
                    <div class="card-body">
                        <label class="form-label fw-semibold" for="deliveryInstructions">{{ __('delivery.instructions_label') }}</label>
                        <textarea name="delivery_instructions" id="deliveryInstructions" rows="3"
                                  class="form-control @error('delivery_instructions') is-invalid @enderror"
                                  placeholder="{{ __('delivery.instructions_placeholder') }}"
                                  maxlength="500">{{ old('delivery_instructions', $delivery['instructions'] ?? '') }}</textarea>
                        @error('delivery_instructions')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex gap-3 justify-content-between aroma-actions-stack">
                    <x-back-link :href="route('checkout.gift-options')" />
                    <button type="submit" class="btn btn-aroma btn-lg">
                        {{ __('checkout.buttons.continue') }} <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
