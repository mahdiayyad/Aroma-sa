<?php

namespace Tests\Unit\ProductSheet;

use App\Support\ProductSheet\Columns;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    public function test_the_requested_columns_exist_in_the_requested_order(): void
    {
        $requested = ['SKU', 'Product Name (Arabic)', 'Product Name (English)', 'Description (Arabic)', 'Description (English)',
            'Short Description (Arabic)', 'Short Description (English)', 'Category', 'Price', 'Sale Price', 'Stock Quantity', 'Status',
            'Meta Title (Arabic)', 'Meta Title (English)', 'Meta Description (Arabic)', 'Meta Description (English)', 'Meta Keywords',
            'Slug', 'Featured', 'Sort Order', 'Image URLs'];

        $this->assertSame($requested, array_slice(Columns::headers(), 0, count($requested)));
    }

    public function test_only_the_fields_a_new_product_cannot_do_without_are_required(): void
    {
        $this->assertSame(['sku', 'name_ar', 'name_en', 'category', 'price'], Columns::requiredKeys());
    }

    public function test_keys_and_headers_are_unique_and_no_two_columns_share_a_spelling(): void
    {
        $this->assertSame(count(Columns::keys()), count(array_unique(Columns::keys())));
        $this->assertSame(count(Columns::headers()), count(array_unique(Columns::headers())));

        // A heading must resolve to exactly one column, so no spelling may be claimed twice.
        $owner = [];
        foreach (Columns::all() as $column) {
            foreach (array_merge([$column->key, $column->header], $column->aliases) as $spelling) {
                $normalised = Columns::normalizeHeading($spelling);

                if (isset($owner[$normalised])) {
                    $this->assertSame($owner[$normalised], $column->key, "'{$spelling}' is claimed by both {$owner[$normalised]} and {$column->key}");
                }

                $owner[$normalised] = $column->key;
            }
        }
    }

    /** @dataProvider headings */
    public function test_headings_resolve_however_they_are_written(string $heading, ?string $key): void
    {
        $this->assertSame($key, Columns::resolve($heading));
    }

    public function headings(): array
    {
        return [
            'exact'                  => ['Product Name (Arabic)', 'name_ar'],
            'lowercase'              => ['product name (english)', 'name_en'],
            'key style'              => ['meta_title_ar', 'meta_title_ar'],
            'extra spaces + case'    => ['  SALE   PRICE ', 'sale_price'],
            'punctuation ignored'    => ['Short Description - Arabic', 'short_description_ar'],
            'arabic heading'         => ['رمز المنتج', 'sku'],
            'unknown'                => ['Warehouse Bin', null],
            'blank'                  => ['   ', null],
        ];
    }

    public function test_every_column_has_help_text_and_a_positive_width(): void
    {
        foreach (Columns::all() as $column) {
            $this->assertNotSame('', $column->help, $column->key.' needs help text (template comments and docs use it)');
            $this->assertGreaterThan(0, $column->width, $column->key);
        }
    }
}
