<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PromoCode;

/**
 * Pure discount math for a PromoCode against a set of cart lines — no
 * database writes, no session, no eligibility checks (that's
 * PromoCodeService::validate()). Kept separate so the arithmetic itself
 * is trivially unit-testable and has exactly one job.
 */
class DiscountCalculator
{
    /**
     * @param array<int,array{product_id:int,category_id:?int,line_total:float}> $cartRows
     * @return array{discount_amount:float,eligible_subtotal:float}
     */
    public function calculate(PromoCode $promo, array $cartRows): array
    {
        $eligibleSubtotal = $this->eligibleSubtotal($promo, $cartRows);

        if ($eligibleSubtotal <= 0) {
            return ['discount_amount' => 0.0, 'eligible_subtotal' => 0.0];
        }

        $discount = $promo->discount_type === PromoCode::TYPE_PERCENTAGE
            ? round($eligibleSubtotal * ((float) $promo->discount_value / 100), 2)
            : min((float) $promo->discount_value, $eligibleSubtotal);

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return ['discount_amount' => round($discount, 2), 'eligible_subtotal' => $eligibleSubtotal];
    }

    /**
     * A code restricted to specific products discounts only those line
     * items; a code restricted to specific categories discounts only lines
     * whose product falls in one of them; product restriction takes
     * precedence if both happen to be configured (admin UI treats them as
     * alternative, not combinable, restriction dimensions). No restriction
     * at all means the whole cart is eligible.
     *
     * @param array<int,array{product_id:int,category_id:?int,line_total:float}> $cartRows
     */
    private function eligibleSubtotal(PromoCode $promo, array $cartRows): float
    {
        $productIds = $promo->isRestrictedToProducts() ? $promo->products()->pluck('products.id')->all() : null;
        $categoryIds = $productIds === null && $promo->isRestrictedToCategories()
            ? $promo->categories()->pluck('categories.id')->all()
            : null;

        if ($productIds === null && $categoryIds === null) {
            return array_sum(array_column($cartRows, 'line_total'));
        }

        $sum = 0.0;
        foreach ($cartRows as $row) {
            $eligible = $productIds !== null
                ? in_array($row['product_id'], $productIds, true)
                : in_array($row['category_id'], $categoryIds, true);

            if ($eligible) {
                $sum += $row['line_total'];
            }
        }

        return $sum;
    }
}
