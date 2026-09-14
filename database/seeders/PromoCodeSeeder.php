<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

/**
 * The always-on welcome offer is a real PromoCode row (not special-cased
 * logic elsewhere) — first_order_only=true is enforced server-side by
 * PromoCodeService::validate() against the customer's real order history.
 * Safe to re-run: keyed by code.
 */
class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        PromoCode::updateOrCreate(
            ['code' => 'WELCOME15'],
            [
                'name' => 'Welcome offer — 15% off your first order',
                'description' => 'Automatically eligible for any customer placing their first order.',
                'is_active' => true,
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 15,
                'free_shipping' => false,
                'first_order_only' => true,
                'customer_restricted' => false,
            ]
        );
    }
}
