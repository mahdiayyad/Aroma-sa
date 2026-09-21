<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

/**
 * The single source of truth for the product spreadsheet: which columns exist,
 * in what order, how each is spelled, what it means. The export writes these
 * headers, the template documents them, the importer maps incoming headings
 * through resolve(), and docs/product-import-export is generated from the same
 * definitions — so they can never drift apart.
 *
 * To add a column: add it here, then teach RowProcessor how to read/write it
 * (and ProductRowMapper how to export it).
 */
final class Columns
{
    /** Sheet name the importer looks for (falls back to the first sheet). */
    public const PRODUCTS_SHEET = 'Products';

    /** Separator inside the Image URLs cell (a newline works too). */
    public const IMAGE_SEPARATOR = '|';

    /** Cell value that clears an optional field on an existing product. */
    public const CLEAR_TOKEN = '[clear]';

    /** @var array<string,Column>|null */
    private static $columns;

    /** @var array<string,string>|null normalised heading => key */
    private static $lookup;

    /** @return array<string,Column> key => Column, in sheet order */
    public static function all(): array
    {
        if (self::$columns === null) {
            self::$columns = [];
            foreach (self::definitions() as $def) {
                self::$columns[$def['key']] = new Column($def);
            }
        }

        return self::$columns;
    }

    /** @return array<int,string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): Column
    {
        return self::all()[$key];
    }

    /** @return array<int,string> the header row, in order */
    public static function headers(): array
    {
        return array_values(array_map(function (Column $c) {
            return $c->header;
        }, self::all()));
    }

    /** @return array<int,string> columns that must be filled for a brand-new product */
    public static function requiredKeys(): array
    {
        return array_keys(array_filter(self::all(), function (Column $c) {
            return $c->requiredOnCreate;
        }));
    }

    /** Map a spreadsheet heading ("Product Name (Arabic)", "name_ar", "الوصف"…) to a column key. */
    public static function resolve(?string $heading): ?string
    {
        if (self::$lookup === null) {
            self::$lookup = [];
            foreach (self::all() as $column) {
                $spellings = array_merge([$column->key, $column->header], $column->aliases);
                foreach ($spellings as $spelling) {
                    self::$lookup[self::normalizeHeading($spelling)] = $column->key;
                }
            }
        }

        $normalized = self::normalizeHeading((string) $heading);

        return $normalized === '' ? null : (self::$lookup[$normalized] ?? null);
    }

    /** Case-, spacing-, punctuation- and diacritic-insensitive heading form. */
    public static function normalizeHeading(string $heading): string
    {
        $heading = mb_strtolower(trim($heading), 'UTF-8');
        $heading = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $heading) ?? $heading; // tashkeel + tatweel
        $heading = preg_replace('/[^\p{L}\p{N}]+/u', '_', $heading) ?? $heading;

        return trim($heading, '_');
    }

    /** @return array<int,array<string,mixed>> */
    private static function definitions(): array
    {
        return [
            ['key' => 'sku', 'header' => 'SKU', 'required' => true, 'group' => 'core', 'width' => 18,
                'aliases' => ['Product SKU', 'Item Code', 'Code', 'رمز المنتج', 'الكود', 'رقم المنتج'],
                'help' => 'Unique product code. This is the key: an existing SKU updates that product, an unknown SKU creates a new one.'],
            ['key' => 'name_ar', 'header' => 'Product Name (Arabic)', 'required' => true, 'group' => 'core', 'width' => 30,
                'aliases' => ['Name Arabic', 'Arabic Name', 'Title Arabic', 'الاسم بالعربية', 'اسم المنتج عربي'],
                'help' => 'Arabic product name (max 255 characters).'],
            ['key' => 'name_en', 'header' => 'Product Name (English)', 'required' => true, 'group' => 'core', 'width' => 30,
                'aliases' => ['Name English', 'English Name', 'Title English', 'الاسم بالانجليزية', 'الاسم بالإنجليزية'],
                'help' => 'English product name (max 255 characters).'],
            ['key' => 'description_ar', 'header' => 'Description (Arabic)', 'group' => 'core', 'width' => 45,
                'aliases' => ['Description Arabic', 'Desc Arabic', 'الوصف بالعربية', 'الوصف'],
                'help' => 'Full Arabic description. Plain text; line breaks are kept.'],
            ['key' => 'description_en', 'header' => 'Description (English)', 'group' => 'core', 'width' => 45,
                'aliases' => ['Description English', 'Desc English', 'الوصف بالانجليزية', 'الوصف بالإنجليزية'],
                'help' => 'Full English description. Plain text; line breaks are kept.'],
            ['key' => 'short_description_ar', 'header' => 'Short Description (Arabic)', 'group' => 'core', 'width' => 35,
                'aliases' => ['Short Description Arabic', 'Summary Arabic', 'الوصف المختصر بالعربية'],
                'help' => 'One or two sentences shown on listings. Also the fallback meta description.'],
            ['key' => 'short_description_en', 'header' => 'Short Description (English)', 'group' => 'core', 'width' => 35,
                'aliases' => ['Short Description English', 'Summary English', 'الوصف المختصر بالانجليزية'],
                'help' => 'One or two sentences shown on listings. Also the fallback meta description.'],
            ['key' => 'category', 'header' => 'Category', 'required' => true, 'type' => Column::REFERENCE, 'group' => 'core', 'width' => 22,
                'aliases' => ['Category Name', 'Category ID', 'Categories', 'التصنيف', 'القسم', 'الفئة'],
                'help' => 'Category ID, slug, or name (Arabic or English). See the Reference sheet.'],
            ['key' => 'price', 'header' => 'Price', 'required' => true, 'type' => Column::MONEY, 'group' => 'pricing', 'width' => 12,
                'aliases' => ['Regular Price', 'Base Price', 'Original Price', 'السعر'],
                'help' => 'Regular price in SAR (e.g. 350 or 350.50). If a Sale Price is set, this is the price shown struck through.'],
            ['key' => 'sale_price', 'header' => 'Sale Price', 'type' => Column::MONEY, 'group' => 'pricing', 'width' => 12,
                'aliases' => ['Discount Price', 'Special Price', 'Offer Price', 'سعر الخصم', 'سعر التخفيض'],
                'help' => 'Optional discounted price in SAR; must be lower than Price. It becomes the price customers pay. Write [clear] to remove an existing sale.'],
            ['key' => 'stock_quantity', 'header' => 'Stock Quantity', 'type' => Column::INTEGER, 'group' => 'pricing', 'width' => 14,
                'aliases' => ['Stock', 'Quantity', 'Qty', 'Inventory', 'المخزون', 'الكمية'],
                'help' => 'Whole number, 0 or more. Products that have variants keep stock per variant, so this is ignored for them.'],
            ['key' => 'status', 'header' => 'Status', 'type' => Column::STATUS, 'group' => 'pricing', 'width' => 12,
                'aliases' => ['Active', 'Is Active', 'الحالة'],
                'help' => '"active" (visible in the shop) or "inactive" (hidden). New products default to active.'],
            ['key' => 'meta_title_ar', 'header' => 'Meta Title (Arabic)', 'group' => 'seo', 'width' => 32,
                'aliases' => ['Meta Title Arabic', 'SEO Title Arabic', 'عنوان السيو بالعربية'],
                'help' => 'Browser/Google title on the Arabic page (max 255, ~60 recommended). Blank = "product name — brand".'],
            ['key' => 'meta_title_en', 'header' => 'Meta Title (English)', 'group' => 'seo', 'width' => 32,
                'aliases' => ['Meta Title English', 'SEO Title English', 'عنوان السيو بالانجليزية'],
                'help' => 'Browser/Google title on the English page (max 255, ~60 recommended).'],
            ['key' => 'meta_description_ar', 'header' => 'Meta Description (Arabic)', 'group' => 'seo', 'width' => 40,
                'aliases' => ['Meta Description Arabic', 'SEO Description Arabic', 'وصف السيو بالعربية'],
                'help' => 'Google snippet on the Arabic page (max 500, ~155 recommended).'],
            ['key' => 'meta_description_en', 'header' => 'Meta Description (English)', 'group' => 'seo', 'width' => 40,
                'aliases' => ['Meta Description English', 'SEO Description English', 'وصف السيو بالانجليزية'],
                'help' => 'Google snippet on the English page (max 500, ~155 recommended).'],
            ['key' => 'meta_keywords', 'header' => 'Meta Keywords', 'group' => 'seo', 'width' => 28,
                'aliases' => ['Keywords', 'SEO Keywords', 'الكلمات المفتاحية'],
                'help' => 'Comma-separated keywords (max 1000 characters).'],
            ['key' => 'slug', 'header' => 'Slug', 'group' => 'seo', 'width' => 26,
                'aliases' => ['URL Key', 'Permalink', 'URL Slug', 'الرابط'],
                'help' => 'URL part: lowercase letters, numbers and hyphens. Blank on a new product = generated from the English name. Must be unique.'],
            ['key' => 'featured', 'header' => 'Featured', 'type' => Column::BOOLEAN, 'group' => 'merch', 'width' => 10,
                'aliases' => ['Is Featured', 'مميز'],
                'help' => 'yes / no. Featured products are highlighted on the homepage.'],
            ['key' => 'sort_order', 'header' => 'Sort Order', 'type' => Column::INTEGER, 'group' => 'merch', 'width' => 11,
                'aliases' => ['Position', 'Order', 'ترتيب', 'الترتيب'],
                'help' => 'Whole number. 1 is shown first in category listings, then 2, 3…; leave 0 for products you do not rank (they follow the ranked ones, newest first).'],
            ['key' => 'image_urls', 'header' => 'Image URLs', 'type' => Column::LIST, 'group' => 'media', 'width' => 50,
                'aliases' => ['Images', 'Image URL', 'Image', 'Photos', 'الصور'],
                'help' => 'Public http(s) image links separated by | (or a new line). The first is the main image. Images are downloaded and stored on your site.'],
            ['key' => 'brand', 'header' => 'Brand', 'type' => Column::REFERENCE, 'group' => 'core', 'width' => 20,
                'aliases' => ['Brand Name', 'Brand ID', 'العلامة التجارية'],
                'help' => 'Optional. Brand ID, slug, or name. See the Reference sheet.'],
            ['key' => 'new_arrival', 'header' => 'New Arrival', 'type' => Column::BOOLEAN, 'group' => 'merch', 'width' => 12,
                'aliases' => ['Is New', 'Is New Arrival', 'New'],
                'help' => 'yes / no. Shown in the "New arrivals" section.'],
            ['key' => 'gift_eligible', 'header' => 'Gift Eligible', 'type' => Column::BOOLEAN, 'group' => 'merch', 'width' => 13,
                'aliases' => ['Is Gift Eligible', 'Gift'],
                'help' => 'yes / no. Whether the product can be sent as a gift. New products default to yes.'],
            ['key' => 'scent_family', 'header' => 'Scent Family', 'group' => 'merch', 'width' => 16,
                'aliases' => ['Scent', 'Fragrance Family'],
                'help' => 'Optional, perfumes only (e.g. Oud, Musk, Floral).'],
        ];
    }
}
