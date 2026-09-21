<?php

declare(strict_types=1);

namespace App\Imports;

use RuntimeException;

/** The workbook itself is unusable (no SKU column, empty, duplicate headers…) — reported as a file-level error. */
final class SheetFormatException extends RuntimeException
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
