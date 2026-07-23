# Aroma — Saudi E-Commerce Platform

Premium bilingual (Arabic RTL + English) storefront for fragrances, floral
arrangements, beauty, abayas, accessories, gifts and seasonal lines. Built on
Laravel 8. This repository currently contains the **Sprint 0 foundation** plus
**Sprint 1 (Catalog)** — categories, brands, products, variants and images, with
a bilingual storefront (homepage, category listing with filters, product detail).

## Stack

- **Backend:** Laravel 8, PHP 7.4 (CI also runs 8.1 — the recommended target), MySQL, Redis-ready queues/cache
- **Frontend:** Bootstrap 5.3 + Blade + jQuery (CDN now; SCSS pipeline via Laravel Mix)
- **Storage:** S3-compatible (`FILESYSTEM_DISK`)
- **Payments (planned):** Moyasar (Mada/Apple Pay/cards) + Tabby & Tamara (BNPL)

## Getting started

```bash
composer install
cp .env.example .env          # already contains sane local defaults
php artisan key:generate
# create the database (MySQL): CREATE DATABASE aroma CHARACTER SET utf8mb4;
php artisan migrate
php artisan db:seed        # loads bilingual placeholder catalog data
php artisan serve
```

> The seeded categories/products are **placeholders** (bundled SVG imagery)
> pending the client's real catalog. Re-running the seeders is idempotent
> (keyed by slug); real data can also be entered via the admin panel (next sprint).

Visit `http://localhost:8000` → redirects to `/ar` (default locale). English at `/en`.

Front-end assets render from CDN out of the box. To build the branded SCSS
pipeline (needs Node): `npm install && npm run prod`.

## Architecture

- **Localization / RTL** — `App\Http\Middleware\SetLocale` resolves the locale
  from the `{locale}` route prefix; supported locales + direction live in
  `config/aroma.php`. Content strings in `resources/lang/{ar,en}/`.
- **Brand system** — `config/aroma.php` is the single source of truth for the
  palette, typography and store settings (from the Brand Guidelines). Shared to
  all views by `App\Providers\AromaServiceProvider`, which also registers the
  `@price` Blade directive.
- **Domain layers** — thin controllers → `App\Support\Services\BaseService` →
  `App\Support\Repositories\BaseRepository` (bound via
  `App\Providers\RepositoryServiceProvider`). Bilingual model content uses the
  dependency-free `App\Support\Concerns\HasTranslations` trait (JSON columns).
- **Layouts** — storefront `resources/views/layouts/app.blade.php` (RTL-aware),
  admin shell `layouts/admin.blade.php`.

## Testing

```bash
php artisan test        # or: vendor/bin/phpunit
```

CI runs migrations + the suite on PHP 7.4 and 8.1 (`.github/workflows/ci.yml`).

## Roadmap

Sprint plan and full blueprint are tracked separately. Done so far:
- **Sprint 0** — foundation (bilingual/RTL, theme, architecture).
- **Sprint 1** — catalog storefront (categories, brands, products, variants).
- **Authentication & accounts** — register (phone-first + email), login (email
  or phone), logout, password reset, social login (Google built-in; Apple via
  the community driver), guarded account area (dashboard + profile).
- **Wishlist** — persistent per-user, toggle from cards/PDP.
- **Cart** — session-backed (guests + users), add/update/remove, cart page.

Heading typeface is **Luxury** (drop `public/fonts/luxury.woff2` to activate).

Next: **admin panel** (catalog + orders management), then **checkout &
payments** (Moyasar + Tabby/Tamara).
