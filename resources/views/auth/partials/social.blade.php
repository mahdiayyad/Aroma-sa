@php($socialProviders = $socialProviders ?? [])

@if (count($socialProviders))
    <div class="position-relative text-center my-3">
        <hr>
        <span class="position-absolute top-50 start-50 translate-middle px-3 bg-white text-aroma-muted small">
            {{ __('auth_ui.social.or') }}
        </span>
    </div>

    <div class="d-grid gap-2">
        @if (in_array('google', $socialProviders, true))
            <a href="{{ route('social.redirect', 'google') }}" class="btn btn-aroma-outline btn-social">
                <i class="bi bi-google" aria-hidden="true"></i>{{ __('auth_ui.social.google') }}
            </a>
        @endif

        @if (in_array('apple', $socialProviders, true))
            <a href="{{ route('social.redirect', 'apple') }}" class="btn btn-aroma-outline btn-social">
                <i class="bi bi-apple" aria-hidden="true"></i>{{ __('auth_ui.social.apple') }}
            </a>
        @endif
    </div>
@endif
