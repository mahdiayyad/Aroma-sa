<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * QA-only fixtures for exercising the variant picker (size/volume buttons,
 * the "selected" blue highlight, the disabled/sold-out state, and the
 * discount badge) end to end. Not wired into DatabaseSeeder::run() — run it
 * on demand: `php artisan db:seed --class=TestVariantProductSeeder`.
 *
 * Both products are tagged with a "QATEST-" SKU prefix and a "TEST —" name
 * prefix so they're trivially greppable and safe to delete once real variant
 * data exists: `Product::where('sku', 'like', 'QATEST-%')->delete()`.
 *
 * The abaya one lands in the currently-active "abayas" category, so it shows
 * up in normal browsing/search. The perfume one lands in the (currently
 * deactivated, see AbayaCatalogSeeder) "perfumes" category, so it's reachable
 * only via its direct product-page link — deliberately, to avoid mixing test
 * data into the live nav/category listings.
 */
class TestVariantProductSeeder extends Seeder
{
    public function run(): void
    {
        $brandId = Brand::where('name->en', 'Aroma Signature')->value('id')
            ?? Brand::query()->value('id');

        $this->seedSizeAbaya($brandId);
        $this->seedVolumePerfume($brandId);
    }

    /** Size variants (S/M/L/XL), one of them on sale — visible in normal browsing. */
    private function seedSizeAbaya(?int $brandId): void
    {
        $category = Category::where('slug', 'abayas')->first();
        if (! $category) {
            return;
        }

        $basePrice = 890.0;

        $product = Product::updateOrCreate(
            ['slug' => 'test-abaya-sizes'],
            [
                'category_id'        => $category->id,
                'brand_id'            => $brandId,
                'name'                => ['ar' => 'TEST — عباية بمقاسات', 'en' => 'TEST — Abaya (Sizes)'],
                'short_description'   => [
                    'ar' => 'منتج تجريبي لاختبار اختيار المقاس — غير معروض للبيع الفعلي.',
                    'en' => 'QA fixture for testing the size picker — not a real product for sale.',
                ],
                'description' => [
                    'ar' => 'منتج تجريبي بأربعة مقاسات (S, M, L, XL) لاختبار واجهة اختيار المقاس وحالة "غير متوفر".',
                    'en' => 'QA fixture with four sizes (S, M, L, XL) for testing the size-picker UI and the sold-out state.',
                ],
                'sku'                 => 'QATEST-001',
                'base_price'          => $basePrice,
                'compare_at_price'    => round($basePrice * 1.2, 2),
                'currency'            => 'SAR',
                'stock_quantity'      => 0, // variants carry stock, per the has_variants convention
                'has_variants'        => true,
                'scent_family'        => null,
                'is_active'           => true,
                'is_featured'         => false,
                'is_new_arrival'      => false,
                'is_gift_eligible'    => true,
            ]
        );

        $product->images()->updateOrCreate(
            ['path' => '/images/placeholder.svg'],
            ['disk' => 'public', 'alt' => ['ar' => 'TEST — عباية بمقاسات', 'en' => 'TEST — Abaya (Sizes)'], 'is_primary' => true, 'sort_order' => 0]
        );

        $sizes = [
            ['label' => 'S',  'mult' => 1.0,  'stock' => 12],
            ['label' => 'M',  'mult' => 1.0,  'stock' => 15],
            ['label' => 'L',  'mult' => 1.05, 'stock' => 8],
            ['label' => 'XL', 'mult' => 1.1,  'stock' => 0], // deliberately sold out — tests the disabled variant state
        ];

        foreach ($sizes as $i => $size) {
            $product->variants()->updateOrCreate(
                ['sku' => $product->sku.'-'.$size['label']],
                [
                    'name'           => ['ar' => $size['label'], 'en' => $size['label']],
                    'attributes'     => ['size' => $size['label']],
                    'price'          => round($basePrice * $size['mult'], 2),
                    'stock_quantity' => $size['stock'],
                    'is_active'      => true,
                    'sort_order'     => $i,
                ]
            );
        }
    }

    /** Volume variants (30/50/100ml), the largest one sold out. */
    private function seedVolumePerfume(?int $brandId): void
    {
        $category = Category::where('slug', 'perfumes')->first();
        if (! $category) {
            return;
        }

        $basePrice = 220.0;

        $product = Product::updateOrCreate(
            ['slug' => 'test-perfume-volumes'],
            [
                'category_id'        => $category->id,
                'brand_id'            => $brandId,
                'name'                => ['ar' => 'TEST — عطر بأحجام', 'en' => 'TEST — Perfume (Volumes)'],
                'short_description'   => [
                    'ar' => 'منتج تجريبي لاختبار اختيار الحجم — غير معروض للبيع الفعلي.',
                    'en' => 'QA fixture for testing the volume picker — not a real product for sale.',
                ],
                'description' => [
                    'ar' => 'منتج تجريبي بثلاثة أحجام (30، 50، 100 مل) لاختبار واجهة اختيار الحجم وحالة "غير متوفر".',
                    'en' => 'QA fixture with three volumes (30ml, 50ml, 100ml) for testing the volume-picker UI and the sold-out state.',
                ],
                'sku'                 => 'QATEST-002',
                'base_price'          => $basePrice,
                'compare_at_price'    => null,
                'currency'            => 'SAR',
                'stock_quantity'      => 0,
                'has_variants'        => true,
                'scent_family'        => 'Oud',
                // Active even though its category is currently deactivated — the
                // product page is still reachable directly by link, which is all
                // this fixture needs (see class docblock).
                'is_active'           => true,
                'is_featured'         => false,
                'is_new_arrival'      => false,
                'is_gift_eligible'    => true,
            ]
        );

        $product->images()->updateOrCreate(
            ['path' => '/images/placeholder.svg'],
            ['disk' => 'public', 'alt' => ['ar' => 'TEST — عطر بأحجام', 'en' => 'TEST — Perfume (Volumes)'], 'is_primary' => true, 'sort_order' => 0]
        );

        $volumes = [
            ['label_ar' => '30 مل',  'label_en' => '30ml',  'mult' => 0.7, 'stock' => 20],
            ['label_ar' => '50 مل',  'label_en' => '50ml',  'mult' => 1.0, 'stock' => 14],
            ['label_ar' => '100 مل', 'label_en' => '100ml', 'mult' => 1.6, 'stock' => 0], // deliberately sold out
        ];

        foreach ($volumes as $i => $vol) {
            $product->variants()->updateOrCreate(
                ['sku' => $product->sku.'-'.$vol['label_en']],
                [
                    'name'           => ['ar' => $vol['label_ar'], 'en' => $vol['label_en']],
                    'attributes'     => ['size' => $vol['label_en']],
                    'price'          => round($basePrice * $vol['mult'], 2),
                    'stock_quantity' => $vol['stock'],
                    'is_active'      => true,
                    'sort_order'     => $i,
                ]
            );
        }
    }
}
