<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

use App\Models\Product;

/**
 * Product -> spreadsheet row, in Columns order. Shared by the export (real
 * data) and the round-trip tests, so what is exported is exactly what the
 * importer reads back.
 *
 * Price mapping mirrors the storefront: `base_price` is what customers pay and
 * `compare_at_price` the struck-through original. So a product on sale
 * exports Price = the original (compare_at) and Sale Price = the selling price;
 * a regular product exports Price = base_price and an empty Sale Price.
 */
final class ProductRowMapper
{
    /** @return array<string,string|int|float|null> column key => cell value */
    public static function row(Product $product): array
    {
        $onSale = $product->isOnSale();

        return [
            'sku'                  => $product->sku,
            'name_ar'              => self::t($product, 'name', 'ar'),
            'name_en'              => self::t($product, 'name', 'en'),
            'description_ar'       => self::t($product, 'description', 'ar'),
            'description_en'       => self::t($product, 'description', 'en'),
            'short_description_ar' => self::t($product, 'short_description', 'ar'),
            'short_description_en' => self::t($product, 'short_description', 'en'),
            'category'             => $product->category ? self::label($product->category->getTranslations('name')) : null,
            'price'                => (float) ($onSale ? $product->compare_at_price : $product->base_price),
            'sale_price'           => $onSale ? (float) $product->base_price : null,
            'stock_quantity'       => $product->has_variants ? (int) $product->variants->sum('stock_quantity') : (int) $product->stock_quantity,
            'status'               => $product->is_active ? 'active' : 'inactive',
            'meta_title_ar'        => self::t($product, 'meta_title', 'ar'),
            'meta_title_en'        => self::t($product, 'meta_title', 'en'),
            'meta_description_ar'  => self::t($product, 'meta_description', 'ar'),
            'meta_description_en'  => self::t($product, 'meta_description', 'en'),
            'meta_keywords'        => $product->meta_keywords,
            'slug'                 => $product->slug,
            'featured'             => $product->is_featured ? 'yes' : 'no',
            'sort_order'           => (int) $product->sort_order,
            'image_urls'           => self::imageUrls($product),
            'brand'                => $product->brand ? self::label($product->brand->getTranslations('name')) : null,
            'new_arrival'          => $product->is_new_arrival ? 'yes' : 'no',
            'gift_eligible'        => $product->is_gift_eligible ? 'yes' : 'no',
            'scent_family'         => $product->scent_family,
        ];
    }

    private static function t(Product $product, string $field, string $locale): ?string
    {
        $value = $product->getTranslations($field)[$locale] ?? null;

        return $value === '' ? null : $value;
    }

    /** English name preferred (stable to type back in), Arabic as the fallback. */
    private static function label(array $names): ?string
    {
        return ($names['en'] ?? '') !== '' ? $names['en'] : (($names['ar'] ?? '') !== '' ? $names['ar'] : null);
    }

    /** Absolute image URLs, main image first, joined with the sheet's separator. */
    private static function imageUrls(Product $product): ?string
    {
        $images = $product->images->sortBy([['is_primary', 'desc'], ['sort_order', 'asc'], ['id', 'asc']]);

        if ($images->isEmpty()) {
            return null;
        }

        return $images->map(function ($image) {
            $url = $image->url();

            return preg_match('#^https?://#i', $url) ? $url : url($url);
        })->implode(Columns::IMAGE_SEPARATOR);
    }
}
