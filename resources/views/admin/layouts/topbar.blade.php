<header class="admin-topbar">
    <button class="admin-menu-toggle" type="button" id="adminMenuToggle" aria-label="{{ __('admin.nav.manage') }}">
        <i class="bi bi-list"></i>
    </button>

    <div class="admin-topbar-title">
        <h1>@yield('page_title', __('admin.nav.dashboard'))</h1>
        @hasSection('page_subtitle')
            <p>@yield('page_subtitle')</p>
        @endif
    </div>

    <div class="admin-topbar-actions">
        @php($otherLocale = collect(array_keys($locales))->first(fn ($code) => $code !== app()->getLocale()))
        @if ($otherLocale)
            {{-- Admin routes carry no {locale} URL prefix, so this always
                 falls into locale.switch's plain-redirect-back branch
                 (routes/web.php) — no prefix-swapping logic needed here,
                 unlike the storefront's language-switcher partial. --}}
            <a href="{{ route('locale.switch', $otherLocale) }}" class="admin-btn admin-btn-outline" title="{{ $locales[$otherLocale]['native'] ?? strtoupper($otherLocale) }}">
                <i class="bi bi-globe2"></i>
                <span class="d-none d-sm-inline">{{ $locales[$otherLocale]['native'] ?? strtoupper($otherLocale) }}</span>
            </a>
        @endif
        <div class="dropdown">
            <button class="admin-user" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="admin-avatar">{{ auth()->user()->initials() }}</span>
                <span class="admin-user-name d-none d-sm-inline">{{ auth()->user()->name }}</span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                <li class="dropdown-header">
                    {{ auth()->user()->email }}
                    <span class="badge admin-role-badge">{{ ucfirst(auth()->user()->role) }}</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="{{ route('admin.preview-store') }}" target="_blank" rel="noopener">
                        <i class="bi bi-shop me-2"></i>{{ __('admin.view_store') }}
                    </a>
                </li>
                <li>
                    <form method="post" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>{{ __('admin.logout') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
