# Product Import / Export — developer guide

Bulk product management for the Aroma admin panel: export products to `.xlsx`, download a template, import a
spreadsheet with row-by-row validation, SKU-based upsert, image download, all-or-nothing transactions, background
processing with live progress, permissions and an audit log.

For the admin-facing walkthrough see [ADMIN_GUIDE.md](ADMIN_GUIDE.md). Example workbooks are in
[`examples/`](examples/), screenshots in [`screenshots/`](screenshots/).

- Stack: Laravel 8.83, PHP 7.4 (nothing here needs PHP 8), MySQL, Bootstrap 5 admin UI.
- Spreadsheet engine: **Maatwebsite Laravel Excel `^3.1`** (3.1.70) on PhpSpreadsheet 1.30. Excel 4.x requires PHP 8.3,
  so it is deliberately pinned to 3.x; the code only uses the stable 3.x concerns (`FromQuery`, `FromArray`, `ToCollection`,
  `WithChunkReading`, …) and moves to 4.x with the PHP upgrade.
- PHP extensions the spreadsheet engine needs on the server: `zip`, `xml`, `gd`, `mbstring`, `fileinfo` (plus `curl`, which the image
  downloader uses to pin the vetted IP address; without it the download still works but is not IP-pinned).

---

## 1. Requirements → implementation

| Requirement | Where it lives |
|---|---|
| Export button, all / selected / filtered | `admin/products/index.blade.php` (Export ▾), `ProductImportExportController::export`, `App\Exports\ProductsExport` |
| Every SEO + product field in the sheet | `App\Support\ProductSheet\Columns` (25 columns) |
| Import `.xlsx`, required-field validation, row-by-row errors | `ProductImportService::validate`, `RowProcessor::plan`, `admin/products/import/show.blade.php`, `ImportErrorsExport` |
| Update by SKU, create when unknown | `RowProcessor::matchExisting` / `persist` |
| Arabic + English content | Translatable `name`, `description`, `short_description`, `meta_title`, `meta_description` (`HasTranslations`) |
| Category by name or ID | `EntityResolver` (ID, slug, Arabic/English name; alef/yeh/tashkeel-insensitive) |
| Image URLs downloaded and stored | `ImageFetcher` (SSRF-safe) → `TempImageStore` → `public` disk, `product_images` |
| Transactions, no partial imports | `ProductImportService::apply` — one `DB::transaction`; files written during it are deleted on rollback |
| Template with examples + instructions | `App\Exports\ProductsTemplateExport` (Products / Instructions / Reference sheets) |
| Meta title/description rendered on the storefront, slugs generated + validated | `catalog/product.blade.php`, `catalog/category.blade.php`, `layouts/app.blade.php`; `RowProcessor::planSlug` |
| Permissions | `config/aroma.php` → `admin.abilities`, `AuthServiceProvider`, `can:` route middleware, `@can` in views |
| Logging | `imports` log channel + `product_import_runs` table + Import history page |
| Queue large imports, progress | `ValidateProductImport` / `ApplyProductImport` on the `imports` queue, `ProgressTracker`, status endpoint + `admin-product-import.js` |

---

## 2. How an import runs

```
 upload .xlsx ──► product_import_runs row (status: pending) + file in storage/app/imports/{id}/
      │
      ▼
 small file?  rows ≤ 100 AND downloads ≤ 25 (or QUEUE_CONNECTION=sync)
  ├─ yes ─► validate inline (request)
  └─ no  ─► status: queued ─► ValidateProductImport on the `imports` queue
      │
      ▼
 VALIDATE (dry run) ─ reads the sheet in chunks, plans every row, downloads new images to a PRIVATE temp store.
      │               Writes NOTHING to products / product_images.
      ├─► status: invalid  (any error)  ─► errors listed row by row, downloadable; nothing changed
      └─► status: ready    ─► review screen: N new · N updated · N unchanged · N images
              │
              │  admin confirms  (or "import straight away" was ticked)
              ▼
 APPLY ─ rows ≤ 500 → inline; more → ApplyProductImport on the queue
      │  re-reads the sheet, re-plans each row, then persists inside ONE DB::transaction
      ├─► any failure ─► ROLLBACK, delete files moved during the transaction, status: failed
      └─► commit ─► status: completed, temp images removed
```

Status machine (`App\Models\ProductImportRun`):

```
pending ─► (queued) ─► validating ─► invalid ─────────────► cancelled
                                 └─► ready ─► (queued) ─► applying ─► completed
                                        └────────────────────────────► failed
                                        └─► cancelled
```

Design decisions worth knowing:

- **Two passes over the same code.** Validation and apply both go through `RowProcessor::plan()`; only `persist()` writes.
  What the admin previewed is what runs. The apply pass re-plans every row against the **current** catalog: if something
  changed since the review so that a row is no longer valid (its slug was taken, its category deleted, its SKU now
  belongs to a trashed product…), the whole run aborts and rolls back (`ImportAbortedException`, reported with the row).
  Plain edits made by someone else in between are not detected — the sheet's non-blank values are applied on top, exactly as they
  would be by any later import.
- **Images are downloaded during validation**, not apply, so the transaction is database-only and short, and a broken
  image link is reported as a row error at review time rather than as a mid-import failure.
- **Progress lives in the cache** (`ProgressTracker`, TTL 120 min), not only in the database: rows updated inside the
  apply transaction are invisible to the status endpoint until commit, so DB-only progress would read 0 % for the whole
  apply. The DB receives phase boundaries and every 25th row outside the transaction.
- **Messages are written in the uploader's language** at validation time (`useLocale($run)` — this also makes queued
  workers, which have no session, use the right language). A run reviewed after switching language keeps its original
  wording; the column names in the error table are the (English) spreadsheet headings on purpose.
- **Inline threshold counts real downloads only.** URLs on this shop's own host (what an export contains) are matched
  to the product's stored files and never fetched, so they don't push a file onto the queue.

---

## 3. Code map

```
app/
├─ Http/
│  ├─ Controllers/Admin/ProductImportExportController.php   export, template, import wizard, status JSON, error report, history
│  └─ Requests/Admin/ProductExportRequest.php, ProductImportRequest.php
├─ Models/ProductImportRun.php                               one import: file, options, progress, outcome; prunable
├─ Jobs/ValidateProductImport.php, ApplyProductImport.php   ShouldQueue, $timeout 1800, $tries 1, failed() marks the run failed
├─ Services/ProductImport/
│  ├─ ProductImportService.php     orchestration: createRun / start / confirm / cancel / validate / apply / log
│  ├─ RowProcessor.php             ONE row → RowPlan (validation + decisions); persist() writes it
│  ├─ RowPlan.php, RunContext.php  per-row result / per-run state (seen SKUs, reserved slugs, counters, errors)
│  ├─ EntityResolver.php           category / brand lookup by id, slug or name
│  ├─ ImageFetcher.php             SSRF-safe downloader (see §7)
│  └─ TempImageStore.php           private per-run image cache: storage/app/imports/{id}/images/{sha1}.{ext}
├─ Imports/ProductSheetReader.php, ProductSheetWorker.php  chunked reading (ToCollection + WithChunkReading), header mapping
├─ Exports/ProductsExport.php, ProductsTemplateExport.php, ImportErrorsExport.php, Template/*   xlsx writers
└─ Support/
   ├─ ProductFilters.php           the products-list filters, shared by the index page and "export current filter"
   └─ ProductSheet/                Columns (registry), Column, RowNormalizer, ProductRowMapper, ProgressTracker, CellException
config/aroma.php                   admin.abilities, import.*
config/logging.php                 `imports` channel
resources/views/admin/products/    index.blade.php (buttons, selection), form.blade.php (sort order, meta keywords), import/{create,show,index}.blade.php
public/js/admin-product-import.js  progress polling
resources/lang/{en,ar}/admin.php   `products_io` group (UI, errors.*, image_errors.*, report.*, status.*)
database/migrations/2026_09_21_00000{1,2,3}_*   schema (see §4)
```

Routes (all inside the `auth` + `admin` group, registered **above** `Route::resource('products')` so `products/import`
isn't read as a `{product}` slug):

| Method | URL | Name | Ability |
|---|---|---|---|
| GET | `admin/products/template` | `admin.products.template` | `products.template` |
| GET, POST | `admin/products/export` | `admin.products.export` | `products.export` |
| GET | `admin/products/import` | `admin.products.import.create` | `products.import` |
| POST | `admin/products/import` | `admin.products.import.store` | `products.import` |
| GET | `admin/products/import/history` | `admin.products.import.history` | `products.import.history` |
| GET | `admin/products/import/{run}` | `admin.products.import.show` | `products.import.history` |
| GET | `admin/products/import/{run}/status` | `admin.products.import.status` | `products.import.history` |
| GET | `admin/products/import/{run}/errors` | `admin.products.import.errors` | `products.import.history` |
| POST | `admin/products/import/{run}/confirm` | `admin.products.import.confirm` | `products.import` |
| POST | `admin/products/import/{run}/cancel` | `admin.products.import.cancel` | `products.import` |

Export scopes: `scope=all` (default) · `scope=filtered` + the products-list filters (`q`, `category`, `active`) ·
`scope=selected` + `ids[]` (POSTed by the checkbox form on the products table). Soft-deleted products are never exported.

---

## 4. Data model

| Change | Migration |
|---|---|
| `products.sort_order` (unsigned int, default 0) and `products.meta_keywords` (text, nullable) | `…000001_add_sort_order_and_meta_keywords_to_products_table` |
| `product_images.source_url` (text) and `source_hash` (sha1, indexed) — where an image came from, so re-imports don't duplicate it | `…000002_add_source_columns_to_product_images_table` |
| `product_import_runs` — status, phase, options (json), counters, `errors`/`warnings`/`summary` (json), file path, user, timestamps | `…000003_create_product_import_runs_table` |

`meta_title`, `meta_description` already existed as translatable JSON columns; `meta_keywords` is a single language-neutral string.

---

## 5. The column registry

`App\Support\ProductSheet\Columns` is the **single source of truth**. It defines each column once — key, English header,
extra heading spellings (including Arabic), required-on-create, type, help text, width, group — and everything else reads it:

- the export header row and the template's header row (`Columns::headers()`)
- the importer's header mapping (`Columns::resolve($heading)`: case, spacing, punctuation and diacritic-insensitive; `name_ar`,
  `Product Name (Arabic)` and `اسم المنتج عربي` all resolve to the same column)
- the template's hover notes, required-column highlighting and Instructions sheet
- the docs (the table in ADMIN_GUIDE.md is generated from it)

Column order: `sku, name_ar, name_en, description_ar, description_en, short_description_ar, short_description_en, category,
price, sale_price, stock_quantity, status, meta_title_ar, meta_title_en, meta_description_ar, meta_description_en,
meta_keywords, slug, featured, sort_order, image_urls`, then the optional extras `brand, new_arrival, gift_eligible, scent_family`.

### Adding a column

1. Add a definition to `Columns::definitions()` (key, header, aliases, type, `help`, `group`, `width`; `required` only if a new
   product cannot exist without it). `ColumnsTest` fails if two columns claim the same heading spelling or one lacks help text.
2. **Import:** handle it in `RowProcessor` — read it with `$cell('key')`, parse with the matching `RowNormalizer` method
   (`money`, `integer`, `boolean`, `status`, `list`), add the value to `RowPlan` and to `persist()`. Follow the rule for existing
   products: a blank cell means "leave unchanged", `[clear]` empties an optional field.
3. **Export:** add it to `ProductRowMapper::map()` (and a number format in `ProductsExport` if it isn't text).
4. Add example values in `Exports/Template/TemplateExamples.php`, and error strings (if any) under `products_io.errors` in **both**
   `resources/lang/en/admin.php` and `ar/admin.php`.
5. Add a case to `ProductImportPipelineTest` and the round-trip test (`test_an_export_re_imports_as_all_unchanged`) will tell you if
   export and import disagree about the value.

---

## 6. Import semantics (the rules the code enforces)

| Topic | Rule |
|---|---|
| Key | **SKU** (trimmed). Unknown SKU → create; existing SKU → update. A SKU that belongs to a **soft-deleted** product is a row error (not a DB crash). Duplicate SKU within the file → error on the later rows. |
| Required to **create** | `sku`, `name_ar`, `name_en`, `category`, `price` (mirrors what the admin product form requires). Updates need only the SKU. |
| Blank cell / absent column | On an existing product: **unchanged**. On a new product: the default (stock 0, status active, gift-eligible yes, …). |
| `[clear]` | Empties an optional field on an existing product (sale price, descriptions, meta fields, image list, brand, …). Case-insensitive, exact cell content. |
| Price vs sale price | Sheet **Price** = regular price, **Sale Price** = what customers pay. Stored as `compare_at_price` = Price and `base_price` = Sale Price when a sale is set; otherwise `base_price` = Price. Sale Price must be lower than Price. `[clear]` on Sale Price returns the product to its regular price. |
| Numbers | `350`, `350.50`, `1,200.50`, `1.200,50`, `12,5`, Arabic-Indic digits (`٣٥٠`), currency words (`SAR`, `ريال`, `﷼`) — rounded to 2 dp. |
| Yes/no, status | `yes/no/true/false/1/0/y/n/on/off/نعم/لا`; status `active/inactive` (+ `enabled/disabled/draft/hidden/published`, `نشط/غير نشط/فعال/معطل`). |
| Category / brand | Numeric ID, slug, or name in either language, matched ignoring case, spacing, tashkeel and alef/yeh variants. Unknown or ambiguous → error. Never auto-created. |
| Slug | Given: normalised, must be unique across **all** products including soft-deleted and earlier rows of the same file. Missing on create: generated from the English name (`-2`, `-3` suffix on collision, reserved in memory across the file so two new rows can't pick the same one). Missing on update: unchanged. |
| Stock | Integer ≥ 0. Products **with variants** keep stock per variant, so the cell is ignored with a warning. |
| Images | `|` or newline separated `http(s)` URLs, max 10 per product, first = primary. **Default appends**: an image already on the product (same source URL hash, or the shop's own storage URL — i.e. an export re-imported) is matched and not duplicated. Options: *replace* (remove images not listed, re-order) and *ignore image failures* (import the product without the broken image, as a warning). |
| Variants, options | Never touched. |
| Unknown columns | Ignored with a warning (so a sheet with a notes column still imports). |
| Sheet choice | The sheet named `Products`, else the first sheet — so a downloaded export or template re-imports unchanged. |

A round trip is lossless by design: **export → import gives "0 new · 0 updated · N unchanged"**
(`ProductImportExportHttpTest::test_an_export_re_imports_as_all_unchanged`).

---

## 7. Image downloading and security

Admins paste arbitrary URLs into a spreadsheet and the server fetches them, so `ImageFetcher` treats every URL as hostile
(SSRF protection):

- only `http`/`https`, no credentials in the URL, ≤ 2048 characters, ports **80/443 only**
- the host is resolved and **every** returned address must be public (`FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE`): loopback,
  RFC1918, link-local (`169.254.169.254` cloud metadata), `0.0.0.0` are refused; an IP-literal host is judged as written
- the connection is **pinned** to the vetted address (`CURLOPT_RESOLVE`) so DNS can't change between check and fetch (rebinding)
- redirects are followed manually, at most 3, each hop re-validated
- 15 s timeout; the body is aborted mid-transfer once it passes the 5 MB cap (progress callback), not buffered first
- content is sniffed with `getimagesizefromstring`: only jpeg/png/webp/gif; **SVG is refused** (scriptable); the extension comes from
  the sniffed type, never from the URL
- stored as `products/<random>.<ext>` on the `public` disk, exactly like admin uploads, with `source_url` / `source_hash` recorded

The shop's **own** image URLs are recognised *before* any of these rules run (nothing is fetched for them), which is why an
export from a `localhost:8000` dev server re-imports cleanly.

Tuning lives in `config/aroma.php` → `import` (`max_images_per_product`, `image_max_mb`, `image_timeout`, `image_max_redirects`, `image_mimes`).

---

## 8. Permissions

Roles today are `customer | staff | admin`. Abilities are role-based Gates defined from **one config map**:

```php
// config/aroma.php
'admin' => ['abilities' => [
    'products.export'         => ['admin', 'staff'],
    'products.template'       => ['admin', 'staff'],
    'products.import'         => ['admin'],           // upload, confirm, cancel
    'products.import.history' => ['admin'],           // run pages, status, error report, history
]],
```

- `AuthServiceProvider` loops over the map and registers `Gate::define($ability, …)` reading the config **at check time**.
  (Ability names contain dots, so the map is indexed directly — `config("aroma.admin.abilities.{$ability}")` would treat the dot
  as a path and silently deny everyone. There is a test for exactly this.)
- Enforced three times: `can:` middleware on every route, `$this->authorize()` in every controller action, `@can` around
  every button/checkbox column in the views.
- To let staff import: add `'staff'` to `products.import` (and `products.import.history`). No code change.
- Customers never reach any of it (the `admin` middleware group). Note `BlockAdminShopping` bounces logged-in admins off the
  storefront — check storefront output as a guest.

---

## 9. Queue and workers

Small files run inline in the request — no worker needed. Bigger ones are queued on a dedicated **`imports`** queue so a long
import never delays emails/SMS on `default`:

| Setting (env) | Default | Meaning |
|---|---|---|
| `AROMA_IMPORT_SYNC_MAX_ROWS` | 100 | at or below this **and** the image limit → validate inline |
| `AROMA_IMPORT_SYNC_MAX_IMAGES` | 25 | downloads (off-shop image URLs) in the file |
| `AROMA_IMPORT_APPLY_SYNC_MAX_ROWS` | 500 | confirm applies inline up to this many rows (database-only work) |
| `AROMA_IMPORT_QUEUE` | `imports` | queue name |
| `AROMA_IMPORT_MAX_UPLOAD_MB` | 10 | upload cap (also needs `upload_max_filesize` / `post_max_size` ≥ this in php.ini) |
| `QUEUE_RETRY_AFTER` | 1900 | **must exceed the job timeout (1800 s)** or a second worker re-runs a job that is still busy |
| `QUEUE_CONNECTION` | `database` | `sync` runs everything inline (dev/tests) |

Run a worker for the `imports` queue (plus `default` if you use it):

```bash
php artisan queue:work --queue=imports,default --timeout=1800 --tries=1
```

Supervisor:

```ini
[program:aroma-queue]
command=php /var/www/aroma/artisan queue:work --queue=imports,default --timeout=1800 --tries=1 --sleep=3
directory=/var/www/aroma
user=www-data
autostart=true
autorestart=true
stopwaitsecs=1810
stdout_logfile=/var/log/aroma-queue.log
```

`stopwaitsecs` ≥ job timeout lets a deploy restart wait for a running import instead of killing it. After deploying code that
touches the import classes run `php artisan queue:restart`.

If no worker is running, a queued run stays in **Queued** and its page says so, with the exact command to start one.
A job that dies (timeout, fatal) reaches `failed()`, which marks the run **failed** with the reason; a hard kill mid-apply rolls the
transaction back, so the catalog is never left half-imported. `$tries = 1` on purpose: a failed import is re-uploaded by a person, not retried blindly.

The scheduler (the usual `* * * * * php artisan schedule:run` cron) runs `model:prune` daily for `ProductImportRun`: finished runs (invalid, ready-but-never-confirmed, completed,
failed, cancelled) older than `import.retention_days` (30) are deleted together with their uploaded file and temp images
(`imports/{id}/`).

---

## 10. Progress feedback

`GET admin/products/import/{run}/status` returns

```json
{ "id": 12, "status": "validating", "phase": "validate", "processed": 340, "total": 620, "percent": 54,
  "active": true, "finished": false, "error_count": 0, "summary": null, "message": null }
```

`public/js/admin-product-import.js` runs only while a run is *active* (pending/queued/validating/applying): it polls every 1.5 s,
moves the bar, and reloads the page when the status changes or the run stops being active — every state has its own
server-rendered screen (waiting-for-worker hint, review, errors, summary), so the JS stays tiny. Small inline imports finish
before the redirect and never poll.

---

## 11. Logging and audit

Everything goes to a dedicated **`imports`** daily channel (`storage/logs/imports-YYYY-MM-DD.log`, 30 days) and each run is a row in
`product_import_runs` (shown on **Products → Import → Import history**).

Events (message `product import <event>` or `product export`), always with `run_id` and `user_id`:
`upload`, `file rejected`, `validation queued`, `validation started`, `validation finished` (status, error count, summary),
`confirmed by admin`, `apply queued`, `apply started`, `completed` (summary), `cancelled`, `failed` (row, error, exception class — level `error`);
plus `product export` (scope, row count, filters) and `product template downloaded`.

```
[2026-09-21 11:27:59] local.INFO: product import upload {"run_id":2,"user_id":1,"file":"example-import-valid.xlsx","rows":8,"options":{"auto_apply":false,"replace_images":false,"ignore_image_failures":false,"locale":"en"}}
[2026-09-21 11:28:06] local.INFO: product import validation finished {"run_id":2,"user_id":1,"file":"example-import-valid.xlsx","status":"ready","errors":0,"summary":{"created":8,"updated":0,"unchanged":0,"images_new":11,"images_downloaded":11,"rows":8,"seconds":6.4}}
[2026-09-21 11:28:15] local.INFO: product import completed {"run_id":2,"user_id":1,"file":"example-import-valid.xlsx","summary":{"created":8,"updated":0,"unchanged":0,"images_added":11,"images_downloaded":11,"rows":8,"seconds":0.2}}
```

---

## 12. Storefront SEO

- Product and category pages: `<title>` = the locale's `meta_title`, falling back to `name — brand` exactly as before;
  `<meta name="description">` = `meta_description` (product falls back to the short description); `<meta name="keywords">` is
  emitted only when `meta_keywords` is set.
- **Blade gotcha (fixed here):** `@section('x', $value)` with a `null` value opens a *block* section that never closes and leaks an
  output buffer. Pass strings: `@section('meta_keywords', (string) $product->meta_keywords)`. A regression test asserts the
  output-buffer level is unchanged after rendering the product page.
- **Sort Order:** category listings (default sort) show ranked products first — `1, 2, 3…` in that order — then unranked
  products (`0`, the default) newest-first. An all-zero catalogue therefore looks exactly as before. Shopper-chosen sorts
  (price, newest) still win. Products and admin forms accept `sort_order` and `meta_keywords` (web and the JSON admin API share `ProductRequest`).

---

## 13. Tests

```bash
php artisan test                                   # whole suite
php artisan test tests/Feature/Admin/ProductImport # feature tests for this module
php artisan test tests/Unit/ProductSheet tests/Unit/ProductImport
```

| File | Covers |
|---|---|
| `tests/Concerns/BuildsProductWorkbooks.php` | Helper: builds **real `.xlsx` files** with PhpSpreadsheet (`workbook()`, `row()`, `readSheet()`, `pngBytes()`) — tests exercise the same reader production uses |
| `Feature/Admin/ProductImportPipelineTest` | create, update by SKU, blank = unchanged, unchanged detection, sale price rules, category/brand matching, row errors, slugs, missing SKU column, heading variants |
| `Feature/Admin/ProductImportImagesAndTransactionTest` | download/store/primary, no re-download, own-URL recognition, broken link vs ignore, **SSRF refusals**, SVG rejected, replace/append/`[clear]`, **rollback on mid-apply failure**, catalog drift abort, cancel, queued path, progress |
| `Feature/Admin/ProductImportExportHttpTest` | permissions (admin/staff/customer/guest), export scopes read back from the generated file, sale-price mapping, round trip, template sheets and that its examples validate, upload→review→confirm over HTTP, error report, status JSON, history, logging, storefront meta rendering, Sort Order, the admin form and JSON API |
| `Unit/ProductSheet/*`, `Unit/ProductImport/ImageFetcherTest` | normaliser (money/int/bool/list/matchKey), column registry integrity, URL identity and SSRF rules, image decoding |

Conventions: `Storage::fake('local'/'public')`, `Http::fake()` for downloads, a fake DNS resolver injected through
`$this->app->bind(ImageFetcher::class, …)` so no test touches the network, `phpunit.xml` runs `QUEUE_CONNECTION=sync`.
Known unrelated failure in the suite: `HomepageTest::test_categories_are_listed_on_the_homepage` (fails on a clean checkout too).

---

## 14. Operations and troubleshooting

| Symptom | Cause / fix |
|---|---|
| Run sits on **Queued** | No worker on the `imports` queue. `php artisan queue:work --queue=imports` (see §9). |
| Upload fails before validation with a size error | `upload_max_filesize` / `post_max_size` below `AROMA_IMPORT_MAX_UPLOAD_MB`. |
| Job re-runs / duplicate work | `QUEUE_RETRY_AFTER` ≤ job timeout. Keep it above 1800. |
| "Failed" with a timeout | Very large file: raise `$timeout` in the two jobs and `QUEUE_RETRY_AFTER` together, or split the file. Apply is a single transaction, so nothing was written. |
| Images "not a public address" / "port not supported" | Working as designed (SSRF guard). Host the image on a public http(s) URL on ports 80/443. |
| Images imported but not visible | `php artisan storage:link` (images are on the `public` disk, like admin uploads). |
| SKU `00123` became `123` in Excel | The template/export force the SKU column to *text*; in your own sheets format the column as Text before typing. |
| Export of tens of thousands of rows is slow | Exports stream synchronously in the request (chunked query). Fine to tens of thousands; beyond that queue it with `Excel::queue`. |
| Need to see what happened | `storage/logs/imports-*.log`, Import history page, `product_import_runs`. |

## 15. Known limitations (by design)

- `.xlsx` only (no CSV/ODS); no variant/option import; categories and brands are matched, never created.
- Error text is stored in the uploader's language at validation time (see §2).
- A run keeps at most 1000 errors on the row (`import.error_cap`); the downloadable report has all of them.
- Images are downloaded serially during validation; a file with hundreds of new images is slow (that is what the queue and the
  progress bar are for).
