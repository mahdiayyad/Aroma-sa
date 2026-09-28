<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\PointTransaction;
use App\Services\RewardPointService;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardPurchasePoints
{
    public function handle(OrderPaid $event): void
    {
        // OrderPaid fires from inside CheckoutService::markOrderAsPaid()'s own
        // DB transaction (see SendOrderConfirmationEmail) — a thrown exception
        // here must never roll back the paid transition, so it's caught and
        // logged rather than allowed to propagate.
        $order = $event->order;

        if (! $order->user_id) {
            return; // guest checkout — no account to credit
        }

        try {
            $service = app(RewardPointService::class);
            $points = $service->toPoints((float) $order->total_amount);

            if ($points > 0) {
                $service->credit(
                    $order->user,
                    $points,
                    PointTransaction::TYPE_PURCHASE_REWARD,
                    $order,
                    __('referral.ledger.purchase_reward', ['order' => $order->order_number])
                );
            }
        } catch (Throwable $e) {
            Log::error('Failed to award purchase points', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
