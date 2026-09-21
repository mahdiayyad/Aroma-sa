<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Models\Brand;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** "Reference" sheet: the live category and brand IDs/slugs/names, plus the accepted values. */
class ReferenceSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles
{
    /** @var array<int,int> */
    private $headingRows = [];

    public function title(): string
    {
        return 'Reference';
    }

    /** @return array<string,int> */
    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 28, 'C' => 30, 'D' => 30, 'E' => 24];
    }

    /** @return array<int,array<int,string|int>> */
    public function array(): array
    {
        $rows = [];

        $rows[] = ['Categories — use the ID, slug or name in the Category column', '', '', '', ''];
        $this->headingRows[] = count($rows);
        $rows[] = ['ID', 'Slug', 'Name (English)', 'Name (Arabic)', 'Parent ID'];
        $this->headingRows[] = count($rows);

        foreach (Category::query()->orderBy('sort_order')->orderBy('id')->get() as $category) {
            $names = $category->getTranslations('name');
            $rows[] = [(int) $category->id, (string) $category->slug, $names['en'] ?? '', $names['ar'] ?? '', $category->parent_id ?: ''];
        }

        $rows[] = ['', '', '', '', ''];
        $rows[] = ['Brands — use the ID, slug or name in the Brand column', '', '', '', ''];
        $this->headingRows[] = count($rows);
        $rows[] = ['ID', 'Slug', 'Name (English)', 'Name (Arabic)', ''];
        $this->headingRows[] = count($rows);

        foreach (Brand::query()->orderBy('id')->get() as $brand) {
            $names = $brand->getTranslations('name');
            $rows[] = [(int) $brand->id, (string) $brand->slug, $names['en'] ?? '', $names['ar'] ?? '', ''];
        }

        $rows[] = ['', '', '', '', ''];
        $rows[] = ['Accepted values', '', '', '', ''];
        $this->headingRows[] = count($rows);
        $rows[] = ['Status', 'active, inactive', '(also: نشط / غير نشط)', '', ''];
        $rows[] = ['Yes / No columns', 'yes, no', '(also: نعم / لا, 1 / 0, true / false)', '', ''];
        $rows[] = ['Image URLs separator', '|  or a new line', '', '', ''];
        $rows[] = ['Clear a value', '[clear]', '(empties an optional field on an existing product)', '', ''];

        return $rows;
    }

    /** @return array<int|string,array<string,mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        foreach ($this->headingRows as $row) {
            $styles[$row] = ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8DDD2']]];
        }

        return $styles;
    }
}
