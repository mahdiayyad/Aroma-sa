@extends('layouts.app')

@section('title', __('gift.toggle_title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@push('head')
    <link href="{{ \App\Support\Assets::versioned('css/components/gift-studio.css') }}" rel="stylesheet">
@endpush

@section('content')
@php($locale = app()->getLocale())
@php($isGift = (bool) old('is_gift', $gift['is_gift'] ?? false))
@php($selectedCardId = old('greeting_card_id', $gift['card_id'] ?? null))
<div class="container checkout-page my-4 my-lg-5">
    @include('checkout.partials.stepper', ['step' => 4])

    <h2 class="aroma-section-title">{{ __('gift.toggle_title') }}</h2>

    <form method="POST" action="{{ route('checkout.gift-options.store') }}" id="giftForm" class="needs-validation">
        @csrf

        {{-- Gift toggle --}}
        <div class="aroma-card mb-4">
            <div class="card-body">
                <p class="text-aroma-muted small mb-3">{{ __('gift.toggle_hint') }}</p>

                <div class="aroma-gift-toggle">
                    <input type="radio" class="btn-check" name="is_gift" id="isGiftYes" value="1" {{ $isGift ? 'checked' : '' }}>
                    <label class="aroma-gift-toggle-option" for="isGiftYes">
                        <i class="bi bi-gift"></i>
                        <span>{{ __('gift.toggle_yes') }}</span>
                    </label>

                    <input type="radio" class="btn-check" name="is_gift" id="isGiftNo" value="0" {{ $isGift ? '' : 'checked' }}>
                    <label class="aroma-gift-toggle-option" for="isGiftNo">
                        <i class="bi bi-bag"></i>
                        <span>{{ __('gift.toggle_no') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="giftPanel" class="{{ $isGift ? '' : 'd-none' }}">
            <div class="row g-4">
                <div class="col-lg-8 order-lg-1">

                    {{-- Recipient --}}
                    <div class="aroma-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('gift.recipient_title') }}</h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('checkout.recipient_name') }}</label>
                                    <input type="text" name="recipient[recipient_name]" class="form-control @error('recipient.recipient_name') is-invalid @enderror"
                                           value="{{ old('recipient.recipient_name', $recipient['recipient_name'] ?? '') }}">
                                    @error('recipient.recipient_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('checkout.phone') }}</label>
                                    <x-phone-input name="recipient[phone]" :value="$recipient['phone'] ?? null" />
                                </div>
                                <div class="col-12">
                                    <x-address-input field-prefix="recipient" dom-id="recipient"
                                        :method="old('recipient.method', $recipient['method'] ?? null)"
                                        :code="$recipient['location_code'] ?? null"
                                        :latitude="$recipient['latitude'] ?? null"
                                        :longitude="$recipient['longitude'] ?? null"
                                        :country="$recipient['country'] ?? null"
                                        :city="$recipient['city'] ?? null"
                                        :district="$recipient['district'] ?? null"
                                        :street="$recipient['street_address'] ?? null"
                                        :building-number="$recipient['building_number'] ?? null"
                                        :apartment-number="$recipient['apartment_number'] ?? null"
                                        :postal-code="$recipient['postal_code'] ?? null"
                                        :additional-notes="$recipient['additional_notes'] ?? null" />
                                </div>
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="is_anonymous" id="isAnonymous" value="1"
                                       {{ old('is_anonymous', $gift['is_anonymous'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isAnonymous">
                                    {{ __('gift.anonymous') }}
                                    <div class="small text-aroma-muted">{{ __('gift.anonymous_hint') }}</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Gift wrap --}}
                    <div class="aroma-card mb-4">
                        <div class="card-body d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <h5 class="card-title mb-1">{{ __('gift.wrap_title') }}</h5>
                                <div class="small text-aroma-muted">{{ __('gift.wrap_label') }} — {{ __('gift.wrap_fee', ['fee' => \App\Support\Formatting\Money::format($wrapFee)]) }}</div>
                            </div>
                            <div class="form-check form-switch fs-4 m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="gift_wrap" id="giftWrap" value="1"
                                       {{ old('gift_wrap', ($gift['wrap_fee'] ?? 0) > 0) ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>

                    {{-- Greeting card --}}
                    <div class="aroma-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('gift.card_title') }}</h5>

                            <div class="aroma-gift-cards">
                                <input type="radio" class="btn-check" name="greeting_card_id" id="cardNone" value="" {{ empty($selectedCardId) ? 'checked' : '' }}>
                                <label class="aroma-gift-card-option aroma-gift-card-none" for="cardNone" data-image="">
                                    <i class="bi bi-slash-circle"></i>
                                    <span>{{ __('gift.card_none') }}</span>
                                </label>

                                @foreach($giftCards as $card)
                                    <input type="radio" class="btn-check" name="greeting_card_id" id="card-{{ $card->id }}" value="{{ $card->id }}"
                                           {{ (int) $selectedCardId === $card->id ? 'checked' : '' }}>
                                    <label class="aroma-gift-card-option" for="card-{{ $card->id }}" data-image="{{ $card->imageUrl() }}">
                                        <img src="{{ $card->imageUrl() }}" alt="{{ $card->name }}" loading="lazy">
                                        <span>{{ $card->name }}</span>
                                        @if ($card->slug === 'blank-note')
                                            <span class="aroma-gift-card-caption">{{ __('gift.blank_note_caption') }}</span>
                                        @endif
                                        <span class="aroma-gift-card-price {{ (float) $card->price === 0.0 ? 'is-free' : '' }}">
                                            @if ((float) $card->price === 0.0)
                                                {{ __('gift.card_free') }}
                                            @else
                                                @price($card->price)
                                            @endif
                                        </span>
                                        {{-- A button nested inside this <label> doesn't double-toggle
                                             the radio — the same nesting the terms checkbox above
                                             already relies on. Opens the shared preview modal below
                                             so a design can be seen full-size before committing. --}}
                                        <button type="button" class="aroma-gift-card-zoom"
                                                data-bs-toggle="modal" data-bs-target="#giftCardPreviewModal"
                                                data-card-id="{{ $card->id }}" data-card-image="{{ $card->imageUrl() }}" data-card-name="{{ $card->name }}"
                                                aria-label="{{ __('gift.preview_cta') }}">
                                            <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                                        </button>
                                    </label>
                                @endforeach
                            </div>
                            @error('greeting_card_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Message studio --}}
                    <div class="aroma-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('gift.message_title') }}</h5>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('gift.from_label') }}</label>
                                    <input type="text" name="gift_from" id="giftFrom" class="form-control" maxlength="100"
                                           value="{{ old('gift_from', $gift['from'] ?? '') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('gift.to_label') }}</label>
                                    <input type="text" name="gift_to" id="giftTo" class="form-control" maxlength="100"
                                           value="{{ old('gift_to', $gift['to'] ?? '') }}">
                                </div>
                            </div>

                            <label class="form-label fw-semibold">{{ __('gift.message_label') }}</label>
                            <textarea name="gift_message" id="giftMessage" class="form-control @error('gift_message') is-invalid @enderror" rows="4"
                                      maxlength="{{ $maxChars }}" data-max-lines="{{ $maxLines }}"
                                      placeholder="{{ __('gift.message_placeholder') }}">{{ old('gift_message', $gift['message'] ?? '') }}</textarea>
                            @error('gift_message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#suggestionsModal">
                                    <i class="bi bi-stars me-1"></i>{{ __('gift.suggestions_cta') }}
                                </button>
                                <div class="small text-aroma-muted aroma-gift-counters">
                                    <span id="charsRemaining" data-template="{{ __('gift.chars_remaining', ['n' => ':n']) }}">{{ __('gift.chars_remaining', ['n' => $maxChars]) }}</span>
                                    · <span id="linesRemaining" data-template="{{ __('gift.lines_remaining', ['n' => ':n']) }}">{{ __('gift.lines_remaining', ['n' => $maxLines]) }}</span>
                                </div>
                            </div>

                            <hr>

                            <div class="row g-3 align-items-start">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold d-block">{{ __('gift.signature_add') }}</label>
                                    <button type="button" class="btn btn-aroma-outline" data-bs-toggle="modal" data-bs-target="#signatureModal">
                                        <i class="bi bi-pen me-2"></i><span id="signatureBtnLabel" data-edit-label="{{ __('gift.signature_edit') }}">{{ ($gift['signature'] ?? null) ? __('gift.signature_edit') : __('gift.signature_add') }}</span>
                                    </button>
                                    <img id="signatureThumb" class="aroma-signature-thumb {{ ($gift['signature'] ?? null) ? '' : 'd-none' }}"
                                         src="{{ ($gift['signature'] ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($gift['signature']) : '' }}" alt="">
                                    <input type="hidden" name="gift_signature_data" id="giftSignatureData" value="{{ old('gift_signature_data') }}">
                                    @error('gift_signature_data')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ __('gift.media_title') }}</label>
                                    <input type="url" name="gift_media_url" id="giftMediaUrl" class="form-control @error('gift_media_url') is-invalid @enderror"
                                           placeholder="{{ __('gift.media_placeholder') }}" value="{{ old('gift_media_url', $gift['media_url'] ?? '') }}">
                                    @error('gift_media_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <div class="small text-aroma-muted mt-1">{{ __('gift.media_hint') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live preview rail --}}
                <div class="col-lg-4 order-lg-2">
                    <div class="aroma-card checkout-summary sticky-top">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('gift.preview_title') }}</h5>

                            <div class="aroma-gift-preview" id="giftPreview">
                                <div class="aroma-gift-preview-card" id="giftPreviewCardWrap">
                                    <img id="giftPreviewImage" src="" alt="" class="d-none">
                                    <div id="giftPreviewEmpty" class="aroma-gift-preview-empty">
                                        <i class="bi bi-postcard"></i>
                                        <span>{{ __('gift.preview_empty') }}</span>
                                    </div>
                                </div>
                                <div class="aroma-gift-preview-body">
                                    <p class="aroma-gift-preview-to mb-1 d-none" id="giftPreviewTo" data-template="{{ __('gift.preview_to', ['name' => ':name']) }}"></p>
                                    <p class="aroma-gift-preview-message mb-2" id="giftPreviewMessage"></p>
                                    <p class="aroma-gift-preview-from mb-0 d-none" id="giftPreviewFrom" data-template="{{ __('gift.preview_from', ['name' => ':name']) }}"></p>
                                    <img id="giftPreviewSignature" src="{{ ($gift['signature'] ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($gift['signature']) : '' }}"
                                         alt="" class="aroma-gift-preview-signature {{ ($gift['signature'] ?? null) ? '' : 'd-none' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="d-flex gap-3 justify-content-between aroma-actions-stack mt-2">
            <x-back-link :href="route('checkout.address')" />
            <button type="submit" class="btn btn-aroma btn-lg" id="giftSubmit">
                <span id="giftSubmitLabel" data-continue-label="{{ __('gift.continue') }}" data-skip-label="{{ __('gift.skip') }}">{{ $isGift ? __('gift.continue') : __('gift.skip') }}</span>
                <i class="bi {{ $locale === 'ar' ? 'bi-chevron-left' : 'bi-chevron-right' }} ms-2"></i>
            </button>
        </div>
    </form>
</div>

{{-- Signature modal --}}
<div class="modal fade" id="signatureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('gift.signature_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <canvas id="signatureCanvas" class="aroma-signature-canvas" width="500" height="220"></canvas>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-aroma-outline" id="signatureClear">{{ __('gift.signature_clear') }}</button>
                <button type="button" class="btn btn-aroma" id="signatureSave">{{ __('gift.signature_save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Message suggestions modal --}}
<div class="modal fade" id="suggestionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('gift.suggestions_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    @foreach($suggestionCategories as $key => $category)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#suggest-{{ $key }}" type="button">{{ $category['label'] }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content">
                    @foreach($suggestionCategories as $key => $category)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="suggest-{{ $key }}">
                            <div class="list-group">
                                @foreach($category['items'] as $item)
                                    <button type="button" class="list-group-item list-group-item-action aroma-suggestion-item" data-text="{{ $item }}">{{ $item }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- One shared modal for all designs, populated per-click via Bootstrap's
     own relatedTarget convention (see gift-studio.js) rather than one modal
     per card. object-fit:contain (not the grid thumbnails' cover/crop) shows
     each design's full artwork uncropped — the grid stays a fast-scanning
     index, this is the "look closer" view. --}}
<div class="modal fade" id="giftCardPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="giftCardPreviewName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="aroma-gift-card-preview-stage">
                    <button type="button" class="aroma-gift-card-nav aroma-gift-card-nav-prev" id="giftCardPreviewPrev" aria-label="{{ __('gift.preview_prev') }}">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                    <img id="giftCardPreviewLarge" src="" alt="" class="aroma-gift-card-preview-large">
                    <button type="button" class="aroma-gift-card-nav aroma-gift-card-nav-next" id="giftCardPreviewNext" aria-label="{{ __('gift.preview_next') }}">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-aroma w-100" id="giftCardPreviewSelect"
                        data-select-label="{{ __('gift.preview_select') }}" data-selected-label="{{ __('gift.preview_selected') }}">
                    {{ __('gift.preview_select') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ \App\Support\Assets::versioned('js/location-lookup.js') }}"></script>
<script src="{{ \App\Support\Assets::versioned('js/address-method-toggle.js') }}"></script>
<script src="{{ \App\Support\Assets::versioned('js/gift-studio.js') }}"></script>
@endpush
@endsection
