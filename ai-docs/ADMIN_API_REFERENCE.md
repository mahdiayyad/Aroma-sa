# Aroma Admin API — Reference

The **separate admin front-end** consumes this REST API, exposed by the Aroma
storefront backend. This keeps all business logic (order/payment state machine,
Moyasar refunds, stock rules, bilingual content, validation, authorization) in
one place. **[BUILT]** = implemented & tested; **[NEXT]** = documented but not yet built.

- **Base URL:** `{APP_URL}/api/admin`
- **Format:** JSON. Send `Accept: application/json` on every request.
- **Auth:** Sanctum bearer tokens. `Authorization: Bearer {token}` on all routes
  except `POST /login`. Only users with `role` = `admin` or `staff` can log in.
- **CORS/hosts:** add the admin front-end origin to `config/cors.php` and
  `SANCTUM_STATEFUL_DOMAINS` (only if you use cookie mode; token mode needs no stateful config).

---

## Conventions

- **Bilingual fields** (`name`, `description`, `short_description`, `meta_title`,
  `meta_description`, image `alt`) are objects: `{"ar": "...", "en": "..."}`.
  Send **both** locales on create/update; they're returned the same way.
- **Money** is SAR, returned as numbers (2-decimal precision server-side).
- **Single resource** responses are wrapped in `{"data": {...}}`.
- **Collections** are paginated: `{"data": [...], "links": {...}, "meta": {...}}`.
  Query params: `?page=`, `?per_page=` (default 20), plus per-endpoint filters.
- **Errors:** `401` no/invalid token · `403` authenticated but not admin ·
  `404` not found · `409` conflict (e.g. deleting a category with products) ·
  `422` validation (`{"message": "...", "errors": {"field": ["..."]}}`).
- **File uploads** use `multipart/form-data` (images/logos). Everything else is JSON.

---

## Auth **[BUILT]**

### `POST /login`
Body: `{ "email", "password", "device?": "admin-web" }`
→ `200 { "token": "...", "user": { id, name, email, role, ... } }`
Errors: `422` bad credentials · `403` not an admin / disabled account.

```
curl -X POST {APP_URL}/api/admin/login \
  -H "Accept: application/json" \
  -d "email=admin@aroma.sa&password=secret"
```

### `GET /me` → current admin. `POST /logout` → revokes the current token.

---

## Dashboard **[BUILT]**
### `GET /dashboard`
→ `{ revenue, orders_total, orders_pending, customers, products_total,
     status_counts: {pending:n,...}, low_stock: [product…], recent_orders: [order…] }`

---

## Categories **[BUILT]**  (bound by **slug**)
| Method | Path | Notes |
|---|---|---|
| GET | `/categories` | filters: `q`, `is_active`; `products_count` included |
| POST | `/categories` | create |
| GET | `/categories/{slug}` | with `children`, `products_count` |
| PUT/PATCH | `/categories/{slug}` | update |
| DELETE | `/categories/{slug}` | **409** if it still has products |

Create/update body (multipart if sending `image`):
```json
{
  "name": {"ar":"عطور","en":"Perfumes"},
  "description": {"ar":"","en":""},
  "slug": null,                 // auto from name.en if omitted
  "parent_id": null,            // hierarchical; cannot be itself
  "icon": "bi-droplet",
  "image": "<file>",            // optional upload
  "sort_order": 0,
  "is_active": true,
  "is_featured": false,
  "meta_title": {"ar":"","en":""},
  "meta_description": {"ar":"","en":""}
}
```

## Brands **[BUILT]**  (bound by **slug**)
`GET|POST /brands`, `GET|PUT|PATCH|DELETE /brands/{slug}`. Same shape as
categories but with `logo` (upload) instead of image/icon/parent. Deleting a
brand **unassigns** its products (no block).

## Products **[BUILT]**  (bound by **slug**)
| Method | Path | Notes |
|---|---|---|
| GET | `/products` | filters: `q`, `category_id`, `brand_id`, `is_active`, `in_stock`; `sort`=`newest\|price_asc\|price_desc` |
| POST | `/products` | create (+ optional `images[]` upload) |
| GET | `/products/{slug}` | with `category, brand, variants, images` |
| PUT/PATCH | `/products/{slug}` | update (+ append `images[]`) |
| DELETE | `/products/{slug}` | **soft delete** (recoverable) |

Create/update body:
```json
{
  "name": {"ar":"ورد","en":"Rose"},
  "short_description": {"ar":"","en":""},
  "description": {"ar":"","en":""},
  "category_id": 1,              // required
  "brand_id": null,
  "slug": null,                 // auto from name.en
  "sku": "AR-ROSE-01",
  "base_price": 250,            // required
  "compare_at_price": 320,     // optional (strike-through)
  "currency": "SAR",
  "stock_quantity": 8,          // required (used when no variants)
  "has_variants": false,
  "scent_family": "floral",
  "is_active": true, "is_featured": false,
  "is_new_arrival": false, "is_gift_eligible": true,
  "images[]": "<file>",         // optional; first uploaded becomes primary
  "meta_title": {"ar":"","en":""},
  "meta_description": {"ar":"","en":""}
}
```
Response adds computed `on_sale`, `discount_percent`, `in_stock`.
> **Variant editing** (`product_variants`) is **[NEXT]** — see ADMIN_SPEC §7.4.

## Orders **[BUILT]**  (bound by **id**)
| Method | Path | Notes |
|---|---|---|
| GET | `/orders` | filters: `q` (number/email/phone), `status`, `from`, `to` |
| GET | `/orders/{id}` | with `items`, `payments`, and `allowed_next` statuses |
| PATCH | `/orders/{id}/status` | `{ "status", "tracking_number?" }` — enforces the state machine (**422** on illegal move); sets `shipped_at`/`delivered_at` automatically |
| POST | `/orders/{id}/refund` | `{ "amount?"}` — refunds via Moyasar; needs a captured payment + configured keys |

**Status machine:** `pending → paid → processing → shipped → delivered`, and
`→ cancelled` from pending/paid/processing. Orders are immutable historical
records except status/tracking/notes.

## Customers **[BUILT]**  (bound by **id**)
| Method | Path | Notes |
|---|---|---|
| GET | `/customers` | filters: `q`, `role`, `is_active`; `orders_count` included |
| GET | `/customers/{id}` | with recent `orders` |
| PATCH | `/customers/{id}` | `{ is_active?, loyalty_points?, role? }` |

Passwords are never returned.

---

## Not yet exposed (build next — see ADMIN_SPEC §9)
`[NEXT]` product **variants** & image reorder/primary/delete endpoints ·
**coupons** (new table) · **reviews** (new table) · **reports/CSV** ·
**settings** (shipping/tax/brand) · inventory adjustments / audit log.

---

## Getting started (backend side)
```bash
php artisan migrate                 # adds users.role
php artisan aroma:make-admin admin@aroma.sa --password=secret   # create/promote an admin
php artisan storage:link            # serve uploaded images from the public disk
```
Then `POST /api/admin/login` to get a token and call the endpoints above.
