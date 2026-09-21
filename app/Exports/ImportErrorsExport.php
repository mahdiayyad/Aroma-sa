<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\ProductSheet\Columns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Every validation error of an import run as a spreadsheet the admin can sort, filter and hand to whoever owns the data. */
class ImportErrorsExport implements FromArray, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    /** @var array<int,array<string,mixed>> */
    private $errors;

    /** @param array<int,array<string,mixed>> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    /** @return array<int,array<int,mixed>> */
    public function array(): array
    {
        return array_map(function (array $error) {
            $column = $error['column'] ?? null;

            return [
                (int) $error['row'],
                $column && isset(Columns::all()[$column]) ? Columns::get($column)->header : '',
                (string) $error['message'],
                (string) ($error['value'] ?? ''),
            ];
        }, $this->errors);
    }

    /** @return array<int,string> */
    public function headings(): array
    {
        return [(string) __('admin.products_io.report.row'), (string) __('admin.products_io.report.column'), (string) __('admin.products_io.report.message'), (string) __('admin.products_io.report.value')];
    }

    public function title(): string
    {
        return 'Errors';
    }

    /** @return array<string,int> */
    public function columnWidths(): array
    {
        return ['A' => 8, 'B' => 26, 'C' => 80, 'D' => 40];
    }

    /** @return array<int,array<string,mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '330101']]]];
    }
}
