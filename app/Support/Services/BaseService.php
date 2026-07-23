<?php

declare(strict_types=1);

namespace App\Support\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base class for domain services. Services hold business logic/use-cases and
 * orchestrate repositories, events and external gateways. Keep controllers thin
 * by delegating to a service. The transaction helper wraps multi-step writes
 * (e.g. place order → decrement stock → create payment) in a DB transaction.
 */
abstract class BaseService
{
    /**
     * Run the given work inside a database transaction.
     *
     * @return mixed
     */
    protected function transaction(Closure $callback)
    {
        return DB::transaction($callback);
    }
}
