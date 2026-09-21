<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

/**
 * The outcome of validating one spreadsheet row: what would happen to the
 * catalog (create / update / unchanged) and the exact values to write — or the
 * reasons the row is unacceptable. Nothing here touches the database.
 */
final class RowPlan
{
    public const CREATE = 'create';
    public const UPDATE = 'update';
    public const UNCHANGED = 'unchanged';

    /** @var int sheet row number (1 = header) */
    public $row;

    /** @var string|null */
    public $sku;

    /** @var string create | update | unchanged */
    public $action = self::CREATE;

    /** @var int|null existing product id when updating */
    public $productId;

    /** @var array<string,mixed> plain columns to set */
    public $attributes = [];

    /** @var array<string,array<string,string|null>> field => [locale => value|null(clear)] */
    public $translations = [];

    /** @var array<int,string> image URLs in sheet order (empty = leave images alone) */
    public $imageUrls = [];

    /** @var bool the sheet said [clear] for Image URLs */
    public $clearImages = false;

    /** @var array<int,array{row:int,column:?string,message:string,value:?string}> */
    public $errors = [];

    /** @var array<int,array{row:int,column:?string,message:string,value:?string}> */
    public $warnings = [];

    /** @var int images that will be downloaded for this row (validation counters) */
    public $newImages = 0;

    /** @var bool the image list would add, remove or re-order images on an existing product */
    public $imagesChange = false;

    public function __construct(int $row)
    {
        $this->row = $row;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function error(?string $column, string $message, ?string $value = null): void
    {
        $this->errors[] = ['row' => $this->row, 'column' => $column, 'message' => $message, 'value' => $value];
    }

    public function warn(?string $column, string $message, ?string $value = null): void
    {
        $this->warnings[] = ['row' => $this->row, 'column' => $column, 'message' => $message, 'value' => $value];
    }
}
