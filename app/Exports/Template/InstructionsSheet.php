<?php

declare(strict_types=1);

namespace App\Exports\Template;

use App\Support\ProductSheet\Columns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** "Instructions" sheet: how to fill the template in, the rules, and an Arabic summary. */
class InstructionsSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles
{
    /** @var array<int,int> rows that are section headings (styled bold) */
    private $headingRows = [];

    public function title(): string
    {
        return 'Instructions';
    }

    /** @return array<int,int> */
    public function columnWidths(): array
    {
        return ['A' => 26, 'B' => 110];
    }

    /** @return array<int,array<int,string>> */
    public function array(): array
    {
        $rows = [];
        $heading = function (string $text) use (&$rows) {
            $rows[] = ['', ''];
            $rows[] = [$text, ''];
            $this->headingRows[] = count($rows);
        };
        $line = function (string $a, string $b = '') use (&$rows) {
            $rows[] = [$a, $b];
        };

        $rows[] = ['Aroma — Product import template', ''];
        $this->headingRows[] = 1;
        $line('Read this first', 'Fill in the "Products" sheet and upload it in Admin ▸ Products ▸ Import products. Delete the three EXAMPLE- rows (or replace them) before uploading.');

        $heading('How it works');
        $line('1. One row = one product', 'The SKU is the key. A SKU that already exists UPDATES that product; a new SKU CREATES a product.');
        $line('2. Nothing is saved yet', 'After upload the file is checked row by row. You see every problem (row number, column, message) and a preview of what will be created/updated. Nothing changes until you confirm.');
        $line('3. All or nothing', 'On confirm, everything is saved in one transaction. If anything fails, nothing is imported.');
        $line('4. Updating', 'Only fill the columns you want to change. A BLANK cell on an existing product means "leave as it is". To empty an optional field write ' . Columns::CLEAR_TOKEN . ' (e.g. a sale price to end a sale, a meta description, or all images).');

        $heading('Required for NEW products');
        $line(implode(', ', array_map(function (string $key) {
            return Columns::get($key)->header;
        }, Columns::requiredKeys())), 'These headers are gold. Everything else is optional (New products default to: stock 0, status active, gift eligible yes, featured no).');

        $heading('Rules worth knowing');
        $line('Price / Sale Price', 'Price is the regular price. Sale Price (optional) is the discounted price customers pay — it must be lower than Price. Numbers like 1,200.50 and Arabic digits (٣٥٠) are fine.');
        $line('Category / Brand', 'Use the ID, the slug, or the name in Arabic or English — see the "Reference" sheet for the exact list. Categories and brands are never created by an import.');
        $line('Slug', 'Optional. Lowercase letters, numbers and hyphens. Left blank on a new product it is generated from the English name. It must be unique across all products.');
        $line('Meta Title / Description / Keywords', 'These are what Google and the browser tab show. They are used on the product page as soon as they are imported. Blank = the shop uses "Product name — brand".');
        $line('Image URLs', 'Public http(s) links separated by ' . Columns::IMAGE_SEPARATOR . ' (or a new line). The first image is the main one. Images are downloaded and stored on your site. Re-importing the same links never duplicates images.');
        $line('Status / Yes-No columns', 'Status: active or inactive. Featured, New Arrival, Gift Eligible: yes or no (نعم / لا also work).');
        $line('Variants', 'Products that have variants keep stock per variant, so Stock Quantity is ignored for them (a warning is shown).');
        $line('Sheet names', 'Keep the sheet named "Products". The Instructions and Reference sheets are ignored on import.');

        $heading('Columns');
        foreach (Columns::all() as $column) {
            $line($column->header . ($column->requiredOnCreate ? '  *' : ''), $column->help);
        }
        $line('* = required for new products', '');

        $heading('ملخص بالعربية');
        $line('كيف تعمل؟', 'كل صف يمثل منتجًا واحدًا، ورمز المنتج (SKU) هو المفتاح: إذا كان الرمز موجودًا يتم تحديث المنتج، وإذا كان جديدًا يتم إنشاؤه.');
        $line('المراجعة', 'بعد الرفع يتم فحص الملف صفًا بصف وتظهر لك الأخطاء (رقم الصف والعمود والرسالة) ومعاينة لما سيتم إنشاؤه أو تحديثه. لا يتم حفظ أي شيء قبل التأكيد.');
        $line('الحفظ', 'عند التأكيد يُحفظ كل شيء دفعة واحدة، وإذا فشل أي جزء لا يتم استيراد شيء.');
        $line('التحديث', 'اترك الخلية فارغة لإبقاء القيمة الحالية، واكتب ' . Columns::CLEAR_TOKEN . ' لمسح قيمة اختيارية.');
        $line('الأسعار', 'السعر هو السعر الأصلي، وسعر الخصم (اختياري) يجب أن يكون أقل منه.');
        $line('الصور', 'روابط عامة تبدأ بـ http أو https مفصولة بالرمز | ، والصورة الأولى هي الرئيسية.');

        return $rows;
    }

    /** @return array<int|string,array<string,mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B'.$sheet->getHighestRow())->getAlignment()->setWrapText(true)->setVertical('top');

        $styles = [1 => ['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '330101']]]];

        foreach ($this->headingRows as $row) {
            if ($row !== 1) {
                $styles[$row] = ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '330101']]];
            }
        }

        $styles['A'] = ['font' => ['bold' => true]];

        return $styles;
    }
}
