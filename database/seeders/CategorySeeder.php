<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Placeholder catalog taxonomy (bilingual). Replace with the client's real
 * categories once supplied — keyed by slug so this seeder is idempotent.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'perfumes',    'icon' => 'bi-droplet-half', 'ar' => 'العطور',            'en' => 'Perfumes'],
            ['slug' => 'flowers',     'icon' => 'bi-flower1',      'ar' => 'الزهور والهدايا',   'en' => 'Flowers & Gifts'],
            ['slug' => 'beauty',      'icon' => 'bi-gem',          'ar' => 'الجمال',            'en' => 'Beauty'],
            ['slug' => 'abayas',      'icon' => 'bi-bag-heart',    'ar' => 'العبايات',          'en' => 'Abayas'],
            ['slug' => 'accessories', 'icon' => 'bi-handbag',      'ar' => 'الإكسسوارات',       'en' => 'Accessories'],
            ['slug' => 'seasonal',    'icon' => 'bi-stars',        'ar' => 'المنتجات الموسمية', 'en' => 'Seasonal'],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name'        => ['ar' => $category['ar'], 'en' => $category['en']],
                    'description' => [
                        'ar' => 'تشكيلة مختارة من '.$category['ar'].' من أروما.',
                        'en' => 'A curated selection of '.$category['en'].' by Aroma.',
                    ],
                    'icon'        => $category['icon'],
                    'sort_order'  => $index,
                    'is_active'   => true,
                    'is_featured' => true,
                ]
            );
        }
    }
}
