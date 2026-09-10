<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seeds the standard Abaya customisation options onto every simple
 * (non-variant) product in the Abayas category — the first real use of the
 * generic product-options system:
 *
 *   - size          : 50–60 (every number), no price change, required
 *   - closure_style : Open / Closed, no price change, required
 *   - dress_addon   : Without dress / Add a dress (+100 SAR), required, defaults to "without"
 *
 * Idempotent: safe to re-run. The dress add-on price lives in price_delta (not
 * the label) so a future price change stays consistent and historical orders,
 * which snapshot the delta, remain accurate.
 */
class ProductOptionSeeder extends Seeder
{
    private const DRESS_ADDON_PRICE = 100.0;

    public function run(): void
    {
        $abayas = Category::where('slug', 'abayas')->first();

        if (! $abayas) {
            return;
        }

        Product::where('category_id', $abayas->id)
            ->where('has_variants', false)
            ->get()
            ->each(function (Product $product) {
                $this->seedSize($product);
                $this->seedClosure($product);
                $this->seedDressAddon($product);
            });
    }

    private function seedSize(Product $product): void
    {
        $option = $product->allOptions()->updateOrCreate(
            ['key' => 'size'],
            [
                'label'       => ['ar' => 'المقاس', 'en' => 'Size'],
                'type'        => 'select',
                'is_required' => true,
                'is_active'   => true,
                'sort_order'  => 0,
            ]
        );

        foreach (range(50, 60) as $i => $size) {
            $option->values()->updateOrCreate(
                ['label->en' => (string) $size],
                [
                    'label'       => ['ar' => (string) $size, 'en' => (string) $size],
                    'price_delta' => 0,
                    'is_default'  => false,
                    'is_active'   => true,
                    'sort_order'  => $i,
                ]
            );
        }
    }

    private function seedClosure(Product $product): void
    {
        $option = $product->allOptions()->updateOrCreate(
            ['key' => 'closure_style'],
            [
                'label'       => ['ar' => 'نوع الإغلاق', 'en' => 'Closure Style'],
                'type'        => 'select',
                'is_required' => true,
                'is_active'   => true,
                'sort_order'  => 1,
            ]
        );

        $values = [
            ['ar' => 'مفتوحة (بدون أزرار)', 'en' => 'Open (Without Buttons)', 'default' => false, 'sort' => 0],
            ['ar' => 'مغلقة (بأزرار)', 'en' => 'Closed (With Buttons)', 'default' => true, 'sort' => 1],
        ];

        foreach ($values as $v) {
            $option->values()->updateOrCreate(
                ['label->en' => $v['en']],
                [
                    'label'       => ['ar' => $v['ar'], 'en' => $v['en']],
                    'price_delta' => 0,
                    'is_default'  => $v['default'],
                    'is_active'   => true,
                    'sort_order'  => $v['sort'],
                ]
            );
        }
    }

    private function seedDressAddon(Product $product): void
    {
        $option = $product->allOptions()->updateOrCreate(
            ['key' => 'dress_addon'],
            [
                'label'       => ['ar' => 'إضافة فستان مع العباية', 'en' => 'Add a dress with the abaya'],
                'type'        => 'select',
                'is_required' => true,
                'is_active'   => true,
                'sort_order'  => 2,
            ]
        );

        $option->values()->updateOrCreate(
            ['label->en' => 'Without dress'],
            [
                'label'       => ['ar' => 'بدون فستان', 'en' => 'Without dress'],
                'price_delta' => 0,
                'is_default'  => true,
                'is_active'   => true,
                'sort_order'  => 0,
            ]
        );

        // price_delta only — the "+100" text is rendered from this value, never
        // baked into the label, so changing it here is consistent everywhere.
        $option->values()->updateOrCreate(
            ['label->en' => 'Add a dress'],
            [
                'label'       => ['ar' => 'إضافة فستان', 'en' => 'Add a dress'],
                'price_delta' => self::DRESS_ADDON_PRICE,
                'is_default'  => false,
                'is_active'   => true,
                'sort_order'  => 1,
            ]
        );
    }
}
