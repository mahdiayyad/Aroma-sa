<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;
use App\Support\ProductSheet\Column;
use App\Support\ProductSheet\Columns;
use App\Support\ProductSheet\ProductRowMapper;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Products -> the same workbook the importer reads. The query is chunked by
 * Laravel Excel, so it streams however many products match; relations are
 * eager-loaded once per chunk (no N+1).
 */
class ProductsExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, IValueBinder
{
    /** @var Builder */
    private $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /** SKUs like 000123 or 1E5 must stay text — never let Excel "helpfully" convert them. */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === self::letter((int) array_search('sku', Columns::keys(), true)) && $cell->getRow() > 1 && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function query(): Builder
    {
        return $this->query
            ->with(['category', 'brand', 'images', 'variants'])
            ->orderBy('id');
    }

    /** @return array<int,string> */
    public function headings(): array
    {
        return Columns::headers();
    }

    /** @param Product $product @return array<int,mixed> */
    public function map($product): array
    {
        $row = ProductRowMapper::row($product);

        return array_map(function (string $key) use ($row) {
            return $row[$key] ?? null;
        }, Columns::keys());
    }

    public function title(): string
    {
        return Columns::PRODUCTS_SHEET;
    }

    /** @return array<string,array<string,string>> */
    public function columnFormats(): array
    {
        $formats = [];

        foreach (array_values(Columns::keys()) as $index => $key) {
            if (in_array($key, ['price', 'sale_price'], true)) {
                $formats[self::letter($index)] = NumberFormat::FORMAT_NUMBER_00;
            } elseif (in_array($key, ['stock_quantity', 'sort_order'], true)) {
                $formats[self::letter($index)] = NumberFormat::FORMAT_NUMBER;
            }
        }

        return $formats;
    }

    /** @return array<string,int> */
    public function columnWidths(): array
    {
        $widths = [];

        foreach (array_values(Columns::all()) as $index => $column) {
            $widths[self::letter($index)] = $column->width;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('B2');
        $last = self::letter(count(Columns::keys()) - 1);

        $wrapColumns = [];
        foreach (array_values(Columns::all()) as $index => $column) {
            if (in_array($column->key, ['description_ar', 'description_en', 'short_description_ar', 'short_description_en', 'meta_description_ar', 'meta_description_en', 'image_urls'], true)) {
                $wrapColumns[] = self::letter($index);
            }
        }

        foreach ($wrapColumns as $letter) {
            $sheet->getStyle($letter.'2:'.$letter.max(2, $sheet->getHighestRow()))->getAlignment()->setWrapText(true)->setVertical('top');
        }

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '330101']],
                'alignment' => ['vertical' => 'center', 'wrapText' => true],
            ],
        ];
    }

    public static function letter(int $zeroBasedIndex): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($zeroBasedIndex + 1);
    }
}
