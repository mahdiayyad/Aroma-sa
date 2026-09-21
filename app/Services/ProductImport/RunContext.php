<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductImportRun;

/**
 * Everything that must be shared across the rows of ONE pass over the sheet:
 * which columns exist, SKUs/slugs already seen in this file (so duplicates
 * inside the file are caught), the category/brand lookups, the options the
 * admin chose, and the running counters. One instance per validate/apply pass.
 */
final class RunContext
{
    public const VALIDATE = 'validate';
    public const APPLY = 'apply';

    /** @var ProductImportRun */
    public $run;

    /** @var string validate | apply */
    public $mode;

    /** @var array<int,string> column keys present in the sheet's header */
    public $columns = [];

    /** @var bool */
    public $replaceImages;

    /** @var bool */
    public $ignoreImageFailures;

    /** @var array<string,int> lowercased sku => first row */
    public $seenSkus = [];

    /** @var array<string,int> slug => row that claimed it */
    public $reservedSlugs = [];

    /** @var EntityResolver */
    public $categories;

    /** @var EntityResolver */
    public $brands;

    /** @var array<string,int> */
    public $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'images_new' => 0, 'images_added' => 0, 'images_removed' => 0, 'images_downloaded' => 0];

    /** @var array<int,array<string,mixed>> */
    public $errors = [];

    /** @var array<int,array<string,mixed>> */
    public $warnings = [];

    /** @var int total errors seen (the list itself is capped) */
    public $errorCount = 0;

    /** @var array<int,array<string,mixed>> every error up to a hard limit — written to the downloadable report */
    public $allErrors = [];

    private const HARD_ERROR_LIMIT = 20000;

    /** @var array<int,string> files written to the public disk during apply (deleted if the transaction rolls back) */
    public $createdFiles = [];

    /** @var array<int,string> files of removed images (deleted only after a successful commit) */
    public $filesToDelete = [];

    public function __construct(ProductImportRun $run, string $mode)
    {
        $this->run = $run;
        $this->mode = $mode;
        $this->replaceImages = (bool) $run->option('replace_images');
        $this->ignoreImageFailures = (bool) $run->option('ignore_image_failures');
        $this->categories = new EntityResolver(Category::class);
        $this->brands = new EntityResolver(Brand::class);
    }

    public function isValidating(): bool
    {
        return $this->mode === self::VALIDATE;
    }

    public function hasColumn(string $key): bool
    {
        return in_array($key, $this->columns, true);
    }

    public function record(RowPlan $plan): void
    {
        $cap = (int) config('aroma.import.error_cap', 1000);

        foreach ($plan->errors as $error) {
            $this->errorCount++;
            if (count($this->errors) < $cap) {
                $this->errors[] = $error;
            }
            if (count($this->allErrors) < self::HARD_ERROR_LIMIT) {
                $this->allErrors[] = $error;
            }
        }

        foreach ($plan->warnings as $warning) {
            if (count($this->warnings) < $cap) {
                $this->warnings[] = $warning;
            }
        }
    }
}
