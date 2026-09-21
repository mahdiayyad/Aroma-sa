<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ProductSheet\CellException;
use App\Support\ProductSheet\Columns;
use App\Support\ProductSheet\RowNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Validates one spreadsheet row and turns it into a RowPlan (plan()), and later
 * writes a plan to the catalog (persist()).
 *
 * plan() never writes to products/images — during the review step it is the
 * whole importer; during apply the SAME plan() runs again inside the
 * transaction, so what was previewed is exactly what is written. Semantics:
 *
 *  - SKU decides create vs update; a blank cell on an existing product means
 *    "leave it alone" (write [clear] to empty an optional field);
 *  - Price is the regular price, Sale Price the discounted selling price
 *    (stored as base_price with Price kept as compare_at_price, matching how
 *    the storefront draws the strike-through);
 *  - all rules mirror the admin product form (both names, category, price…).
 */
class RowProcessor
{
    /** field => [locale => column key] */
    private const TRANSLATABLE = [
        'name'              => ['ar' => 'name_ar', 'en' => 'name_en'],
        'description'       => ['ar' => 'description_ar', 'en' => 'description_en'],
        'short_description' => ['ar' => 'short_description_ar', 'en' => 'short_description_en'],
        'meta_title'        => ['ar' => 'meta_title_ar', 'en' => 'meta_title_en'],
        'meta_description'  => ['ar' => 'meta_description_ar', 'en' => 'meta_description_en'],
    ];

    private const MAX_LENGTH = ['name' => 255, 'meta_title' => 255, 'meta_description' => 500, 'description' => 65000, 'short_description' => 65000];

    private const MAX_PRICE = 99999999.99;

    /** @var ImageFetcher */
    private $fetcher;

    /** @var TempImageStore */
    private $temp;

    public function __construct(ImageFetcher $fetcher, TempImageStore $temp)
    {
        $this->fetcher = $fetcher;
        $this->temp = $temp;
    }

    /* Planning (validation) ------------------------------------------------ */

    /** @param array<string,string|null> $cells column key => normalised cell */
    public function plan(int $row, array $cells, RunContext $ctx): RowPlan
    {
        $plan = new RowPlan($row);
        $cell = function (string $key) use ($cells, $ctx) {
            return $ctx->hasColumn($key) ? ($cells[$key] ?? null) : null;
        };

        // --- SKU: the key of the whole row --------------------------------
        $sku = $cell('sku');

        if ($sku === null) {
            $plan->error('sku', $this->msg('sku_required'));

            return $plan;
        }

        if (mb_strlen($sku) > 100) {
            $plan->error('sku', $this->msg('too_long', ['max' => 100]), $sku);

            return $plan;
        }

        $plan->sku = $sku;
        $skuKey = mb_strtolower($sku, 'UTF-8');

        if (isset($ctx->seenSkus[$skuKey])) {
            $plan->error('sku', $this->msg('duplicate_sku', ['row' => $ctx->seenSkus[$skuKey]]), $sku);

            return $plan;
        }

        $ctx->seenSkus[$skuKey] = $row;

        $existing = Product::withTrashed()->where('sku', $sku)->first();

        if ($existing && $existing->trashed()) {
            $plan->error('sku', $this->msg('sku_trashed'), $sku);

            return $plan;
        }

        $plan->action = $existing ? RowPlan::UPDATE : RowPlan::CREATE;
        $plan->productId = $existing ? (int) $existing->id : null;

        if (stripos($sku, 'EXAMPLE-') === 0) {
            $plan->warn('sku', $this->msg('example_row'), $sku);
        }

        if (! $existing) {
            foreach (Columns::requiredKeys() as $key) {
                if ($key !== 'sku' && ($cell($key) === null || RowNormalizer::isClear($cell($key)))) {
                    $plan->error($key, $this->msg('required_for_new', ['field' => Columns::get($key)->header]));
                }
            }
        }

        $this->planTexts($plan, $cell);
        $this->planReferences($plan, $cell, $ctx);
        $this->planPricing($plan, $cell, $existing);
        $this->planStockAndFlags($plan, $cell, $existing);
        $this->planSlug($plan, $cell, $existing, $ctx);
        $this->planImages($plan, $cell, $existing, $ctx);

        if ($existing && ! $plan->hasErrors() && ! $this->hasChanges($plan, $existing)) {
            $plan->action = RowPlan::UNCHANGED;
        }

        return $plan;
    }

    /** @param callable $cell */
    private function planTexts(RowPlan $plan, callable $cell): void
    {
        foreach (self::TRANSLATABLE as $field => $locales) {
            foreach ($locales as $locale => $columnKey) {
                $value = $cell($columnKey);

                if ($value === null) {
                    continue;
                }

                if (RowNormalizer::isClear($value)) {
                    if ($field === 'name') {
                        $plan->error($columnKey, $this->msg('cannot_clear', ['field' => Columns::get($columnKey)->header]));
                    } else {
                        $plan->translations[$field][$locale] = null;
                    }

                    continue;
                }

                if (mb_strlen($value) > self::MAX_LENGTH[$field]) {
                    $plan->error($columnKey, $this->msg('too_long', ['max' => self::MAX_LENGTH[$field]]), Str::limit($value, 40));

                    continue;
                }

                $plan->translations[$field][$locale] = $value;
            }
        }

        foreach (['meta_keywords' => 1000, 'scent_family' => 100] as $key => $max) {
            $value = $cell($key);

            if ($value === null) {
                continue;
            }

            if (RowNormalizer::isClear($value)) {
                $plan->attributes[$key] = null;
            } elseif (mb_strlen($value) > $max) {
                $plan->error($key, $this->msg('too_long', ['max' => $max]), Str::limit($value, 40));
            } else {
                $plan->attributes[$key] = $value;
            }
        }
    }

    /** @param callable $cell */
    private function planReferences(RowPlan $plan, callable $cell, RunContext $ctx): void
    {
        $category = $cell('category');

        if ($category !== null) {
            if (RowNormalizer::isClear($category)) {
                $plan->error('category', $this->msg('cannot_clear', ['field' => Columns::get('category')->header]));
            } else {
                $found = $ctx->categories->find($category);

                if (isset($found['id'])) {
                    $plan->attributes['category_id'] = $found['id'];
                } else {
                    $plan->error('category', $this->msg('category_'.$found['error'], ['value' => $category]), $category);
                }
            }
        }

        $brand = $cell('brand');

        if ($brand !== null) {
            if (RowNormalizer::isClear($brand)) {
                $plan->attributes['brand_id'] = null;
            } else {
                $found = $ctx->brands->find($brand);

                if (isset($found['id'])) {
                    $plan->attributes['brand_id'] = $found['id'];
                } else {
                    $plan->error('brand', $this->msg('brand_'.$found['error'], ['value' => $brand]), $brand);
                }
            }
        }
    }

    /** @param callable $cell */
    private function planPricing(RowPlan $plan, callable $cell, ?Product $existing): void
    {
        $priceCell = $cell('price');
        $saleCell = $cell('sale_price');

        $price = null;
        $sale = null;
        $clearSale = false;
        $failed = false;

        if ($priceCell !== null) {
            if (RowNormalizer::isClear($priceCell)) {
                $plan->error('price', $this->msg('cannot_clear', ['field' => Columns::get('price')->header]));
                $failed = true;
            } else {
                $price = $this->parse($plan, 'price', $priceCell, [RowNormalizer::class, 'money']);
                $failed = $price === null;

                if ($price !== null && $price > self::MAX_PRICE) {
                    $plan->error('price', $this->msg('price_range'), $priceCell);
                    $failed = true;
                }
            }
        }

        if ($saleCell !== null) {
            if (RowNormalizer::isClear($saleCell)) {
                $clearSale = true;
            } else {
                $sale = $this->parse($plan, 'sale_price', $saleCell, [RowNormalizer::class, 'money']);
                $failed = $failed || $sale === null;
            }
        }

        if ($failed || ($priceCell === null && $saleCell === null)) {
            return;
        }

        $existingOnSale = $existing && $existing->isOnSale();
        $regular = $price ?? ($existing ? ($existingOnSale ? (float) $existing->compare_at_price : (float) $existing->base_price) : null);
        $effectiveSale = $clearSale ? null : ($sale ?? ($existingOnSale ? (float) $existing->base_price : null));

        if ($regular === null) {
            return;
        }

        if ($effectiveSale !== null) {
            if ($effectiveSale >= $regular) {
                $plan->error('sale_price', $this->msg('sale_not_lower'), $saleCell ?? (string) $effectiveSale);

                return;
            }

            $plan->attributes['base_price'] = $effectiveSale;
            $plan->attributes['compare_at_price'] = $regular;

            return;
        }

        $plan->attributes['base_price'] = $regular;
        $plan->attributes['compare_at_price'] = null;
    }

    /** @param callable $cell */
    private function planStockAndFlags(RowPlan $plan, callable $cell, ?Product $existing): void
    {
        $stock = $cell('stock_quantity');

        if ($stock !== null) {
            if (RowNormalizer::isClear($stock)) {
                $plan->error('stock_quantity', $this->msg('cannot_clear', ['field' => Columns::get('stock_quantity')->header]));
            } else {
                $n = $this->parse($plan, 'stock_quantity', $stock, [RowNormalizer::class, 'integer']);

                if ($n !== null && $n > 4294967295) {
                    $plan->error('stock_quantity', $this->msg('number_too_large'), $stock);
                } elseif ($n !== null && $existing && $existing->has_variants) {
                    $plan->warn('stock_quantity', $this->msg('stock_ignored_variants'), $stock);
                } elseif ($n !== null) {
                    $plan->attributes['stock_quantity'] = $n;
                }
            }
        } elseif (! $existing) {
            $plan->attributes['stock_quantity'] = 0;
        }

        $status = $cell('status');

        if ($status !== null && ! RowNormalizer::isClear($status)) {
            $active = $this->parse($plan, 'status', $status, [RowNormalizer::class, 'status']);

            if ($active !== null) {
                $plan->attributes['is_active'] = $active;
            }
        } elseif (! $existing) {
            $plan->attributes['is_active'] = true;
        }

        foreach (['featured' => ['is_featured', false], 'new_arrival' => ['is_new_arrival', false], 'gift_eligible' => ['is_gift_eligible', true]] as $key => [$attribute, $default]) {
            $value = $cell($key);

            if ($value !== null && ! RowNormalizer::isClear($value)) {
                $parsed = $this->parse($plan, $key, $value, [RowNormalizer::class, 'boolean']);

                if ($parsed !== null) {
                    $plan->attributes[$attribute] = $parsed;
                }
            } elseif (! $existing) {
                $plan->attributes[$attribute] = $default;
            }
        }

        $order = $cell('sort_order');

        if ($order !== null && ! RowNormalizer::isClear($order)) {
            $n = $this->parse($plan, 'sort_order', $order, [RowNormalizer::class, 'integer']);

            if ($n !== null && $n > 4294967295) {
                $plan->error('sort_order', $this->msg('number_too_large'), $order);
            } elseif ($n !== null) {
                $plan->attributes['sort_order'] = $n;
            }
        } elseif (! $existing) {
            $plan->attributes['sort_order'] = 0;
        }
    }

    /** @param callable $cell */
    private function planSlug(RowPlan $plan, callable $cell, ?Product $existing, RunContext $ctx): void
    {
        $given = $cell('slug');

        if ($given !== null) {
            if (RowNormalizer::isClear($given)) {
                $plan->error('slug', $this->msg('cannot_clear', ['field' => Columns::get('slug')->header]));

                return;
            }

            $slug = Str::slug($given);

            if ($slug === '' || mb_strlen($slug) > 255) {
                $plan->error('slug', $this->msg('slug_invalid'), $given);

                return;
            }

            if ($existing && $slug === $existing->slug) {
                return; // unchanged
            }

            $owner = DB::table('products')
                ->where('slug', $slug)
                ->when($existing, function ($q) use ($existing) {
                    $q->where('id', '!=', $existing->id);
                })
                ->first(['id', 'sku']);

            if ($owner) {
                $plan->error('slug', $this->msg('slug_taken', ['owner' => $owner->sku ?: '#'.$owner->id]), $slug);

                return;
            }

            if (isset($ctx->reservedSlugs[$slug])) {
                $plan->error('slug', $this->msg('slug_duplicate_in_file', ['row' => $ctx->reservedSlugs[$slug]]), $slug);

                return;
            }

            $ctx->reservedSlugs[$slug] = $plan->row;
            $plan->attributes['slug'] = $slug;

            if ($existing) {
                $plan->warn('slug', $this->msg('slug_changed', ['old' => $existing->slug]), $slug);
            }

            return;
        }

        if ($existing) {
            return;
        }

        // New product without a slug: derive one from the English name (Arabic as a fallback).
        $base = Str::slug((string) $cell('name_en')) ?: Str::slug((string) $cell('name_ar')) ?: 'product-'.Str::lower(Str::random(6));
        $candidate = $base;
        $i = 1;

        while (isset($ctx->reservedSlugs[$candidate]) || DB::table('products')->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.(++$i);
        }

        $ctx->reservedSlugs[$candidate] = $plan->row;
        $plan->attributes['slug'] = $candidate;
    }

    /** @param callable $cell */
    private function planImages(RowPlan $plan, callable $cell, ?Product $existing, RunContext $ctx): void
    {
        $value = $cell('image_urls');

        if ($value === null) {
            return;
        }

        $existingImages = $existing ? $existing->images()->get() : new Collection();

        if (RowNormalizer::isClear($value)) {
            $plan->clearImages = true;
            $plan->imagesChange = $existingImages->isNotEmpty();

            return;
        }

        $urls = RowNormalizer::list($value);
        $max = (int) config('aroma.import.max_images_per_product', 10);

        if (count($urls) > $max) {
            $plan->error('image_urls', $this->msg('too_many_images', ['max' => $max]));

            return;
        }

        $matchedIds = [];

        foreach ($urls as $url) {
            // The product's own images come first: nothing is fetched for them, so the
            // download rules (public host, standard port…) don't apply — an export from
            // a staging or dev server (http://localhost:8000/storage/…) must re-import.
            if ($match = $this->matchExisting($url, $existingImages)) {
                $matchedIds[] = $match->id;
                $plan->imageUrls[] = $url;

                continue;
            }

            try {
                $this->fetcher->assertAcceptableUrl($url);
            } catch (ImageFetchException $e) {
                $plan->error('image_urls', $this->imageMessage($e), $url);

                continue;
            }

            if (! $ctx->isValidating()) {
                $plan->imageUrls[] = $url; // apply pass: bytes come from the temp store (or are fetched then)
                $plan->newImages++;

                continue;
            }

            $hash = ImageFetcher::hash($url);

            if (! $this->temp->has($ctx->run->id, $hash)) {
                try {
                    $file = $this->fetcher->fetch($url);
                    $this->temp->put($ctx->run->id, $hash, $file['bytes'], $file['ext']);
                    $ctx->counts['images_downloaded']++;
                } catch (ImageFetchException $e) {
                    if ($ctx->ignoreImageFailures) {
                        $plan->warn('image_urls', $this->msg('image_skipped', ['reason' => $this->imageMessage($e)]), $url);
                    } else {
                        $plan->error('image_urls', $this->msg('image_failed', ['reason' => $this->imageMessage($e)]), $url);
                    }

                    continue;
                }
            }

            $plan->imageUrls[] = $url;
            $plan->newImages++;
        }

        // A "replace" run also removes images that aren't listed and re-orders the rest.
        $removes = $ctx->replaceImages && $existingImages->pluck('id')->diff($matchedIds)->isNotEmpty();
        $reorders = $ctx->replaceImages && $matchedIds !== $existingImages->pluck('id')->intersect($matchedIds)->values()->all();

        $plan->imagesChange = $plan->newImages > 0 || $removes || $reorders;
    }

    /** Would writing this plan change anything for an existing product? */
    private function hasChanges(RowPlan $plan, Product $existing): bool
    {
        // Plain columns: apply to the (unsaved) model and let Eloquent's cast-aware dirty check decide.
        foreach ($plan->attributes as $key => $value) {
            $existing->setAttribute($key, $value);
        }

        if ($plan->attributes !== [] && $existing->isDirty(array_keys($plan->attributes))) {
            return true;
        }

        // Translations: compare decoded values (MySQL re-formats JSON, so a string compare would always differ).
        foreach ($plan->translations as $field => $locales) {
            $current = $existing->getTranslations($field);

            foreach ($locales as $locale => $value) {
                if (($current[$locale] ?? null) !== $value) {
                    return true;
                }
            }
        }

        return $plan->imagesChange;
    }

    /* Persisting ----------------------------------------------------------- */

    /** Write a valid plan to the catalog. Must run inside the apply transaction. */
    public function persist(RowPlan $plan, RunContext $ctx): void
    {
        if ($plan->action === RowPlan::UNCHANGED) {
            $ctx->counts['unchanged']++;

            return;
        }

        $product = $plan->action === RowPlan::CREATE ? new Product() : Product::query()->findOrFail($plan->productId);

        foreach ($plan->attributes as $key => $value) {
            $product->setAttribute($key, $value);
        }

        if ($plan->action === RowPlan::CREATE) {
            $product->setAttribute('sku', $plan->sku);
            $product->setAttribute('currency', 'SAR');
        }

        foreach ($plan->translations as $field => $locales) {
            foreach ($locales as $locale => $value) {
                $this->writeTranslation($product, $field, $locale, $value);
            }
        }

        $product->save();

        $this->persistImages($product, $plan, $ctx);

        $ctx->counts[$plan->action === RowPlan::CREATE ? 'created' : 'updated']++;
    }

    private function writeTranslation(Product $product, string $field, string $locale, ?string $value): void
    {
        if ($value !== null) {
            $product->setTranslation($field, $locale, $value);

            return;
        }

        $translations = $product->getTranslations($field);
        unset($translations[$locale]);
        $product->setAttribute($field, $translations === [] ? null : $translations);
    }

    private function persistImages(Product $product, RowPlan $plan, RunContext $ctx): void
    {
        $existing = $product->images()->get();

        if ($plan->clearImages) {
            foreach ($existing as $image) {
                $this->discard($image, $ctx);
            }

            return;
        }

        if ($plan->imageUrls === []) {
            return;
        }

        $kept = [];
        $position = 0;
        $maxSort = (int) $existing->max('sort_order');

        foreach ($plan->imageUrls as $url) {
            $position++;

            if ($match = $this->matchExisting($url, $existing)) {
                $kept[] = $match->id;

                if ($ctx->replaceImages) {
                    $match->update(['sort_order' => $position]);
                }

                continue;
            }

            $file = $this->acquire($ctx, $url);

            if ($file === null) {
                continue;
            }

            $path = 'products/'.Str::random(40).'.'.$file['ext'];
            Storage::disk('public')->put($path, $file['bytes']);
            $ctx->createdFiles[] = $path;

            $image = $product->images()->create([
                'disk'        => 'public',
                'path'        => $path,
                'source_url'  => $url,
                'source_hash' => ImageFetcher::hash($url),
                'sort_order'  => $ctx->replaceImages ? $position : ++$maxSort,
                'is_primary'  => false,
            ]);

            $kept[] = $image->id;
            $ctx->counts['images_added']++;
        }

        if ($ctx->replaceImages) {
            foreach ($existing as $image) {
                if (! in_array($image->id, $kept, true)) {
                    $this->discard($image, $ctx);
                }
            }
        }

        $this->normalisePrimary($product, $ctx->replaceImages);
    }

    /** Exactly one primary image: the first by order on a replace, otherwise only if none is set yet. */
    private function normalisePrimary(Product $product, bool $forceFirst): void
    {
        $images = $product->images()->orderBy('sort_order')->orderBy('id')->get();

        if ($images->isEmpty()) {
            return;
        }

        if (! $forceFirst && $images->contains('is_primary', true)) {
            return;
        }

        foreach ($images as $index => $image) {
            $should = $index === 0;

            if ((bool) $image->is_primary !== $should) {
                $image->update(['is_primary' => $should]);
            }
        }
    }

    private function discard(ProductImage $image, RunContext $ctx): void
    {
        // Root-relative / absolute paths (the seeded placeholder) never lived on a disk.
        if ($image->path && ! preg_match('#^(https?:)?/#', $image->path)) {
            $ctx->filesToDelete[] = [$image->disk, $image->path];
        }

        $image->delete();
        $ctx->counts['images_removed']++;
    }

    /** @return array{bytes:string,mime:string,ext:string}|null null when a failure is being ignored */
    private function acquire(RunContext $ctx, string $url): ?array
    {
        $hash = ImageFetcher::hash($url);

        if ($file = $this->temp->get($ctx->run->id, $hash)) {
            return $file;
        }

        try {
            return $this->fetcher->fetch($url);
        } catch (ImageFetchException $e) {
            if ($ctx->ignoreImageFailures) {
                return null;
            }

            throw new RuntimeException($this->msg('image_failed', ['reason' => $this->imageMessage($e)]).' ('.$url.')');
        }
    }

    /**
     * An image already on this product: imported from the same URL before, the
     * product's own stored file (an export's URL), or the same file exported
     * from another environment (same /storage/… path).
     */
    private function matchExisting(string $url, Collection $images): ?ProductImage
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $hash = ImageFetcher::hash($url);
        $urlPath = (string) parse_url($url, PHP_URL_PATH);

        foreach ($images as $image) {
            if ($image->source_hash !== null && $image->source_hash === $hash) {
                return $image;
            }

            $own = $image->url();
            $ownAbsolute = preg_match('#^https?://#i', $own) ? $own : url($own);

            if (ImageFetcher::hash($ownAbsolute) === $hash) {
                return $image;
            }

            if ($image->disk === 'public' && $urlPath !== '' && substr($urlPath, -strlen('/storage/'.$image->path)) === '/storage/'.$image->path) {
                return $image;
            }
        }

        return null;
    }

    /* Helpers -------------------------------------------------------------- */

    /** @return mixed|null null (and an error on the plan) when the cell can't be parsed */
    private function parse(RowPlan $plan, string $column, string $value, callable $parser)
    {
        try {
            return $parser($value);
        } catch (CellException $e) {
            $plan->error($column, $this->msg($e->errorKey, $e->replace), $value);

            return null;
        }
    }

    /** @param array<string,mixed> $replace */
    private function msg(string $key, array $replace = []): string
    {
        return (string) __('admin.products_io.errors.'.$key, $replace);
    }

    private function imageMessage(ImageFetchException $e): string
    {
        return (string) __('admin.products_io.image_errors.'.$e->errorKey, $e->replace);
    }
}
