<?php

declare(strict_types=1);

namespace App\Services\Payment;

use RuntimeException;

/**
 * The payment gateway couldn't be reached or answered with a server error.
 * Temporary by nature: a webhook hitting this answers 503 so the gateway
 * redelivers it later.
 */
class GatewayUnavailableException extends RuntimeException
{
}
