<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportRun;
use App\Models\User;
use App\Services\ProductImport\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductWorkbooks;
use Tests\TestCase;

/**
 * The import engine end to end (upload -> validate -> confirm -> apply) with
 * real .xlsx files, through ProductImportService — no HTTP layer.
 */
class ProductImportPipelineTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductWorkbooks;

    /** @var Category */
    private $perfumes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        app()->setLocale('en'); // messages are stored in the uploader's language

        $this->perfumes = Category::factory()->create(['name' => ['en' => 'Perfumes', 'ar' => 'العطور'], 'slug' => 'perfumes']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** Upload + validate (inline, since the test queue is sync). */
    private function upload(array $rows, array $options = [], ?array $keys = null): ProductImportRun
    {
        $service = app(ProductImportService::class);
        $run = $service->createRun($this->admin(), $this->workbook($rows, $keys), $options);
        $service->start($run);

        return $run->fresh();
    }

    private function confirm(ProductImportRun $run): ProductImportRun
    {
        app(ProductImportService::class)->confirm($run);

        return $run->fresh();
    }

    public function test_a_valid_sheet_is_reviewed_then_created_on_confirm(): void
    {
        $run = $this->upload([
            $this->row(['sku' => 'A-1', 'name_en' => 'Royal Oud', 'name_ar' => 'عود ملكي', 'price' => 450, 'sale_price' => 380, 'stock_quantity' => 12,
                'description_ar' => 'وصف طويل', 'description_en' => 'Long description', 'meta_title_en' => 'Royal Oud | Aroma', 'meta_title_ar' => 'عود ملكي | أروما',
                'meta_description_en' => 'Buy Royal Oud', 'meta_keywords' => 'oud, perfume', 'status' => 'active', 'featured' => 'yes', 'sort_order' => 3]),
            $this->row(['sku' => 'A-2', 'name_en' => 'White Musk', 'price' => '1,200.50']),
        ]);

        // Review step: valid, nothing written yet.
        $this->assertSame(ProductImportRun::STATUS_READY, $run->status, json_encode($run->errors));
        $this->assertSame(0, Product::count());
        $this->assertSame(2, $run->summary['created']);

        $run = $this->confirm($run);

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, (string) $run->message);
        $this->assertSame(2, Product::count());

        $oud = Product::where('sku', 'A-1')->first();
        $this->assertSame('Royal Oud', $oud->translate('name', 'en'));
        $this->assertSame('عود ملكي', $oud->translate('name', 'ar'));
        $this->assertSame('Royal Oud | Aroma', $oud->translate('meta_title', 'en'));
        $this->assertSame('عود ملكي | أروما', $oud->translate('meta_title', 'ar'));
        $this->assertSame('oud, perfume', $oud->meta_keywords);
        $this->assertSame('royal-oud', $oud->slug, 'slug generated from the English name');
        $this->assertSame($this->perfumes->id, $oud->category_id);
        // Sale price mapping: customers pay 380, 450 is the struck-through original.
        $this->assertEquals(380, $oud->base_price);
        $this->assertEquals(450, $oud->compare_at_price);
        $this->assertTrue($oud->isOnSale());
        $this->assertEquals(12, $oud->stock_quantity);
        $this->assertTrue($oud->is_featured);
        $this->assertSame(3, (int) $oud->sort_order);

        $musk = Product::where('sku', 'A-2')->first();
        $this->assertEquals(1200.50, $musk->base_price);
        $this->assertNull($musk->compare_at_price);
        $this->assertTrue($musk->is_active, 'new products default to active');
        $this->assertEquals(0, $musk->stock_quantity);
    }

    public function test_an_existing_sku_is_updated_and_blank_cells_leave_values_alone(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->perfumes->id, 'sku' => 'KEEP-1', 'base_price' => 200, 'stock_quantity' => 7,
            'name' => ['en' => 'Original', 'ar' => 'الأصلي'], 'meta_description' => ['en' => 'Keep me'],
        ]);

        // Only price + name_en are in the sheet; stock/description are blank/absent => untouched.
        $run = $this->confirm($this->upload([['sku' => 'KEEP-1', 'name_en' => 'Renamed', 'price' => 250]], [], ['sku', 'name_en', 'price']));

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, (string) $run->message);
        $product->refresh();
        $this->assertSame('Renamed', $product->translate('name', 'en'));
        $this->assertSame('الأصلي', $product->translate('name', 'ar'), 'the other language is kept');
        $this->assertEquals(250, $product->base_price);
        $this->assertEquals(7, $product->stock_quantity);
        $this->assertSame('Keep me', $product->translate('meta_description', 'en'));
        $this->assertSame(1, Product::count(), 'updated in place, not duplicated');
        $this->assertSame(1, $run->summary['updated']);
    }

    public function test_reimporting_identical_data_reports_unchanged_and_touches_nothing(): void
    {
        $product = Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'SAME-1', 'base_price' => 99, 'compare_at_price' => null,
            'name' => ['en' => 'Same', 'ar' => 'نفس'], 'stock_quantity' => 5, 'is_active' => true]);
        $before = $product->updated_at;

        $run = $this->upload([['sku' => 'SAME-1', 'name_en' => 'Same', 'name_ar' => 'نفس', 'price' => 99, 'stock_quantity' => 5, 'status' => 'active']]);

        $this->assertSame(ProductImportRun::STATUS_READY, $run->status);
        $this->assertSame(1, $run->summary['unchanged']);
        $this->assertSame(0, $run->summary['updated']);

        $run = $this->confirm($run);
        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status);
        $this->assertEquals($before, $product->fresh()->updated_at);
    }

    public function test_a_sale_can_be_cleared_and_a_sale_price_must_be_lower(): void
    {
        Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'SALE-1', 'base_price' => 80, 'compare_at_price' => 100]);

        $bad = $this->upload([['sku' => 'SALE-1', 'sale_price' => 120]], [], ['sku', 'sale_price']);
        $this->assertSame(ProductImportRun::STATUS_INVALID, $bad->status);
        $this->assertSame('sale_price', $bad->errors[0]['column']);

        $ok = $this->confirm($this->upload([['sku' => 'SALE-1', 'sale_price' => '[clear]']], [], ['sku', 'sale_price']));
        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $ok->status, (string) $ok->message);
        $product = Product::where('sku', 'SALE-1')->first();
        $this->assertEquals(100, $product->base_price, 'back to the regular price');
        $this->assertNull($product->compare_at_price);
    }

    public function test_category_and_brand_match_by_id_slug_or_name_in_either_language(): void
    {
        $abayas = Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'العبايات'], 'slug' => 'abayas']);
        $brand = Brand::factory()->create(['name' => ['en' => 'Maison Riyadh', 'ar' => 'ميزون الرياض'], 'slug' => 'maison-riyadh']);

        $run = $this->confirm($this->upload([
            $this->row(['sku' => 'C-ID', 'category' => (string) $abayas->id]),
            $this->row(['sku' => 'C-SLUG', 'category' => 'abayas']),
            $this->row(['sku' => 'C-EN', 'category' => '  perfumes ']),
            $this->row(['sku' => 'C-AR', 'category' => 'العطور', 'brand' => 'ميزون الرياض']),
        ]));

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, json_encode($run->errors).$run->message);
        $this->assertSame($abayas->id, Product::where('sku', 'C-ID')->value('category_id'));
        $this->assertSame($abayas->id, Product::where('sku', 'C-SLUG')->value('category_id'));
        $this->assertSame($this->perfumes->id, Product::where('sku', 'C-EN')->value('category_id'));
        $this->assertSame($this->perfumes->id, Product::where('sku', 'C-AR')->value('category_id'));
        $this->assertSame($brand->id, Product::where('sku', 'C-AR')->value('brand_id'));
    }

    public function test_every_bad_row_is_reported_with_its_row_number_and_column_and_nothing_is_written(): void
    {
        Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'TAKEN', 'slug' => 'taken-slug']);
        Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'GONE'])->delete();

        $run = $this->upload([
            $this->row(['sku' => 'OK-1']),                                              // row 2  fine
            $this->row(['sku' => 'BAD-CAT', 'category' => 'Nope']),                     // row 3
            $this->row(['sku' => 'BAD-PRICE', 'price' => 'abc']),                       // row 4
            $this->row(['sku' => 'OK-1']),                                              // row 5  duplicate of row 2
            $this->row(['sku' => 'GONE']),                                              // row 6  soft-deleted SKU
            $this->row(['sku' => 'SLUG-DUP', 'slug' => 'taken-slug']),                  // row 7  slug used by another product
            $this->row(['sku' => 'NEW-1', 'name_en' => '']),                            // row 8  missing required name
            $this->row(['sku' => 'BAD-STOCK', 'stock_quantity' => '-3']),               // row 9
            $this->row(['sku' => 'BAD-BOOL', 'featured' => 'maybe']),                   // row 10
        ]);

        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);
        $byRow = collect($run->errors)->groupBy('row');

        $this->assertSame('category', $byRow[3][0]['column']);
        $this->assertSame('price', $byRow[4][0]['column']);
        $this->assertSame('sku', $byRow[5][0]['column']);
        $this->assertStringContainsString('row 2', $byRow[5][0]['message']);
        $this->assertSame('sku', $byRow[6][0]['column']);
        $this->assertSame('slug', $byRow[7][0]['column']);
        $this->assertSame('name_en', $byRow[8][0]['column']);
        $this->assertSame('stock_quantity', $byRow[9][0]['column']);
        $this->assertSame('featured', $byRow[10][0]['column']);
        $this->assertFalse($byRow->has(2), 'the valid row is not flagged');

        $this->assertSame(8, $run->error_count);
        // Nothing but the two pre-existing products.
        $this->assertSame(1, Product::count());
        $this->assertNull(Product::where('sku', 'OK-1')->first());
    }

    public function test_slugs_are_generated_uniquely_and_explicit_duplicates_within_the_file_are_rejected(): void
    {
        $run = $this->confirm($this->upload([
            $this->row(['sku' => 'S-1', 'name_en' => 'Rose Oud']),
            $this->row(['sku' => 'S-2', 'name_en' => 'Rose Oud']),
            $this->row(['sku' => 'S-3', 'name_en' => 'Rose Oud']),
        ]));

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status);
        $this->assertEqualsCanonicalizing(['rose-oud', 'rose-oud-2', 'rose-oud-3'], Product::pluck('slug')->all());

        $dup = $this->upload([
            $this->row(['sku' => 'X-1', 'slug' => 'My Slug']),
            $this->row(['sku' => 'X-2', 'slug' => 'my-slug']),
        ]);
        $this->assertSame(ProductImportRun::STATUS_INVALID, $dup->status);
        $this->assertSame(3, $dup->errors[0]['row']);
        $this->assertSame('slug', $dup->errors[0]['column']);
    }

    public function test_a_file_without_a_sku_column_or_with_the_wrong_sheet_is_rejected_clearly(): void
    {
        $run = $this->upload([['name_en' => 'X', 'price' => 1]], [], ['name_en', 'price']);

        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);
        $this->assertNull($run->errors[0]['column']);
        $this->assertStringContainsString('SKU', $run->errors[0]['message']);
    }

    public function test_headings_can_be_key_style_or_label_style_in_any_case_and_unknown_columns_only_warn(): void
    {
        $service = app(ProductImportService::class);
        // Headings as a person might type them (any case/spacing, key- or label-style, Arabic, plus an unknown one).
        $path = $this->workbookPath([$this->row(['sku' => 'H-1', 'name_en' => 'Headed', 'extra' => 'x'])],
            ['sku', 'name_ar', 'name_en', 'category', 'price', 'extra'],
            'Products', ['  sKu ', 'PRODUCT NAME (ARABIC)', 'name_en', 'Category', 'السعر', 'Colour']);

        $run = $service->createRun($this->admin(), new \Illuminate\Http\UploadedFile($path, 'p.xlsx', null, null, true), []);
        $service->start($run);
        $run = $run->fresh();

        $this->assertSame(ProductImportRun::STATUS_READY, $run->status, json_encode($run->errors));
        $this->assertStringContainsString('Colour', $run->warnings[0]['message']);
    }

    public function test_the_products_sheet_is_found_by_name_even_when_it_is_not_first(): void
    {
        $service = app(ProductImportService::class);
        $path = $this->workbookPath([$this->row(['sku' => 'W-1'])], null, 'Products');

        // Put a decoy "Instructions" sheet first.
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $decoy = $book->createSheet(0);
        $decoy->setTitle('Instructions');
        $decoy->setCellValue('A1', 'Read me');
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($path);

        $run = $service->createRun($this->admin(), new \Illuminate\Http\UploadedFile($path, 'p.xlsx', null, null, true), []);
        $service->start($run);

        $this->assertSame(ProductImportRun::STATUS_READY, $run->fresh()->status, json_encode($run->fresh()->errors));
    }
}
