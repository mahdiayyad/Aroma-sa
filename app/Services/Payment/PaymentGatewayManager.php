<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use InvalidArgumentException;

/**
 * Resolves the concrete gateway for a selected payment option. Keeps the
 * checkout controller free of gateway-specific branching.
 */
class PaymentGatewayManager
{
    public function for(string $gateway): PaymentGateway
    {
        switch ($gateway) {
            case 'tabby':
                return app(TabbyPaymentService::class);
            case 'tamara':
                return app(TamaraPaymentService::class);
            case 'moyasar':
                return app(MoyasarPaymentService::class);
        }

        throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}");
    }
}
