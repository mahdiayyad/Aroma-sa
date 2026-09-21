<?php

declare(strict_types=1);

namespace App\Imports;

use App\Support\ProductSheet\Columns;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

/**
 * Streams the Products sheet of an uploaded workbook, chunk by chunk (memory
 * stays flat for large files), turning each row into `column key => clean
 * string|null` and handing it to a callback with its real sheet row number.
 *
 * Row 1 is the header. Headings are matched through Columns::resolve(), so
 * "Product Name (Arabic)", "name_ar" and Arabic spellings all work; unknown
 * headings are reported, not fatal. Blank rows are skipped.
 *
 * Implemented as a Laravel Excel import: this class is the per-sheet worker,
 * and the workbook wrapper picks the sheet named "Products" (else the first).
 */
class ProductSheetReader implements WithMultipleSheets
{
    /** @var callable(array<int,string>,array<int,string>):void  header callback: (columnKeys, unknownHeadings) */
    private $onHeader;

    /** @var callable(int,array<string,string|null>):void */
    private $onRow;

    /** @var int */
    private $sheetIndex;

    /**
     * @param callable(array<int,string>,array<int,string>):void $onHeader may throw SheetFormatException
     * @param callable(int,array<string,string|null>):void $onRow
     */
    public function __construct(int $sheetIndex, callable $onHeader, callable $onRow)
    {
        $this->sheetIndex = $sheetIndex;
        $this->onHeader = $onHeader;
        $this->onRow = $onRow;
    }

    /** @return array<int,object> */
    public function sheets(): array
    {
        return [$this->sheetIndex => new ProductSheetWorker($this->onHeader, $this->onRow)];
    }

    /** Index of the sheet to read: "Products" if present, otherwise the first. @throws SheetFormatException */
    public static function sheetIndex(string $absolutePath): int
    {
        try {
            $names = (new Xlsx())->listWorksheetNames($absolutePath);
        } catch (Throwable $e) {
            throw new SheetFormatException('file_unreadable', ['reason' => $e->getMessage()]);
        }

        foreach ($names as $index => $name) {
            if (mb_strtolower(trim((string) $name), 'UTF-8') === mb_strtolower(Columns::PRODUCTS_SHEET, 'UTF-8')) {
                return (int) $index;
            }
        }

        return 0;
    }

    /** Data rows in the products sheet (header excluded), read cheaply from the sheet dimensions. */
    public static function countRows(string $absolutePath): int
    {
        try {
            $info = (new Xlsx())->listWorksheetInfo($absolutePath);
        } catch (Throwable $e) {
            throw new SheetFormatException('file_unreadable', ['reason' => $e->getMessage()]);
        }

        $index = self::sheetIndex($absolutePath);

        return max(0, (int) ($info[$index]['totalRows'] ?? 0) - 1);
    }
}
