@php($current = app()->getLocale())
<div class="dropdown">
    <button class="btn btn-sm dropdown-toggle aroma-icon-link border-0 bg-transparent" type="button"
            data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe2 me-1"></i>{{ $locales[$current]['native'] ?? strtoupper($current) }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($locales as $code => $meta)
            <li>
                <a class="dropdown-item {{ $code === $current ? 'active' : '' }}"
                   href="{{ route('locale.switch', $code) }}">
                    {{ $meta['native'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
