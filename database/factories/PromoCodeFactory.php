<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('CODE###??')),
            'name' => $this->faker->words(3, true),
            'description' => null,
            'is_active' => true,
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'free_shipping' => false,
            'min_order_amount' => null,
            'max_discount_amount' => null,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'used_count' => 0,
            'first_order_only' => false,
            'customer_restricted' => false,
        ];
    }
}
