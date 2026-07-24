# Aroma — Admin Panel: Project Details & Business Requirements

> Source of truth for building the Aroma admin (back-office). Everything here is
> derived from the **actual** storefront codebase (schema, models, services,
> payment flow) as of the current branch — not a generic template.
>
> Legend: **[BUILT]** exists in the storefront codebase and can be reused ·
> **[GAP]** must be created for the admin phase.

---

## 1. What the admin is

Aroma is a **premium bilingual (Arabic-primary RTL / English LTR) Saudi
e-commerce platform** selling abayas, perfumes, women's fashion, and gifts. The
**storefront** (customer-facing) is built. The **admin panel** is the internal
back-office for staff to manage the catalog, orders, payments, customers, and
content.

Primary admin users: **store owner / operations staff**. Not customer-facing.

### Core admin responsibilities
1. **Catalog** — Categories, Brands, Products (+ Variants, Images) CRUD.
2. **Orders** — view, search, advance status, add tracking, refund, notes.
3. **Payments** — inspect gateway transactions, issue refunds.
4. **Customers** — view/manage users, addresses, activation, loyalty.
5. **Merchandising** — featured/new-arrival flags, sort order, gift eligibility.
6. **Reports & dashboard** — sales, orders, top products, stock alerts.
7. **Settings** — brand, shipping, tax, payment config.

---

## 2. Tech stack (shared with storefront)

| Layer | Choice |
|---|---|
| Framework | **Laravel 8** (`^8.75`), PHP `^7.4 / ^8.0` |
| DB | **MySQL 8**, `utf8mb4` / `utf8mb4_unicode_ci` |
| Cache/Queue | Redis + Laravel Queue (jobs table exists) |
| Auth | Laravel session auth + **Laravel Sanctum** (installed — enables token/API auth if admin is a separate SPA) |
| Views | Blade + **Bootstrap 5.3** (RTL build), jQuery, Select2 |
| Payments | **Moyasar** (Mada, Apple Pay, Visa, Mastercard), BNPL: Tabby, Tamara |
| Storage | Local `public` disk now; **S3 planned** (image `disk` column already supports per-file disks) |
| i18n | JSON translatable columns + `HasTranslations` trait; `lang/{ar,en}` files |

### Architecture — DECIDED: separate admin project + REST API
The admin is a **separate project** that consumes a secured REST API exposed by
this backend (`/api/admin/*`, Sanctum tokens). This keeps a **single source of
truth** for business logic (order/payment state machine, Moyasar refunds, stock
rules, bilingual content, validation, authorization) and avoids the "shared
database" anti-pattern (two codebases writing the same tables with drifting rules).

**The API is BUILT and tested** — see **`ADMIN_API_REFERENCE.md`** for the full
endpoint contract. The separate admin front-end (any stack) authenticates via
`POST /api/admin/login`, then calls the resource endpoints with a bearer token.

Implemented server-side: Sanctum admin auth, `admin` middleware, API controllers
+ Form Requests + JSON Resources for **dashboard, categories, brands, products,
orders (with status machine + refund), customers**.

**Also built — an in-app Blade admin UI** (`/admin/*`, session auth) per the
project owner's request for a ready-to-use back-office: login, dashboard, and
CRUD screens for products/categories/brands + order management + customers, using
Bootstrap 5.3, jQuery, and reusable Blade components (`resources/views/admin/**`,
`resources/views/components/admin/**`, `public/css/admin.css`, `public/js/admin.js`).
Both front-ends share the same models/services/Form Requests, so business logic
stays in one place. Access: `php artisan aroma:make-admin {email} --password=…`
then visit `/admin/login`.

### ⚠️ Blade gotcha (if using Blade)
This Laravel 8 build does **not** support the Laravel 9 `@selected` / `@checked`
directives (they render as raw text) — use `{{ $cond ? 'selected' : '' }}`. Also,
**do not mix** inline `@php(...)` and block `@php ... @endphp` in the same file;
it corrupts compilation. Pick one style per file.

---

## 3. Access control — **[BUILT]**

Implemented in this phase:
1. **Role on users** — `users.role` (`customer` default | `staff` | `admin`);
   added to `User::$fillable` with `User::isAdmin()` (true for staff/admin).
   Migration: `2026_07_24_000001_add_role_to_users_table`.
2. **`admin` route middleware** — `App\Http\Middleware\EnsureUserIsAdmin`
   (registered as `admin` in the Kernel) rejects authenticated non-staff with 403.
3. **API auth** — Sanctum tokens; only staff/admin can obtain one. Guard added
   to `config/auth.php`. See `AuthController` + `ADMIN_API_REFERENCE.md`.
4. **Create an admin** — `php artisan aroma:make-admin {email} [--password=] [--role=admin|staff]`.

*Still recommended (next):* per-resource **Policies** and, if you need granular
staff permissions, a roles/permissions package.

---

## 4. Shared conventions the admin MUST follow

- **Bilingual JSON fields** — `name`, `description`, `short_description`,
  `meta_title`, `meta_description`, image `alt` are stored as
  `{"ar": "...", "en": "..."}`. Models use the **`HasTranslations`** trait
  (`app/Support/Concerns/HasTranslations.php`): reading returns the active
  locale (falling back to `app.fallback_locale`); admin forms must edit **both
  locales** (two inputs per field, AR + EN). Save the whole array.
- **Money** — prices are `decimal` in SAR. Format with
  `App\Support\Formatting\Money::format($amount)` (symbol `ر.س`, 2 decimals; trails
  the amount in AR, leads in EN). Never hard-code currency.
- **Slugs** — `categories`, `brands`, `products` have a unique `slug` and use it
  as the route key. Admin should auto-generate from the EN name (`Str::slug`)
  with a uniqueness check, editable.
- **Soft deletes** — **only `products`** use `SoftDeletes`. Admin "delete" of a
  product should soft-delete (recoverable / trashed view). All other entities
  hard-delete — respect FK constraints (below).
- **Mass assignment** — models use `$guarded = ['id']` (products guard `['id']`
  too), so most columns are fillable; still validate via **Form Requests**.
- **Service layer** — business logic lives in services (`app/Services`), not
  controllers (project standard). Reuse `CheckoutService`, `MoyasarPaymentService`,
  repositories. Keep admin controllers thin.

---

## 5. Data model reference (authoritative schema)

FK delete behavior matters for admin delete UX. Translatable columns are marked ⓣ.

### `users` [BUILT]
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | required |
| email | string, **nullable**, unique | email *or* phone identifies the user |
| phone | string, **nullable**, unique | KSA format `^(\+9665\|05)\d{8}$` |
| email_verified_at / phone_verified_at | timestamp nullable | |
| password | string **nullable** | null for social-only accounts |
| gender | string nullable | `female` \| `male` \| `unspecified` |
| dob | date nullable | birthday offers |
| locale | string(5) default `ar` | |
| avatar | string nullable | |
| loyalty_points | uint default 0 | |
| is_active | bool default true | admin can deactivate |
| provider / provider_id | string nullable | `google` \| `apple` |
| **role** | **[GAP] add** | `customer`/`admin`/`staff` |

Relations: `wishlistItems` (hasMany Wishlist), `wishlistedProducts` (belongsToMany
Product), `addresses` (hasMany), `orders` (hasMany). Helpers: `hasWishlisted()`,
`initials()`.

### `categories` [BUILT]
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| parent_id | FK→categories nullable, **nullOnDelete** | hierarchical (self-referencing) |
| name ⓣ | json | required |
| slug | string unique | route key |
| description ⓣ | json nullable | |
| image | string nullable | S3 path |
| icon | string nullable | bootstrap-icon class (nav tiles) |
| sort_order | uint default 0 | |
| is_active / is_featured | bool | featured → homepage |
| meta_title ⓣ / meta_description ⓣ | json nullable | SEO |

Relations: `parent`, `children`, `products` (hasMany). Scopes: `active`, `featured`,
`roots`. **Delete rule:** a category with products **cannot be deleted** (products
`category_id` is `restrictOnDelete`) — admin must block/reassign first. Deleting a
parent nulls its children's `parent_id`.

### `brands` [BUILT]
`id`, `name` ⓣ, `slug` unique, `description` ⓣ, `logo` (string nullable), `is_active`,
`is_featured`, `meta_title` ⓣ, `meta_description` ⓣ. Relations: `products`.
**Delete rule:** products' `brand_id` is `nullOnDelete` (deleting a brand unassigns
its products).

### `products` [BUILT] — softDeletes
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| category_id | FK→categories, **restrictOnDelete** | required |
| brand_id | FK→brands nullable, nullOnDelete | optional |
| name ⓣ / short_description ⓣ / description ⓣ | json | |
| slug | string unique | route key |
| sku | string nullable unique | |
| base_price | decimal(10,2) default 0 | display / "from" price |
| compare_at_price | decimal(10,2) nullable | strike-through when > base_price |
| currency | string(3) default SAR | |
| stock_quantity | uint default 0 | used when **no** variants |
| has_variants | bool default false | |
| scent_family | string nullable | merchandising |
| is_active / is_featured / is_new_arrival | bool | |
| is_gift_eligible | bool default true | |
| meta_title ⓣ / meta_description ⓣ | json | |

Relations: `category`, `brand`, `variants` (ordered by sort_order), `images`
(ordered). Helpers: `isOnSale()`, `discountPercent()`, `inStock()` (variant-aware),
`priceLabel()`, `compareAtLabel()`, `primaryImageUrl()`. Scopes: `active`, `featured`,
`newArrivals`.

### `product_variants` [BUILT]
`id`, `product_id` (cascadeOnDelete), `name` ⓣ (json, e.g. "100ml"), `sku` (unique
nullable), `attributes` (json — `{size, color}`), `price` (decimal(10,2),
**absolute** per-variant price), `stock_quantity` (uint), `is_active`, `sort_order`.
Helpers: `inStock()`, `priceLabel()`.

### `product_images` [BUILT]
`id`, `product_id` (cascade), `variant_id` (nullable, cascade), `disk` (default
`public`), `path`, `alt` ⓣ, `sort_order`, `is_primary`. `url()` resolves via the
file's storage disk (or returns absolute/root-relative paths as-is). **Primary
image** = `is_primary` true, else first by `sort_order`.

### `addresses` [BUILT]
`id`, `user_id` (cascade), `type` enum(`billing`|`shipping`), `label`,
`recipient_name`, `phone`, `city`, `region`, `street_address` (text),
`postal_code` (nullable), `is_default`. Note: at checkout, addresses are **snapshotted
as JSON** onto the order (see below), so editing a saved address never alters past orders.

### `wishlists` [BUILT]
`id`, `user_id` (cascade), `product_id` (cascade), unique(`user_id`,`product_id`). Read-only for admin (analytics: most-wishlisted).

### `orders` [BUILT]
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK nullable, nullOnDelete | **null = guest order** |
| order_number | string unique | format `AR-{year}-{000000}` (sequence per year) |
| status | string default `pending` | see status machine §6 |
| customer_name / customer_email / customer_phone | string | snapshot |
| billing_address / shipping_address | json | **snapshot** at order time |
| subtotal / discount_amount / shipping_cost / tax_amount / total_amount | decimal(12,2) | |
| shipping_method | string nullable | `standard`, … |
| tracking_number | string nullable | admin sets |
| shipped_at / delivered_at | timestamp nullable | admin sets |
| customer_notes | text nullable | from customer |
| internal_notes | text nullable | **admin-only** |

Relations: `user`, `items` (hasMany OrderItem), `payment` (hasMany Payment — note:
method is singular but returns many). Helpers: `isPaid()`, `isPending()`,
`isCancellable()`, `statusBadgeClass()`. Constant `Order::STATUSES`.

### `order_items` [BUILT]
`id`, `order_id` (cascade), `product_id` (**restrictOnDelete** — can't delete a
product that's in an order; soft delete instead), `product_variant_id` (nullable,
nullOnDelete), `product_data` (json snapshot: name/image/sku — **name is the
resolved single-locale string** at order time), `variant_data` (json nullable),
`unit_price` (decimal 12,2), `quantity` (uint), `line_total` (decimal 12,2). Helpers:
`priceLabel()`, `totalLabel()`.

### `payments` [BUILT]
`id`, `order_id` (cascade), `gateway` (`moyasar`|`tabby`|`tamara`), `method`
(`mada`|`applepay`|`visa`|`mastercard`|…), `status` (`pending`|`authorized`|
`captured`|`failed`|`refunded`), `transaction_id` (unique nullable), `reference_number`,
`amount` (decimal 12,2), `currency`, `gateway_response` (json), `refunded_amount`
(decimal 12,2), `refunded_at`. Helpers: `isPaid()` (captured|authorized), `isFailed()`.

---

## 6. Business rules & domain logic (must be preserved by admin)

### Order status machine
`pending → paid → processing → shipped → delivered`, plus `cancelled`.
- `isPaid()` is true for **paid, processing, shipped, delivered**.
- `isPending()` → only `pending`.
- `isCancellable()` → `pending` or `paid`.
- Admin transitions to build **[GAP]**: mark processing, ship (set
  `tracking_number` + `shipped_at`), deliver (set `delivered_at`), cancel. Enforce
  valid transitions in an `OrderStatusService` (don't let staff jump arbitrarily);
  consider refund-on-cancel if already paid.

### Payment status machine
`pending → authorized/captured` (success) · `failed` · `refunded`.
- On success the webhook/callback sets payment `captured` and order `paid`.
- Refund sets payment `refunded`, records `refunded_amount` + `refunded_at`.

### Order & payment lifecycle (end-to-end) [BUILT]
1. Checkout `storePayment` → `CheckoutService::createOrder` (order `pending`) +
   `createPayment` (payment `pending`) → `MoyasarPaymentService::createInvoice`
   → redirect to Moyasar hosted page.
2. **Return** `paymentCallback` → `getPaymentStatus`; if invoice `paid`,
   `markOrderAsPaid` (order `paid`, payment `captured`) → redirect home with success.
3. **Webhook** `POST /webhooks/moyasar` (`MoyasarWebhookController`) — signature
   verified, then `handleWebhook`: `invoice.paid` → success (fires
   **`OrderPaid`** event → `SendOrderConfirmationEmail`); `invoice.failed` →
   payment `failed`, order back to `pending` (fires **`OrderPaymentFailed`** →
   `SendPaymentFailedEmail`); `invoice.expired` → payment `failed`, order `cancelled`.
4. Order number: `AR-{year}-{6-digit sequence}` (count of that year + 1).

### Pricing rules
- `base_price` is the product's display / "from" price.
- Variants carry their **own absolute `price`** (not a delta).
- `compare_at_price > base_price` ⇒ on sale ⇒ strike-through + `discountPercent()`.
- Totals at checkout: `subtotal − discount + tax + shipping`. **discount/tax/shipping
  are currently hard-coded to 0** in `CheckoutService::calculateTotals` — the admin/
  settings must introduce real shipping & tax rules **[GAP]**.

### Inventory rules
- No variants → `products.stock_quantity`. Has variants → **sum of variant stock**.
- Cart caps quantity at available stock. **Stock is NOT auto-decremented on
  order yet** (TODO in `CheckoutService`) — admin needs stock management + a
  decrement-on-paid rule and low-stock alerts **[GAP]**.

### Translations
Every ⓣ field is bilingual JSON. Admin forms edit AR + EN; missing AR falls back
to EN at render. Default locale is **`ar`** (RTL).

---

## 7. Admin modules — functional requirements

For each: list view (search/filter/sort/paginate), create/edit forms (with
validation), and the actions/business rules. Two-column responsive forms, Select2
dropdowns, bilingual inputs (per `UI_UX_GUIDELINES.md`).

### 7.1 Dashboard **[GAP]**
KPIs: today/period revenue, order counts by status, new customers, low-stock
products, pending payments, top products, recent orders. Charts optional.

### 7.2 Categories [model BUILT · admin CRUD GAP]
- Tree/list with parent, sort_order, active/featured toggles, product count.
- Create/edit: AR+EN name/description/meta, slug (auto), parent, icon, image
  upload, sort_order, is_active, is_featured.
- **Delete guarded**: block if it has products (restrictOnDelete) — force reassign;
  deleting a parent nulls children's parent.

### 7.3 Brands [model BUILT · admin CRUD GAP]
- List (active/featured, product count). Create/edit: AR+EN name/description/meta,
  slug, logo upload, is_active, is_featured. Delete unassigns products (nullOnDelete).

### 7.4 Products [model BUILT · admin CRUD GAP] — the biggest module
- **List**: image, name, SKU, category, brand, price, stock, active/featured/new
  badges; filters (category, brand, active, in/out of stock, on sale); search
  (name/SKU); sort; paginate; bulk actions (activate, feature, delete).
- **Create/Edit** (card sections per `UI_UX_GUIDELINES.md`): Basic info (AR+EN
  name/short/long description, category, brand, scent_family) · SKU · Pricing
  (base_price, compare_at_price) · Inventory (has_variants toggle, stock_quantity)
  · **Variants** (repeater: name AR+EN, sku, attributes, price, stock, active,
  sort) · **Images** (multi-upload, drag-reorder, set primary, alt AR+EN, optional
  variant link) · Flags (is_active/featured/new_arrival/gift_eligible) · SEO
  (meta_title/description AR+EN).
- Slug auto from EN name, unique. **Soft delete** (trashed view + restore).
- Image handling **[GAP]**: upload to `public` disk (S3-ready via `disk` column),
  store `path`, manage `is_primary`/`sort_order`, delete file on removal.

### 7.5 Orders [model BUILT · admin management GAP]
- **List**: order_number, customer, date, status badge, total, payment status;
  filters (status, date range, paid/unpaid, guest/registered); search
  (order_number/email/phone); paginate.
- **Detail**: items (with snapshots), totals, billing/shipping snapshots, customer
  info, payment(s), timeline. Actions: **advance status**, set `tracking_number`
  + mark shipped (`shipped_at`), mark delivered (`delivered_at`), cancel, add
  `internal_notes`, **refund** (→ §7.6), resend confirmation email.
- Enforce the status machine; respect `isCancellable()`.

### 7.6 Payments [model BUILT · admin view/refund GAP]
- View transactions per order (gateway, method, status, transaction_id, amount,
  `gateway_response`). **Refund** via `MoyasarPaymentService::refundPayment($payment,
  $amount)` (full/partial) → sets `refunded`, `refunded_amount`, `refunded_at`.
  Requires configured Moyasar keys + `transaction_id`.

### 7.7 Customers (users) [model BUILT · admin CRUD GAP]
- List: name, email/phone, orders count, loyalty_points, is_active, joined;
  search; filter active/social. Detail: profile, addresses, order history,
  wishlist. Actions: activate/deactivate (`is_active`), adjust `loyalty_points`,
  assign `role` (once added), view (not edit) snapshots. **Never expose passwords.**

### 7.8 Reviews **[GAP — table & model do NOT exist]**
`DATABASE_DESIGN.md` lists `reviews`, but there is **no migration/model**. To build:
`reviews` (product_id, user_id, order_id?, rating 1–5, title, body, is_approved,
timestamps) + moderation (approve/reject) in admin + storefront display.

### 7.9 Coupons / discounts **[GAP — table & model do NOT exist]**
Listed in `DATABASE_DESIGN.md` but **not built**. `orders.discount_amount` exists
but there's no coupon logic. To build: `coupons` (code, type fixed/percent, value,
min_subtotal, usage limits, starts/ends, is_active) + apply-at-checkout logic in
`CheckoutService::calculateTotals` (currently discount=0) + admin CRUD.

### 7.10 Reports **[GAP]**
Sales over time, by category/brand/product, order status breakdown, payment method
mix, refunds, low stock, new vs returning customers, BNPL share. Export CSV.

### 7.11 Settings **[GAP]**
Brand identity, currency, locales, and payment methods currently live in
**`config/aroma.php`** (a PHP file, not DB). Shipping & tax are not modeled.
Decide: editable settings store (DB `settings` table) vs. env/config. At minimum:
shipping methods & rates, tax rate, free-shipping threshold, Moyasar keys
(`services.moyasar.*` in `.env`), contact info.

---

## 8. Reusable building blocks already in the codebase [BUILT]

- **Services**: `CheckoutService` (createOrder, calculateTotals, createPayment,
  markOrderAsPaid, order-number gen), `MoyasarPaymentService` (createInvoice,
  getPaymentStatus, refundPayment, webhook verify/handle), `CatalogService`,
  `CartService`.
- **Repositories** (bound in `RepositoryServiceProvider`): `ProductRepository`,
  `CategoryRepository`, `BrandRepository` (+ Contracts) — extend for admin queries.
- **Support**: `HasTranslations` trait, `Money` formatter, `BaseRepository`,
  `BaseService`.
- **Events/Listeners**: `OrderPaid`→`SendOrderConfirmationEmail`,
  `OrderPaymentFailed`→`SendPaymentFailedEmail` (wire admin actions to reuse/extend).
- **Config**: `config/aroma.php` (brand, colors, fonts, locales, currency SAR,
  country SA, payment methods + BNPL, contact).
- **Admin layout stub**: `resources/views/layouts/admin.blade.php` (extend it).
- **Component CSS library**: `public/css/components/*` (buttons, forms, cards,
  tables, badges, select2, navigation) — reuse for a consistent admin UI.
- **Validation references** (mirror these): KSA phone `^(\+9665|05)\d{8}$`
  (`ProfileUpdateRequest`), address fields (`AddressRequest`), payment
  (`PaymentRequest`).

---

## 9. Consolidated GAP list (build order for the admin phase)

1. **Admin auth & roles** — `role`/`is_admin` on users, `admin` middleware,
   admin login redirect, Policies. *(blocks everything)*
2. **Admin shell** — `/admin` route group, `Admin\*` controllers, extend
   `layouts/admin.blade.php`, nav, dashboard.
3. **Catalog CRUD** — Categories, Brands, Products (+ variant repeater + image
   uploader with primary/sort), soft-delete/restore, bulk actions.
4. **Image upload pipeline** — store to `public` disk (S3-ready), `product_images`
   management, file cleanup on delete.
5. **Orders management** — list/detail, `OrderStatusService` (valid transitions,
   tracking, shipped/delivered/cancel), internal notes, resend email.
6. **Payments/refunds** — view + `refundPayment` (needs Moyasar keys).
7. **Customers** — list/detail, activate, loyalty, role assignment.
8. **Inventory** — decrement-on-paid rule, stock adjustments, low-stock alerts.
9. **Coupons** — new table/model + checkout discount logic + admin CRUD.
10. **Reviews** — new table/model + moderation + storefront display.
11. **Shipping & tax** — settings + wire into `calculateTotals` (currently 0).
12. **Reports** — dashboards + CSV export.
13. **Settings** — DB-backed settings or config editor; Moyasar keys management.
14. **Audit log** *(recommended)* — who changed what, for orders/products/refunds.

---

## 10. Non-functional requirements

- **RTL/i18n first** — admin UI must work in AR (RTL) and EN (LTR); all labels
  translated; bilingual content editing everywhere ⓣ.
- **Authorization** — every admin action behind auth + role + Policy; no IDOR.
- **Validation** — Form Requests for every write; reuse storefront rules.
- **Pagination/search/filter** — every list view; server-side.
- **Consistency** — reuse the component CSS library & Money/HasTranslations.
- **Auditability** — log destructive/financial actions (refunds, status changes,
  deletes).
- **Data integrity** — respect FK rules (restrictOnDelete on category/product);
  never break order snapshots (they're immutable historical records).
- **Security** — CSRF on all forms, verify Moyasar webhook signatures (already
  implemented), never expose password hashes or full gateway secrets.

---

## 11. Suggested `/admin` route map (Option A)

```
Route::prefix('admin')->name('admin.')->middleware(['auth','admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('products', ProductController::class);           // + soft-delete/restore
    Route::resource('products.variants', ProductVariantController::class);
    Route::post('products/{product}/images', [ProductImageController::class,'store']);
    Route::delete('images/{image}', [ProductImageController::class,'destroy']);

    Route::get('orders', [OrderController::class,'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class,'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class,'updateStatus']);
    Route::post('orders/{order}/refund', [OrderController::class,'refund']);

    Route::resource('customers', CustomerController::class)->only(['index','show','update']);

    // Later: coupons, reviews, reports, settings
});
```

> Controllers thin → delegate to services (`CheckoutService`, a new
> `OrderStatusService`, `MoyasarPaymentService`) and repositories.

---

*Keep this file updated as the admin is built. Cross-references:
`DATABASE_DESIGN.md`, `FEATURES.md`, `CODING_STANDARDS.md`, `TECH_STACK.md`,
`UI_UX_GUIDELINES.md`, `BRAND_GUIDELINES.md`, `config/aroma.php`.*
