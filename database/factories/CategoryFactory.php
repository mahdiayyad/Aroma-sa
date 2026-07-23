<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $en = $this->faker->unique()->words(2, true);

        return [
            'name'        => ['en' => Str::title($en), 'ar' => 'فئة '.$this->faker->numberBetween(1, 999)],
            'slug'        => Str::slug($en.'-'.Str::random(5)),
            'description' => ['en' => $this->faker->sentence(), 'ar' => 'وصف الفئة'],
            'icon'        => 'bi-tag',
            'sort_order'  => 0,
            'is_active'   => true,
            'is_featured' => false,
        ];
    }

    public function featured(): self
    {
        return $this->state(['is_featured' => true]);
    }
}
