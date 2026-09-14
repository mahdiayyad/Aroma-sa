<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $address = [
            'recipient_name' => $this->faker->name(),
            'phone' => '+9665'.$this->faker->numerify('########'),
            'city' => 'Riyadh',
        ];

        return [
            'user_id' => null,
            'order_number' => 'AR-'.now()->year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'customer_name' => $address['recipient_name'],
            'customer_email' => $this->faker->unique()->safeEmail(),
            'customer_phone' => $address['phone'],
            'billing_address' => $address,
            'shipping_address' => $address,
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'total_amount' => 200,
        ];
    }
}
