@extends('layouts.app')

@php($locale = app()->getLocale())

@section('title', __('contact.title').' — '.$brand['name'])
@section('meta_description', __('contact.subtitle'))

@section('content')
@php($waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp')))
@php($contactEmail = config('aroma.contact.email'))
{{-- .aroma-contact-page: this page's own signature dark treatment
     (background #330101, text gold) — scoped here rather than touching
     .aroma-hero/.aroma-card/.aroma-trust's shared base rules, since those
     are used across ~15+ other pages (About's own light hero, checkout
     cards, cart's empty state, etc.). See public/css/aroma.css for the
     scoped rules themselves and the reasoning on what stayed light
     (form input wells — legible/conventional data entry) vs went dark. --}}
<div class="aroma-contact-page">

{{-- Hero --}}
<section class="container mt-4">
    <div class="aroma-hero text-center aroma-animate-in">
        <div class="aroma-hero-tagline mb-2">{{ __('contact.eyebrow') }}</div>
        <h1 class="mb-3">{{ __('contact.title') }}</h1>
        <p class="lead aroma-hero-lead mb-0">{{ __('contact.subtitle') }}</p>
    </div>
</section>

<section class="container aroma-section">
    <div class="row g-4">
        {{-- Contact form --}}
        <div class="col-lg-7">
            <div class="aroma-card p-4 p-lg-5">
                <form method="post" action="{{ route('contact.send') }}" id="contactForm" class="js-contact-form" novalidate>
                    @csrf

                    {{-- Honeypot — a real 1x1px box clipped out of view (never
                         offset thousands of pixels away, which is what was
                         causing the page's horizontal scrollbar). --}}
                    <div class="aroma-visually-hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="contact-name">
                                {{ __('contact.form.name') }} <span class="aroma-required">*</span>
                            </label>
                            <input type="text" name="name" id="contact-name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror" required maxlength="120">
                            <div class="invalid-feedback">@error('name'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact-email">
                                {{ __('contact.form.email') }} <span class="aroma-required">*</span>
                            </label>
                            <input type="email" name="email" id="contact-email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror" required maxlength="255">
                            <div class="invalid-feedback">@error('email'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact-phone">{{ __('contact.form.phone_optional') }}</label>
                            <input type="tel" name="phone" id="contact-phone" value="{{ old('phone') }}" dir="ltr"
                                   class="form-control @error('phone') is-invalid @enderror" maxlength="30">
                            <div class="invalid-feedback">@error('phone'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact-topic">
                                {{ __('contact.form.topic') }} <span class="aroma-required">*</span>
                            </label>
                            <select name="topic" id="contact-topic" class="form-select @error('topic') is-invalid @enderror" required>
                                <option value="" disabled {{ old('topic') ? '' : 'selected' }}></option>
                                @foreach (['general', 'order', 'complaint', 'suggestion', 'other'] as $topic)
                                    <option value="{{ $topic }}" {{ old('topic') === $topic ? 'selected' : '' }}>
                                        {{ __('contact.form.topics.'.$topic) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block">@error('topic'){{ $message }}@enderror</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="contact-message">
                                {{ __('contact.form.message') }} <span class="aroma-required">*</span>
                            </label>
                            <textarea name="message" id="contact-message" rows="6" maxlength="2000"
                                      class="form-control @error('message') is-invalid @enderror"
                                      placeholder="{{ __('contact.form.message_placeholder') }}" required>{{ old('message') }}</textarea>
                            <div class="invalid-feedback">@error('message'){{ $message }}@enderror</div>
                        </div>
                    </div>

                    {{-- .btn-aroma-light (white/brown, hover gold) — the same
                         "for use on dark/colored backgrounds" variant the hero
                         and newsletter CTAs use, correct now that this card is
                         solid Burgundy (a solid .btn-aroma button would nearly
                         vanish against it). --}}
                    <button type="submit" class="btn btn-aroma-light btn-lg w-100 mt-4">
                        <i class="bi bi-send me-2"></i>{{ __('contact.form.submit') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Contact info --}}
        <div class="col-lg-5">
            <div class="aroma-trust p-4 p-lg-5 h-100">
                <h3 class="mb-4">{{ __('contact.info.title') }}</h3>

                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="aroma-avatar flex-shrink-0"><i class="bi bi-envelope fs-5"></i></div>
                    <div>
                        <div class="small text-aroma-muted">{{ __('contact.info.email_label') }}</div>
                        <a href="mailto:{{ $contactEmail }}" class="fw-semibold text-decoration-none aroma-contact-value">
                            {{ $contactEmail }}
                        </a>
                    </div>
                </div>

                @if ($waDigits !== '')
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div class="aroma-avatar flex-shrink-0"><i class="bi bi-whatsapp fs-5"></i></div>
                        <div>
                            <div class="small text-aroma-muted">{{ __('contact.info.whatsapp_label') }}</div>
                            <a href="https://wa.me/{{ $waDigits }}" target="_blank" rel="noopener"
                               class="fw-semibold text-decoration-none aroma-contact-value" dir="ltr">
                                <span class="aroma-phone-plus">+</span>{{ $waDigits }}
                            </a>
                        </div>
                    </div>
                @endif

                <div class="d-flex align-items-start gap-3">
                    <div class="aroma-avatar flex-shrink-0"><i class="bi bi-clock-history fs-5"></i></div>
                    <div>
                        <p class="mb-0 text-aroma-muted">{{ __('contact.info.response_time') }}</p>
                    </div>
                </div>

                <hr class="my-4">

                <a href="{{ route('privacy-policy') }}#complaints" class="btn btn-aroma-light w-100">
                    <i class="bi bi-chat-heart me-2"></i>{{ __('storefront.footer.policies') }}
                </a>
            </div>
        </div>
    </div>
</section>
</div>
@endsection
