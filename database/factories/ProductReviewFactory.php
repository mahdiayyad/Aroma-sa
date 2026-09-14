<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'body' => $this->faker->paragraph(),
            'is_verified_purchase' => false,
            'status' => ProductReview::STATUS_APPROVED,
            'approved_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => ProductReview::STATUS_PENDING, 'approved_at' => null]);
    }
}
