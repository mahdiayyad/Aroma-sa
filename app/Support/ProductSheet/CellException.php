<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

use RuntimeException;

/**
 * A single cell that can't be understood (not a number, not yes/no…). Carries a
 * translation key under admin.products_io.errors so the importer can turn it
 * into a localised, row-specific message.
 */
final class CellException extends RuntimeException
{
    /** @var string */
    public $errorKey;

    /** @var array<string,mixed> */
    public $replace;

    /** @param array<string,mixed> $replace */
    public function __construct(string $errorKey, array $replace = [])
    {
        parent::__construct($errorKey);
        $this->errorKey = $errorKey;
        $this->replace = $replace;
    }
}
