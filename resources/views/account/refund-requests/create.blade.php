@extends('layouts.app')

@section('title', __('refund.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
<div class="container my-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h2 class="aroma-section-title mb-2">{{ __('refund.title') }}</h2>
            <p class="text-aroma-muted mb-4">{{ __('refund.subtitle') }}</p>

            <div class="aroma-card">
                <div class="card-body p-4">
                    @if ($orders->isEmpty())
                        <p class="text-aroma-muted mb-0">{{ __('refund.no_eligible_orders') }}</p>
                    @else
                        <form method="POST" action="{{ route('account.refund-requests.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('refund.form.order') }}</label>
                                <select name="order_id" class="form-select @error('order_id') is-invalid @enderror" required>
                                    <option value="">{{ __('refund.form.order_placeholder') }}</option>
                                    @foreach ($orders as $order)
                                        <option value="{{ $order->id }}" {{ (int) old('order_id', $selectedOrderId) === $order->id ? 'selected' : '' }}>
                                            {{ $order->order_number }} — {{ $order->created_at->translatedFormat('j M Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('order_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('refund.form.reason') }}</label>
                                <select name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                                    <option value="">{{ __('refund.form.reason_placeholder') }}</option>
                                    @foreach (['damaged', 'wrong_item', 'not_as_described', 'no_longer_needed', 'other'] as $reason)
                                        <option value="{{ $reason }}" {{ old('reason') === $reason ? 'selected' : '' }}>{{ __('refund.reasons.'.$reason) }}</option>
                                    @endforeach
                                </select>
                                @error('reason')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">{{ __('refund.form.notes') }}</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4" maxlength="1000">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-aroma">{{ __('refund.form.submit') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
