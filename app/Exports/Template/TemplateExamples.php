<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Models\Brand;
use App\Models\Category;
use App\Support\ProductSheet\Columns;

/**
 * Three example rows for the template. Categories/brands come from the live
 * catalog so the examples validate against this shop. They are deliberately
 * `inactive` with EXAMPLE- SKUs and a placeholder image host: importing them
 * unchanged can never publish anything by accident (the image links fail
 * validation until they are replaced or the rows are deleted).
 */
final class TemplateExamples
{
    /** @return array<int,array<string,string|int|float|null>> three rows keyed by column */
    public static function rows(): array
    {
        $categories = Category::query()->orderBy('sort_order')->limit(2)->get();
        $first = $categories->get(0);
        $second = $categories->get(1) ?: $first;
        $brand = Brand::query()->orderBy('id')->first();

        $firstName = $first ? ($first->getTranslations('name')['en'] ?? 'Perfumes') : 'Perfumes';
        $secondName = $second ? ($second->getTranslations('name')['en'] ?? 'Abayas') : 'Abayas';
        $secondId = $second ? (int) $second->id : 1;

        $blank = array_fill_keys(Columns::keys(), null);

        return [
            array_merge($blank, [
                'sku' => 'EXAMPLE-OUD-100',
                'name_ar' => 'عود ملكي فاخر ١٠٠ مل',
                'name_en' => 'Royal Oud Perfume 100ml',
                'description_ar' => 'عطر شرقي فاخر بنفحات العود الكمبودي والعنبر، يدوم طويلاً ويمنحكِ حضورًا لا يُنسى.',
                'description_en' => 'A rich oriental perfume built on Cambodian oud and amber. Long-lasting and unmistakably elegant.',
                'short_description_ar' => 'عطر عود فاخر يدوم طويلاً.',
                'short_description_en' => 'A luxurious, long-lasting oud perfume.',
                'category' => $firstName,
                'price' => 450,
                'sale_price' => 380,
                'stock_quantity' => 25,
                'status' => 'inactive',
                'meta_title_ar' => 'عود ملكي فاخر ١٠٠ مل | أروما',
                'meta_title_en' => 'Royal Oud Perfume 100ml | Aroma',
                'meta_description_ar' => 'اكتشفي عطر العود الملكي من أروما: عود كمبودي وعنبر بثبات طويل. توصيل لجميع مدن المملكة.',
                'meta_description_en' => 'Discover Aroma Royal Oud: Cambodian oud and amber with long-lasting wear. Delivery across Saudi Arabia.',
                'meta_keywords' => 'oud, perfume, luxury, عود, عطر فاخر',
                'slug' => 'royal-oud-perfume-100ml',
                'featured' => 'yes',
                'sort_order' => 1,
                'image_urls' => 'https://example.com/images/royal-oud-front.jpg|https://example.com/images/royal-oud-side.jpg',
                'brand' => $brand ? ($brand->getTranslations('name')['en'] ?? null) : null,
                'new_arrival' => 'yes',
                'gift_eligible' => 'yes',
                'scent_family' => 'Oud',
            ]),
            // Arabic-only copy, no slug (auto-generated), no images, no SEO overrides.
            array_merge($blank, [
                'sku' => 'EXAMPLE-ABAYA-01',
                'name_ar' => 'عباية سوداء مطرزة',
                'name_en' => 'Black Embroidered Abaya',
                'description_ar' => 'عباية سوداء بتطريز يدوي على الأكمام، بقماش كريب فاخر.',
                'category' => $secondName,
                'price' => 690,
                'stock_quantity' => 8,
                'status' => 'inactive',
                'featured' => 'no',
                'sort_order' => 2,
            ]),
            // The minimum: only the required columns (category matched by its numeric ID here).
            array_merge($blank, [
                'sku' => 'EXAMPLE-MIN-01',
                'name_ar' => 'منتج تجريبي',
                'name_en' => 'Sample Product',
                'category' => $secondId,
                'price' => 99,
            ]),
        ];
    }
}
