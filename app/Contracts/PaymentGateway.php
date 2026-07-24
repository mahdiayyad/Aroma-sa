<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;

/**
 * A payment gateway the storefront can redirect a customer to and later verify.
 * Implemented by Moyasar (cards / Mada / Apple Pay) and the BNPL gateways
 * Tabby and Tamara, so the checkout is gateway-agnostic.
 */
interface PaymentGateway
{
    /** Machine key: moyasar | tabby | tamara. */
    public function key(): string;

    /**
     * Create a hosted checkout for the order and return where to send the buyer.
     *
     * @return array{success:bool, redirect_url?:string, reference?:string, error?:string}
     */
    public function createCheckout(Order $order): array;

    /**
     * Look the payment up at the gateway (authoritative source of truth on return).
     *
     * @return array{paid:bool, status:string, raw:array}|null  null when it can't be fetched
     */
    public function fetchStatus(string $reference): ?array;
}
