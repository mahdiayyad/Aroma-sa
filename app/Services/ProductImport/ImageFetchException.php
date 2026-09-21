<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use RuntimeException;

/** An image URL that can't be turned into a stored image; $errorKey is under admin.products_io.image_errors. */
final class ImageFetchException extends RuntimeException
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
