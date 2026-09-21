<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use RuntimeException;
use Throwable;

/** The apply step hit a problem on a specific row; the whole transaction is rolled back. */
final class ImportAbortedException extends RuntimeException
{
    /** @var int */
    public $row;

    public function __construct(int $row, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->row = $row;
    }
}
