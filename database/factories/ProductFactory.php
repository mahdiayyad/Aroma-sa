<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $en = $this->faker->unique()->words(2, true);

        return [
            'category_id'      => Category::factory(),
            'brand_id'         => null,
            'name'             => ['en' => Str::title($en), 'ar' => 'منتج '.$this->faker->numberBetween(1, 999)],
            'slug'             => Str::slug($en.'-'.Str::random(5)),
            'short_description' => ['en' => $this->faker->sentence(), 'ar' => 'وصف قصير'],
            'description'      => ['en' => $this->faker->paragraph(), 'ar' => 'وصف تفصيلي'],
            'sku'              => strtoupper(Str::random(8)),
            'base_price'       => $this->faker->randomFloat(2, 50, 500),
            'compare_at_price' => null,
            'currency'         => 'SAR',
            'stock_quantity'   => 10,
            'has_variants'     => false,
            'is_active'        => true,
            'is_featured'      => false,
            'is_new_arrival'   => false,
            'is_gift_eligible' => true,
        ];
    }

    public function featured(): self
    {
        return $this->state(['is_featured' => true]);
    }

    public function newArrival(): self
    {
        return $this->state(['is_new_arrival' => true]);
    }

    public function outOfStock(): self
    {
        return $this->state(['stock_quantity' => 0]);
    }
}
