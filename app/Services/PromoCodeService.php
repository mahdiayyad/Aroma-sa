<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Product;
use App\Models\User;
use App\Support\Formatting\Money;
use App\Support\Services\BaseService;

/**
 * The single validation + redemption gate for promo codes. Every check here
 * is server-side and re-run at each stage (apply, then again at order
 * creation) — nothing about eligibility or the discount amount is ever
 * trusted from the client or from what was computed at a previous step.
 */
class PromoCodeService extends BaseService
{
    private CartService $cart;
    private DiscountCalculator $calculator;

    public function __construct(CartService $cart, DiscountCalculator $calculator)
    {
        $this->cart = $cart;
        $this->calculator = $calculator;
    }

    /**
     * @return array{valid:bool,error?:string,promo_code?:PromoCode,discount_amount?:float,free_shipping?:bool}
     */
    public function validate(string $code, ?User $user): array
    {
        $promo = PromoCode::findByCode($code);

        if (! $promo) {
            return ['valid' => false, 'error' => __('promo.errors.not_found')];
        }
        if (! $promo->is_active) {
            return ['valid' => false, 'error' => __('promo.errors.inactive')];
        }
        if (! $promo->hasStarted()) {
            return ['valid' => false, 'error' => __('promo.errors.not_started')];
        }
        if ($promo->hasExpired()) {
            return ['valid' => false, 'error' => __('promo.errors.expired')];
        }
        if ($promo->hasReachedUsageLimit()) {
            return ['valid' => false, 'error' => __('promo.errors.usage_limit_reached')];
        }

        if ($promo->customer_restricted) {
            if (! $user || ! $promo->customers()->where('users.id', $user->id)->exists()) {
                return ['valid' => false, 'error' => __('promo.errors.not_eligible')];
            }
        }

        if ($promo->first_order_only) {
            if (! $user) {
                return ['valid' => false, 'error' => __('promo.errors.first_order_requires_account')];
            }
            if ($user->orders()->whereIn('status', Order::PAID_STATUSES)->exists()) {
                return ['valid' => false, 'error' => __('promo.errors.first_order_only')];
            }
        }

        if ($user && $promo->usage_limit_per_customer !== null) {
            $usedByCustomer = PromoCodeRedemption::where('promo_code_id', $promo->id)
                ->where('user_id', $user->id)
                ->count();

            if ($usedByCustomer >= $promo->usage_limit_per_customer) {
                return ['valid' => false, 'error' => __('promo.errors.customer_limit_reached')];
            }
        }

        $cartRows = $this->cartRows();
        $subtotal = array_sum(array_column($cartRows, 'line_total'));

        if ($promo->min_order_amount !== null && $subtotal < (float) $promo->min_order_amount) {
            return ['valid' => false, 'error' => __('promo.errors.below_minimum', [
                'amount' => Money::format($promo->min_order_amount),
            ])];
        }

        $calc = $this->calculator->calculate($promo, $cartRows);

        if ($calc['eligible_subtotal'] <= 0) {
            return ['valid' => false, 'error' => __('promo.errors.product_not_eligible')];
        }

        return [
            'valid' => true,
            'promo_code' => $promo,
            'discount_amount' => $calc['discount_amount'],
            'free_shipping' => (bool) $promo->free_shipping,
        ];
    }

    /**
     * Finalises usage bookkeeping — called from CheckoutService::markOrderAsPaid()
     * once payment is actually confirmed, never at order creation (an unpaid/
     * abandoned order must not burn a usage-limit slot). Atomic: locks the
     * promo row, increments used_count, and inserts the redemption record;
     * the unique(promo_code_id, order_id) constraint is the last-resort
     * guard against a double-redeem race (e.g. a retried webhook).
     */
    public function redeem(PromoCode $promo, Order $order, ?User $user, float $discountAmount): void
    {
        $this->transaction(function () use ($promo, $order, $user, $discountAmount) {
            $locked = PromoCode::whereKey($promo->id)->lockForUpdate()->first();

            if (! $locked || PromoCodeRedemption::where('promo_code_id', $locked->id)->where('order_id', $order->id)->exists()) {
                return;
            }

            $locked->increment('used_count');

            PromoCodeRedemption::create([
                'promo_code_id' => $locked->id,
                'order_id' => $order->id,
                'user_id' => $user ? $user->id : null,
                'discount_amount' => $discountAmount,
            ]);
        });
    }

    /**
     * @return array<int,array{product_id:int,category_id:?int,line_total:float}>
     */
    private function cartRows(): array
    {
        $rows = $this->cart->rows();

        if (empty($rows)) {
            return [];
        }

        $productIds = array_column($rows, 'product_id');
        $categoryByProduct = Product::whereIn('id', $productIds)->pluck('category_id', 'id');

        return array_map(fn ($row) => [
            'product_id' => (int) $row['product_id'],
            'category_id' => isset($categoryByProduct[$row['product_id']]) ? (int) $categoryByProduct[$row['product_id']] : null,
            'line_total' => (float) $row['unit_price'] * (int) $row['qty'],
        ], array_values($rows));
    }
}
