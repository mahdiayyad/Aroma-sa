<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Template\InstructionsSheet;
use App\Exports\Template\ProductsTemplateSheet;
use App\Exports\Template\ReferenceSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The downloadable starter workbook: a Products sheet (every supported column,
 * example rows, dropdowns), an Instructions sheet and a live Reference sheet
 * (real category/brand IDs). The importer reads the sheet named "Products", so
 * the file is ready to fill in and upload as it is.
 */
class ProductsTemplateExport implements WithMultipleSheets
{
    /** @return array<int,object> */
    public function sheets(): array
    {
        return [
            new ProductsTemplateSheet(),
            new InstructionsSheet(),
            new ReferenceSheet(),
        ];
    }
}
