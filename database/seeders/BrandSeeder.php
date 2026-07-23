<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Placeholder brands (bilingual). Replace with the client's real brand roster.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['slug' => 'aroma-signature', 'ar' => 'أروما سيجنتشر', 'en' => 'Aroma Signature'],
            ['slug' => 'maison-riyadh',   'ar' => 'ميزون الرياض',   'en' => 'Maison Riyadh'],
            ['slug' => 'noor',            'ar' => 'نور',            'en' => 'Noor'],
            ['slug' => 'dune',            'ar' => 'ديون',           'en' => 'Dune'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['slug' => $brand['slug']],
                [
                    'name'      => ['ar' => $brand['ar'], 'en' => $brand['en']],
                    'is_active' => true,
                ]
            );
        }
    }
}
