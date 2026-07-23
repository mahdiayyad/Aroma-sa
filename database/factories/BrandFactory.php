<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $en = $this->faker->unique()->company();

        return [
            'name'      => ['en' => $en, 'ar' => 'علامة '.$this->faker->numberBetween(1, 999)],
            'slug'      => Str::slug($en.'-'.Str::random(5)),
            'is_active' => true,
        ];
    }
}
