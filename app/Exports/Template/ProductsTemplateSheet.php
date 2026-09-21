<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Support\ProductSheet\Columns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** The "Products" sheet of the template: headers, examples, hover help, dropdowns. */
class ProductsTemplateSheet extends DefaultValueBinder implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents, WithCustomValueBinder, IValueBinder
{
    /** Dropdown validation is applied down to this row. */
    private const VALIDATION_ROWS = 1000;

    public function bindValue(Cell $cell, $value)
    {
        // SKUs (and long digit strings) stay text.
        if ($cell->getColumn() === 'A' && $cell->getRow() > 1 && $value !== null) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<int,array<int,mixed>> */
    public function array(): array
    {
        return array_map(function (array $row) {
            return array_map(function (string $key) use ($row) {
                return $row[$key] ?? null;
            }, Columns::keys());
        }, TemplateExamples::rows());
    }

    /** @return array<int,string> */
    public function headings(): array
    {
        return Columns::headers();
    }

    public function title(): string
    {
        return Columns::PRODUCTS_SHEET;
    }

    /** @return array<string,int> */
    public function columnWidths(): array
    {
        $widths = [];

        foreach (array_values(Columns::all()) as $index => $column) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = max($column->width, 14);
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('B2');
        $sheet->getRowDimension(1)->setRowHeight(32);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '330101']],
                'alignment' => ['vertical' => 'center', 'wrapText' => true],
            ],
        ];
    }

    /** @return array<class-string,callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach (array_values(Columns::all()) as $index => $column) {
                    $letter = Coordinate::stringFromColumnIndex($index + 1);

                    // Required-for-new-products headers are gold; hovering any header shows its help.
                    if ($column->requiredOnCreate) {
                        $sheet->getStyle($letter.'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('C9B39A');
                        $sheet->getStyle($letter.'1')->getFont()->getColor()->setRGB('330101');
                    }

                    $comment = $sheet->getComment($letter.'1');
                    $comment->getText()->createTextRun(($column->requiredOnCreate ? '(Required for new products) ' : '').$column->help);
                    $comment->setWidth('260px')->setHeight('110px');

                    $list = null;
                    if ($column->key === 'status') {
                        $list = '"active,inactive"';
                    } elseif (in_array($column->key, ['featured', 'new_arrival', 'gift_eligible'], true)) {
                        $list = '"yes,no"';
                    }

                    if ($list !== null) {
                        $validation = $sheet->getCell($letter.'2')->getDataValidation();
                        $validation->setType('list')->setAllowBlank(true)->setShowDropDown(true)->setFormula1($list);
                        $sheet->setDataValidation($letter.'2:'.$letter.self::VALIDATION_ROWS, clone $validation);
                    }
                }

                $sheet->setSelectedCell('A2');
            },
        ];
    }
}
