{{-- Shown only when a signed-in admin got here via "View store" (routes/web.php:
     admin.preview-store, which sets the admin_store_preview session flag) — see
     App\Http\Middleware\BlockAdminShopping for what the flag actually allows.
     Not sticky on purpose: it scrolls away with the page instead of fighting
     the navbar's own sticky-top. --}}
@if (auth()->check() && auth()->user()->isAdmin() && session('admin_store_preview'))
    <div class="aroma-preview-banner" role="status">
        <div class="container">
            <i class="bi bi-eye" aria-hidden="true"></i>
            <span>{{ __('storefront.preview_banner') }}</span>
            <a href="{{ route('admin.exit-preview') }}" class="aroma-preview-banner-exit">
                <i class="bi bi-box-arrow-left" aria-hidden="true"></i>{{ __('storefront.preview_exit') }}
            </a>
        </div>
    </div>
@endif
