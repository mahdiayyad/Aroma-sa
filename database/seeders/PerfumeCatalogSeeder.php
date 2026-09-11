<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Reverses AbayaCatalogSeeder's "abayas only" storefront state: hides the
 * Abaya category (and its products) and brings the rest of the real catalog
 * (see CategorySeeder) back into the active storefront. Nothing is deleted —
 * nothing here touches product data, only the is_active flags that already
 * gate every catalog query (Category::scopeActive / Product::scopeActive),
 * so re-running AbayaCatalogSeeder later restores the abaya-only
 * presentation exactly as it was. Idempotent: safe to re-run.
 *
 * Deliberately an explicit slug allow-list rather than "activate everything
 * that isn't abaya" — a blanket inversion would also resurrect any stray/test
 * category (e.g. ad-hoc ones created while poking at the admin) that has no
 * business being back on the live storefront.
 *
 * Run on demand: `php artisan db:seed --class=PerfumeCatalogSeeder`.
 */
class PerfumeCatalogSeeder extends Seeder
{
    /** The abaya category slug differs by environment (abaya / abayas). */
    private const ABAYA_SLUGS = ['abaya', 'abayas'];

    /** The rest of CategorySeeder's real catalog. */
    private const REACTIVATE_SLUGS = ['perfumes', 'flowers', 'beauty', 'accessories', 'seasonal'];

    public function run(): void
    {
        $abayaIds = Category::whereIn('slug', self::ABAYA_SLUGS)->pluck('id');
        $reactivateIds = Category::whereIn('slug', self::REACTIVATE_SLUGS)->pluck('id');

        Category::whereIn('id', $abayaIds)->update(['is_active' => false]);
        Category::whereIn('id', $reactivateIds)->update(['is_active' => true]);

        Product::whereIn('category_id', $abayaIds)->update(['is_active' => false]);
        Product::whereIn('category_id', $reactivateIds)->update(['is_active' => true]);
    }
}
