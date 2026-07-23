<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Placeholder products (bilingual) across every category so the storefront has
 * real data to render. Perfume products also get size variants. All use the
 * bundled SVG placeholder image until real photography is supplied.
 *
 * Replace this seeder — or better, enter products via the admin panel — once
 * the client's catalog arrives. Keyed by slug so re-running is safe.
 */
class ProductSeeder extends Seeder
{
    /** Bilingual product name stems per category. */
    private array $catalog = [
        'perfumes' => [
            ['ar' => 'عود ملكي', 'en' => 'Royal Oud'],
            ['ar' => 'ورد الطائف', 'en' => 'Taif Rose'],
            ['ar' => 'مسك أبيض', 'en' => 'White Musk'],
            ['ar' => 'عنبر ذهبي', 'en' => 'Golden Amber'],
        ],
        'flowers' => [
            ['ar' => 'باقة الحب', 'en' => 'Love Bouquet'],
            ['ar' => 'صندوق الأناقة', 'en' => 'Elegance Box'],
            ['ar' => 'تنسيق الفرح', 'en' => 'Joy Arrangement'],
        ],
        'beauty' => [
            ['ar' => 'سيروم مضيء', 'en' => 'Radiance Serum'],
            ['ar' => 'كريم مرطب', 'en' => 'Hydrating Cream'],
            ['ar' => 'أحمر شفاه مخملي', 'en' => 'Velvet Lipstick'],
        ],
        'abayas' => [
            ['ar' => 'عباية كلاسيك', 'en' => 'Classic Abaya'],
            ['ar' => 'عباية مطرزة', 'en' => 'Embroidered Abaya'],
            ['ar' => 'عباية حرير', 'en' => 'Silk Abaya'],
        ],
        'accessories' => [
            ['ar' => 'وشاح حريري', 'en' => 'Silk Scarf'],
            ['ar' => 'حقيبة يد', 'en' => 'Handbag'],
            ['ar' => 'ساعة أنيقة', 'en' => 'Elegant Watch'],
        ],
        'seasonal' => [
            ['ar' => 'هدية رمضان', 'en' => 'Ramadan Gift Set'],
            ['ar' => 'صندوق العيد', 'en' => 'Eid Box'],
        ],
    ];

    public function run(): void
    {
        $brands = Brand::pluck('id')->all();

        foreach ($this->catalog as $categorySlug => $products) {
            $category = Category::where('slug', $categorySlug)->first();
            if (! $category) {
                continue;
            }

            foreach ($products as $i => $names) {
                $isPerfume = $categorySlug === 'perfumes';
                $basePrice = $this->priceFor($categorySlug, $i);
                $onSale    = $i % 3 === 0;

                $product = Product::updateOrCreate(
                    ['slug' => Str::slug($names['en']).'-'.$categorySlug],
                    [
                        'category_id'      => $category->id,
                        'brand_id'         => $brands ? $brands[$i % count($brands)] : null,
                        'name'             => $names,
                        'short_description' => [
                            'ar' => 'منتج مميز من مجموعة '.$category->translate('name', 'ar').' بلمسة فاخرة.',
                            'en' => 'A signature piece from our '.$category->translate('name', 'en').' collection.',
                        ],
                        'description' => [
                            'ar' => 'وصف تجريبي مؤقت لهذا المنتج. سيتم استبداله بالمحتوى الحقيقي لاحقاً.',
                            'en' => 'Placeholder description for this product. Will be replaced with real copy.',
                        ],
                        'sku'              => strtoupper(Str::random(3)).'-'.($category->id).($i + 1),
                        'base_price'       => $basePrice,
                        'compare_at_price' => $onSale ? round($basePrice * 1.25, 2) : null,
                        'currency'         => 'SAR',
                        'stock_quantity'   => $isPerfume ? 0 : 25,
                        'has_variants'     => $isPerfume,
                        'scent_family'     => $isPerfume ? ['Oud', 'Floral', 'Musk', 'Amber'][$i % 4] : null,
                        'is_active'        => true,
                        'is_featured'      => $i < 2,
                        'is_new_arrival'   => $i % 2 === 0,
                        'is_gift_eligible' => true,
                    ]
                );

                // Primary image (placeholder)
                $product->images()->updateOrCreate(
                    ['path' => '/images/placeholder.svg'],
                    [
                        'disk'       => 'public',
                        'alt'        => $names,
                        'is_primary' => true,
                        'sort_order' => 0,
                    ]
                );

                // Perfume size variants
                if ($isPerfume) {
                    $sizes = [
                        ['ar' => '50 مل', 'en' => '50ml', 'mult' => 1.0, 'stock' => 15],
                        ['ar' => '100 مل', 'en' => '100ml', 'mult' => 1.6, 'stock' => 10],
                    ];

                    foreach ($sizes as $s => $size) {
                        $product->variants()->updateOrCreate(
                            ['sku' => $product->sku.'-'.$size['en']],
                            [
                                'name'           => ['ar' => $size['ar'], 'en' => $size['en']],
                                'attributes'     => ['size' => $size['en']],
                                'price'          => round($basePrice * $size['mult'], 2),
                                'stock_quantity' => $size['stock'],
                                'is_active'      => true,
                                'sort_order'     => $s,
                            ]
                        );
                    }
                }
            }
        }
    }

    private function priceFor(string $category, int $i): float
    {
        $base = [
            'perfumes'    => 220,
            'flowers'     => 180,
            'beauty'      => 120,
            'abayas'      => 350,
            'accessories' => 150,
            'seasonal'    => 250,
        ][$category] ?? 150;

        return (float) ($base + $i * 30);
    }
}
