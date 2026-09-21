<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\ProductSheet\Columns;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Builds real .xlsx files for import tests (and reads exported ones back) so
 * the whole pipeline — Laravel Excel included — is exercised, not mocked.
 */
trait BuildsProductWorkbooks
{
    /** 1x1 PNG. */
    protected function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /**
     * @param array<int,array<string,mixed>> $rows column key => value
     * @param array<int,string>|null $keys columns to include in the header (default: all)
     * @param array<int,string>|null $headings custom heading texts (same length as $keys)
     */
    protected function workbookPath(array $rows, ?array $keys = null, string $sheetName = 'Products', ?array $headings = null): string
    {
        $keys = $keys ?? Columns::keys();
        $headings = $headings ?? array_map(function (string $key) {
            return Columns::get($key)->header;
        }, $keys);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        foreach ($headings as $i => $heading) {
            $sheet->setCellValueExplicitByColumnAndRow($i + 1, 1, (string) $heading, DataType::TYPE_STRING);
        }

        foreach (array_values($rows) as $r => $row) {
            foreach ($keys as $i => $key) {
                $value = $row[$key] ?? null;

                if ($value === null) {
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValueByColumnAndRow($i + 1, $r + 2, $value);
                } else {
                    $sheet->setCellValueExplicitByColumnAndRow($i + 1, $r + 2, (string) $value, DataType::TYPE_STRING);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'aroma-import-').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $path;
    }

    /** @param array<int,array<string,mixed>> $rows */
    protected function workbook(array $rows, ?array $keys = null, string $name = 'products.xlsx', string $sheetName = 'Products'): UploadedFile
    {
        return new UploadedFile(
            $this->workbookPath($rows, $keys, $sheetName),
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /** Read a workbook's first (or named) sheet into rows of raw cell values. @return array<int,array<int,mixed>> */
    protected function readSheet(string $path, ?string $sheetName = null): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getSheet(0);

        return $sheet->toArray(null, true, false, false);
    }

    /** A minimal valid row; override keys as needed. @return array<string,mixed> */
    protected function row(array $overrides = []): array
    {
        return array_merge([
            'sku'      => 'SKU-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'name_ar'  => 'عطر تجريبي',
            'name_en'  => 'Test Perfume',
            'category' => 'Perfumes',
            'price'    => 100,
        ], $overrides);
    }
}
