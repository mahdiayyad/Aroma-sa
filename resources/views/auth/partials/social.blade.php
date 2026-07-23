<div class="position-relative text-center my-3">
    <hr>
    <span class="position-absolute top-50 start-50 translate-middle px-2 bg-white text-muted small">
        {{ __('auth_ui.social.or') }}
    </span>
</div>

<div class="d-grid gap-2">
    <a href="{{ route('social.redirect', 'apple') }}" class="btn btn-dark">
        <i class="bi bi-apple me-1"></i>{{ __('auth_ui.social.apple') }}
    </a>
    <a href="{{ route('social.redirect', 'google') }}" class="btn btn-outline-secondary">
        <i class="bi bi-google me-1"></i>{{ __('auth_ui.social.google') }}
    </a>
</div>
