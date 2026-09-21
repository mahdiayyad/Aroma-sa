<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductWorkbooks;
use Tests\TestCase;

/**
 * The admin-facing side: buttons and pages, permissions, the export/template
 * downloads (read back as real workbooks), the upload -> review -> confirm flow
 * over HTTP, the progress endpoint, and the storefront SEO the import feeds.
 */
class ProductImportExportHttpTest extends TestCase
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
        // SetLocale runs on every web request and would fall back to Arabic without this.
        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');

        $this->perfumes = Category::factory()->create(['name' => ['en' => 'Perfumes', 'ar' => 'العطور'], 'slug' => 'perfumes']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_STAFF]);
    }

    /** Download response -> a readable local xlsx path. */
    private function saveDownload($response): string
    {
        $file = $response->baseResponse->getFile();

        return $file->getPathname();
    }

    /* Permissions ------------------------------------------------------------ */

    public function test_admin_sees_export_import_and_template_buttons_staff_only_export_and_template(): void
    {
        Product::factory()->create(['category_id' => $this->perfumes->id]);

        $this->actingAs($this->admin())->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee(route('admin.products.import.create'), false)
            ->assertSee(route('admin.products.template'), false)
            ->assertSee(route('admin.products.export', ['scope' => 'all']), false);

        $this->actingAs($this->staff())->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee(route('admin.products.template'), false)
            ->assertSee(route('admin.products.export', ['scope' => 'all']), false)
            ->assertDontSee(route('admin.products.import.create'), false);
    }

    public function test_staff_can_export_and_download_the_template_but_cannot_import(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.products.export'))->assertOk();
        $this->actingAs($staff)->get(route('admin.products.template'))->assertOk();

        $this->actingAs($staff)->get(route('admin.products.import.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.products.import.store'), ['file' => $this->workbook([$this->row()])])->assertForbidden();
        $this->actingAs($staff)->get(route('admin.products.import.history'))->assertForbidden();

        $run = ProductImportRun::create(['status' => 'ready', 'original_name' => 'x.xlsx', 'path' => 'x']);
        $this->actingAs($staff)->post(route('admin.products.import.confirm', $run))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.products.import.cancel', $run))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.products.import.status', $run))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.products.import.errors', $run))->assertForbidden();
    }

    public function test_customers_and_guests_get_nothing(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('admin.products.export'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.products.template'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.products.import.create'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.products.export'))->assertRedirect();
        $this->get(route('admin.products.import.create'))->assertRedirect();
    }

    public function test_the_ability_map_is_the_single_place_to_change_who_can_import(): void
    {
        config(['aroma.admin.abilities' => array_merge(config('aroma.admin.abilities'), ['products.import' => ['admin', 'staff']])]);

        $this->actingAs($this->staff())->get(route('admin.products.import.create'))->assertOk();

        config(['aroma.admin.abilities' => array_merge(config('aroma.admin.abilities'), ['products.export' => ['admin']])]);

        $this->actingAs($this->staff())->get(route('admin.products.export'))->assertForbidden();
    }

    /* Export ---------------------------------------------------------------- */

    public function test_export_writes_every_column_with_the_documented_price_and_seo_mapping(): void
    {
        $brand = Brand::factory()->create(['name' => ['en' => 'Maison Riyadh', 'ar' => 'ميزون الرياض']]);
        $product = Product::factory()->create([
            'category_id' => $this->perfumes->id, 'brand_id' => $brand->id, 'sku' => '00123',
            'name' => ['en' => 'Royal Oud', 'ar' => 'عود ملكي'], 'description' => ['en' => 'Long', 'ar' => 'وصف'],
            'short_description' => ['en' => 'Short', 'ar' => 'قصير'],
            'meta_title' => ['en' => 'Royal Oud | Aroma', 'ar' => 'عود ملكي | أروما'], 'meta_description' => ['en' => 'MD en', 'ar' => 'MD ar'],
            'meta_keywords' => 'oud, perfume', 'slug' => 'royal-oud', 'base_price' => 380, 'compare_at_price' => 450,
            'stock_quantity' => 12, 'is_active' => true, 'is_featured' => true, 'sort_order' => 4, 'scent_family' => 'Oud',
        ]);
        Storage::disk('public')->put('products/a.jpg', 'x');
        Storage::disk('public')->put('products/b.jpg', 'x');
        $product->images()->create(['disk' => 'public', 'path' => 'products/b.jpg', 'is_primary' => false, 'sort_order' => 2]);
        $product->images()->create(['disk' => 'public', 'path' => 'products/a.jpg', 'is_primary' => true, 'sort_order' => 1]);

        $rows = $this->readSheet($this->saveDownload($this->actingAs($this->admin())->get(route('admin.products.export'))), 'Products');

        $header = $rows[0];
        $this->assertSame('SKU', $header[0]);
        $this->assertSame(\App\Support\ProductSheet\Columns::headers(), $header);

        $data = array_combine($header, $rows[1]);
        $this->assertSame('00123', (string) $data['SKU'], 'SKU stays text (leading zeros kept)');
        $this->assertSame('عود ملكي', $data['Product Name (Arabic)']);
        $this->assertSame('Royal Oud', $data['Product Name (English)']);
        $this->assertSame('Perfumes', $data['Category']);
        $this->assertEquals(450, $data['Price'], 'Price = the regular (struck-through) price');
        $this->assertEquals(380, $data['Sale Price'], 'Sale Price = what customers pay');
        $this->assertEquals(12, $data['Stock Quantity']);
        $this->assertSame('active', $data['Status']);
        $this->assertSame('Royal Oud | Aroma', $data['Meta Title (English)']);
        $this->assertSame('عود ملكي | أروما', $data['Meta Title (Arabic)']);
        $this->assertSame('MD en', $data['Meta Description (English)']);
        $this->assertSame('oud, perfume', $data['Meta Keywords']);
        $this->assertSame('royal-oud', $data['Slug']);
        $this->assertSame('yes', $data['Featured']);
        $this->assertEquals(4, $data['Sort Order']);
        $this->assertSame('Maison Riyadh', $data['Brand']);
        $this->assertSame('Oud', $data['Scent Family']);

        $images = explode('|', $data['Image URLs']);
        $this->assertCount(2, $images);
        $this->assertStringEndsWith('products/a.jpg', $images[0], 'primary image first');
        $this->assertStringStartsWith('http', $images[0], 'absolute URLs');
    }

    public function test_export_scopes_all_filtered_and_selected(): void
    {
        $abayas = Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'العبايات'], 'slug' => 'abayas']);
        $a = Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'P-1', 'is_active' => true]);
        $b = Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'P-2', 'is_active' => false]);
        $c = Product::factory()->create(['category_id' => $abayas->id, 'sku' => 'A-1', 'is_active' => true]);
        Product::factory()->create(['category_id' => $abayas->id, 'sku' => 'GONE'])->delete();

        $admin = $this->admin();
        $skus = function ($response) {
            $rows = $this->readSheet($this->saveDownload($response), 'Products');

            return collect(array_slice($rows, 1))->pluck(0)->map(function ($v) { return (string) $v; })->sort()->values()->all();
        };

        $this->assertSame(['A-1', 'P-1', 'P-2'], $skus($this->actingAs($admin)->get(route('admin.products.export', ['scope' => 'all']))), 'trashed products are never exported');
        $this->assertSame(['P-1', 'P-2'], $skus($this->actingAs($admin)->get(route('admin.products.export', ['scope' => 'filtered', 'category' => $this->perfumes->id]))));
        $this->assertSame(['P-1'], $skus($this->actingAs($admin)->get(route('admin.products.export', ['scope' => 'filtered', 'category' => $this->perfumes->id, 'active' => '1']))));
        $this->assertSame(['P-2'], $skus($this->actingAs($admin)->get(route('admin.products.export', ['scope' => 'filtered', 'q' => 'P-2']))));
        $this->assertSame(['A-1', 'P-2'], $skus($this->actingAs($admin)->post(route('admin.products.export'), ['scope' => 'selected', 'ids' => [$b->id, $c->id]])));
    }

    public function test_exporting_a_selection_with_nothing_selected_explains_itself(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.export'), ['scope' => 'selected'])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('error');
    }

    public function test_an_export_re_imports_as_all_unchanged(): void
    {
        $product = Product::factory()->create([
            'category_id' => $this->perfumes->id, 'sku' => 'RT-1', 'name' => ['en' => 'Round Trip', 'ar' => 'ذهاب وعودة'],
            'base_price' => 90, 'compare_at_price' => 120, 'stock_quantity' => 3, 'meta_title' => ['en' => 'T'], 'meta_keywords' => 'k',
            'slug' => 'round-trip', 'sort_order' => 2,
        ]);
        Storage::disk('public')->put('products/rt.jpg', 'x');
        $product->images()->create(['disk' => 'public', 'path' => 'products/rt.jpg', 'is_primary' => true, 'sort_order' => 1]);

        $exported = $this->saveDownload($this->actingAs($this->admin())->get(route('admin.products.export')));
        $upload = new UploadedFile($exported, 'export.xlsx', null, null, true);

        $response = $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => $upload]);
        $run = ProductImportRun::latest('id')->first();

        $response->assertRedirect(route('admin.products.import.show', $run));
        $this->assertSame(ProductImportRun::STATUS_READY, $run->status, json_encode($run->errors));
        $this->assertSame(1, $run->summary['unchanged'], 'the export changes nothing when imported back: '.json_encode($run->summary));
        $this->assertSame(0, $run->summary['updated']);
        $this->assertSame(0, $run->summary['created']);
    }

    public function test_re_importing_an_export_stays_inline_because_the_shops_own_images_are_not_downloads(): void
    {
        // No Queue::fake(): it would also swallow the inline dispatchSync() we want to observe.
        config(['queue.default' => 'database', 'aroma.import.sync_max_images' => 25]);

        foreach (range(1, 4) as $n) {
            $product = Product::factory()->create(['category_id' => $this->perfumes->id, 'sku' => 'OWN-'.$n]);
            foreach (range(1, 8) as $i) {
                Storage::disk('public')->put("products/own-{$n}-{$i}.jpg", 'x');
                $product->images()->create(['disk' => 'public', 'path' => "products/own-{$n}-{$i}.jpg", 'is_primary' => $i === 1, 'sort_order' => $i]);
            }
        }

        $exported = $this->saveDownload($this->actingAs($this->admin())->get(route('admin.products.export')));
        $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => new UploadedFile($exported, 'export.xlsx', null, null, true)]);

        $run = ProductImportRun::latest('id')->first();
        $this->assertSame(ProductImportRun::STATUS_READY, $run->status, 'validated on the spot, not left waiting for a worker');
        $this->assertSame(4, $run->summary['unchanged']);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_a_small_file_with_many_downloads_still_goes_to_the_queue(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['queue.default' => 'database', 'aroma.import.sync_max_images' => 25]);

        $rows = [];
        foreach (range(1, 3) as $n) {
            $rows[] = $this->row(['sku' => 'FAR-'.$n, 'image_urls' => implode('|', array_map(function ($i) use ($n) {
                return "https://cdn.example.com/{$n}/{$i}.jpg";
            }, range(1, 10)))]);
        }

        $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => $this->workbook($rows)]);

        $run = ProductImportRun::latest('id')->first();
        $this->assertSame(ProductImportRun::STATUS_QUEUED, $run->status);
        \Illuminate\Support\Facades\Queue::assertPushedOn('imports', \App\Jobs\ValidateProductImport::class);
    }

    /* Template --------------------------------------------------------------- */

    public function test_the_template_has_all_columns_examples_instructions_and_live_reference_data(): void
    {
        $abayas = Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'العبايات'], 'slug' => 'abayas']);
        Brand::factory()->create(['name' => ['en' => 'Maison Riyadh', 'ar' => 'ميزون الرياض'], 'slug' => 'maison-riyadh']);

        $path = $this->saveDownload($this->actingAs($this->admin())->get(route('admin.products.template')));

        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $this->assertSame(['Products', 'Instructions', 'Reference'], $book->getSheetNames());

        $products = $this->readSheet($path, 'Products');
        $this->assertSame(\App\Support\ProductSheet\Columns::headers(), $products[0]);
        $this->assertCount(4, $products, 'header + 3 example rows');
        $this->assertStringStartsWith('EXAMPLE-', (string) $products[1][0]);

        $instructions = json_encode($this->readSheet($path, 'Instructions'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('SKU', $instructions);
        $this->assertStringContainsString('[clear]', $instructions);
        $this->assertStringContainsString('ملخص بالعربية', $instructions);

        $reference = json_encode($this->readSheet($path, 'Reference'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Perfumes', $reference);
        $this->assertStringContainsString('العبايات', $reference);
        $this->assertStringContainsString('maison-riyadh', $reference);
    }

    public function test_the_templates_examples_are_valid_against_this_shop_and_import_as_inactive_examples(): void
    {
        Category::factory()->create(['name' => ['en' => 'Abayas', 'ar' => 'العبايات'], 'slug' => 'abayas']);
        \Illuminate\Support\Facades\Http::fake(['example.com/*' => \Illuminate\Support\Facades\Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);
        $this->app->bind(\App\Services\ProductImport\ImageFetcher::class, function () {
            return new \App\Services\ProductImport\ImageFetcher(function () { return ['93.184.216.34']; });
        });

        $path = $this->saveDownload($this->actingAs($this->admin())->get(route('admin.products.template')));

        $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => new UploadedFile($path, 'template.xlsx', null, null, true)]);
        $run = ProductImportRun::latest('id')->first();

        $this->assertSame(ProductImportRun::STATUS_READY, $run->status, json_encode($run->errors));
        $this->assertSame(3, $run->summary['created']);
        $this->assertNotEmpty($run->warnings, 'EXAMPLE- rows are flagged');
    }

    /* Upload -> review -> confirm over HTTP ------------------------------------ */

    public function test_upload_review_and_confirm_through_the_admin_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.products.import.create'))->assertOk()->assertSee('name="file"', false);

        $this->actingAs($admin)->post(route('admin.products.import.store'), ['file' => $this->workbook([$this->row(['sku' => 'UI-1', 'name_en' => 'Via Admin'])])])
            ->assertRedirect();

        $run = ProductImportRun::latest('id')->first();
        $this->assertSame(ProductImportRun::STATUS_READY, $run->status);
        $this->assertSame(0, Product::where('sku', 'UI-1')->count(), 'nothing is written before confirming');

        $this->actingAs($admin)->get(route('admin.products.import.show', $run))
            ->assertOk()
            ->assertSee(__('admin.products_io.preview_title'))
            ->assertSee(route('admin.products.import.confirm', $run), false);

        $this->actingAs($admin)->post(route('admin.products.import.confirm', $run))->assertRedirect(route('admin.products.import.show', $run));

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->fresh()->status, (string) $run->fresh()->message);
        $this->assertSame('Via Admin', Product::where('sku', 'UI-1')->first()->translate('name', 'en'));

        $this->actingAs($admin)->get(route('admin.products.import.show', $run))->assertOk()->assertSee(__('admin.products_io.completed_title'));
    }

    public function test_auto_apply_imports_immediately_when_the_file_is_valid(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.import.store'), [
            'file' => $this->workbook([$this->row(['sku' => 'AUTO-1'])]), 'auto_apply' => '1',
        ]);

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, ProductImportRun::latest('id')->first()->status);
        $this->assertNotNull(Product::where('sku', 'AUTO-1')->first());
    }

    public function test_row_by_row_errors_are_shown_and_downloadable_and_nothing_is_imported(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.import.store'), ['file' => $this->workbook([
            $this->row(['sku' => 'OK-1']),
            $this->row(['sku' => 'BAD-1', 'category' => 'Nowhere']),
            $this->row(['sku' => 'BAD-2', 'price' => 'free']),
        ])]);

        $run = ProductImportRun::latest('id')->first();
        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);

        $page = $this->actingAs($admin)->get(route('admin.products.import.show', $run));
        $page->assertOk()
            ->assertSee(__('admin.products_io.invalid_title'))
            ->assertSee('Nowhere')
            ->assertSee('free')
            ->assertSee(route('admin.products.import.errors', $run), false)
            ->assertDontSee(route('admin.products.import.confirm', $run), false);

        $report = $this->readSheet($this->saveDownload($this->actingAs($admin)->get(route('admin.products.import.errors', $run))));
        $this->assertSame(['Row', 'Column', 'Message', 'Value'], $report[0]);
        $this->assertSame(3, $report[1][0]);
        $this->assertSame('Category', $report[1][1]);
        $this->assertSame(4, $report[2][0]);
        $this->assertSame('Price', $report[2][1]);
        $this->assertSame(0, Product::count());

        // Can't be confirmed either.
        $this->actingAs($admin)->post(route('admin.products.import.confirm', $run));
        $this->assertSame(0, Product::count());
    }

    public function test_the_upload_rejects_non_xlsx_files_and_oversized_files(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.import.store'), ['file' => UploadedFile::fake()->create('products.csv', 10, 'text/csv')])
            ->assertSessionHasErrors('file');

        config(['aroma.import.max_upload_mb' => 1]);
        $this->actingAs($admin)->post(route('admin.products.import.store'), ['file' => UploadedFile::fake()->create('big.xlsx', 2048)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ProductImportRun::count());
    }

    public function test_a_corrupt_xlsx_is_rejected_with_a_readable_error(): void
    {
        $bad = UploadedFile::fake()->createWithContent('broken.xlsx', 'this is not a zip');

        $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => $bad])->assertRedirect();

        $run = ProductImportRun::latest('id')->first();
        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);
        $this->assertStringContainsString('.xlsx', $run->errors[0]['message']);
    }

    public function test_the_status_endpoint_returns_progress_json_and_history_lists_runs(): void
    {
        $admin = $this->admin();
        $run = ProductImportRun::create(['user_id' => $admin->id, 'status' => ProductImportRun::STATUS_VALIDATING, 'phase' => 'validate',
            'original_name' => 'big.xlsx', 'path' => 'x', 'total_rows' => 400, 'processed_rows' => 100]);

        $this->actingAs($admin)->getJson(route('admin.products.import.status', $run))
            ->assertOk()
            ->assertJson(['id' => $run->id, 'status' => 'validating', 'processed' => 100, 'total' => 400, 'percent' => 25, 'active' => true, 'finished' => false]);

        $this->actingAs($admin)->get(route('admin.products.import.history'))->assertOk()->assertSee('big.xlsx');
        $this->actingAs($admin)->get(route('admin.products.import.show', $run))->assertOk()->assertSee('importProgressBar', false);
    }

    public function test_a_queued_apply_reports_zero_progress_not_the_validation_leftovers(): void
    {
        $admin = $this->admin();
        $run = ProductImportRun::create(['user_id' => $admin->id, 'status' => ProductImportRun::STATUS_QUEUED, 'phase' => 'apply',
            'original_name' => 'big.xlsx', 'path' => 'x', 'total_rows' => 620, 'processed_rows' => 620]);

        $this->actingAs($admin)->getJson(route('admin.products.import.status', $run))
            ->assertOk()
            ->assertJson(['status' => 'queued', 'phase' => 'apply', 'processed' => 0, 'total' => 620, 'percent' => 0, 'active' => true]);
    }

    public function test_old_runs_that_are_going_nowhere_are_pruned_with_their_files_but_active_and_recent_ones_are_not(): void
    {
        config(['aroma.import.retention_days' => 30]);
        $old = now()->subDays(45);
        $make = function (string $status, $when) {
            $run = ProductImportRun::create(['status' => $status, 'original_name' => $status.'.xlsx', 'path' => 'imports/x/'.$status.'.xlsx']);
            $run->forceFill(['created_at' => $when])->saveQuietly();

            return $run;
        };

        $pruned = ['invalid', 'ready', 'completed', 'failed', 'cancelled'];
        $kept = ['queued', 'validating', 'applying'];
        $runs = [];
        foreach (array_merge($pruned, $kept) as $status) {
            $runs[$status] = $make($status, $old);
        }
        $recent = $make('completed', now()->subDays(2));

        Storage::disk('local')->put($runs['ready']->workDir().'/images/abc.jpg', 'x');

        $this->artisan('model:prune', ['--model' => [ProductImportRun::class]])->assertExitCode(0);

        foreach ($pruned as $status) {
            $this->assertNull(ProductImportRun::find($runs[$status]->id), "old {$status} run is pruned");
        }
        foreach ($kept as $status) {
            $this->assertNotNull(ProductImportRun::find($runs[$status]->id), "old {$status} run is still in flight, keep it");
        }
        $this->assertNotNull(ProductImportRun::find($recent->id), 'recent runs are kept');
        Storage::disk('local')->assertMissing($runs['ready']->workDir().'/images/abc.jpg');
    }

    public function test_every_import_and_export_is_logged_to_the_imports_channel(): void
    {
        $channel = \Mockery::mock();
        $channel->shouldReceive('info')->atLeast()->times(3)->withArgs(function ($message) use (&$messages) {
            $messages[] = $message;

            return true;
        });
        Log::shouldReceive('channel')->with('imports')->andReturn($channel);
        Log::shouldReceive('error')->andReturnNull();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.products.export'));
        $this->actingAs($admin)->get(route('admin.products.template'));
        $this->actingAs($admin)->post(route('admin.products.import.store'), ['file' => $this->workbook([$this->row(['sku' => 'LOG-1'])]), 'auto_apply' => '1']);

        $joined = implode('|', $messages);
        $this->assertStringContainsString('product export', $joined);
        $this->assertStringContainsString('template downloaded', $joined);
        $this->assertStringContainsString('upload', $joined);
        $this->assertStringContainsString('validation finished', $joined);
        $this->assertStringContainsString('completed', $joined);
    }

    /* SEO ---------------------------------------------------------------------- */

    public function test_imported_meta_title_description_and_keywords_are_rendered_on_the_storefront(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.import.store'), ['file' => $this->workbook([$this->row([
            'sku' => 'SEO-1', 'name_en' => 'Seo Oud', 'name_ar' => 'عود سيو', 'slug' => 'seo-oud',
            'meta_title_en' => 'Buy Seo Oud Perfume | Aroma', 'meta_title_ar' => 'اشتري عود سيو | أروما',
            'meta_description_en' => 'The best oud in Riyadh.', 'meta_description_ar' => 'أفضل عود في الرياض.',
            'meta_keywords' => 'oud, riyadh, عود',
        ])]), 'auto_apply' => '1']);

        // Admins are bounced off the storefront (BlockAdminShopping); look at it as a shopper.
        auth()->logout();

        $en = $this->get('/en/product/seo-oud')->assertOk();
        $en->assertSee('<title>Buy Seo Oud Perfume | Aroma</title>', false)
            ->assertSee('<meta name="description" content="The best oud in Riyadh.">', false)
            ->assertSee('<meta name="keywords" content="oud, riyadh, عود">', false);

        $this->get('/ar/product/seo-oud')->assertOk()
            ->assertSee('<title>اشتري عود سيو | أروما</title>', false)
            ->assertSee('أفضل عود في الرياض.', false);
    }

    public function test_without_seo_fields_the_storefront_keeps_its_name_dash_brand_title_and_no_keywords_tag(): void
    {
        Product::factory()->create(['category_id' => $this->perfumes->id, 'name' => ['en' => 'Plain', 'ar' => 'عادي'], 'slug' => 'plain', 'meta_title' => null, 'meta_keywords' => null]);

        $buffers = ob_get_level();

        $this->get('/en/product/plain')->assertOk()
            ->assertSee('<title>Plain — Aroma</title>', false)
            ->assertDontSee('name="keywords"', false);

        // A null @section value opens a block section that never closes (leaks an output buffer).
        $this->assertSame($buffers, ob_get_level(), 'the product view must not leave an output buffer open');
    }

    public function test_a_category_meta_title_is_rendered_too(): void
    {
        $this->perfumes->update(['meta_title' => ['en' => 'Luxury Perfumes in Saudi Arabia']]);

        $this->get('/en/category/perfumes')->assertOk()->assertSee('<title>Luxury Perfumes in Saudi Arabia</title>', false);
    }

    public function test_sort_order_ranks_products_first_and_unranked_zero_follows_newest_first(): void
    {
        $unrankedOld = Product::factory()->create(['category_id' => $this->perfumes->id, 'name' => ['en' => 'Unranked Old', 'ar' => 'قديم'], 'sort_order' => 0, 'created_at' => now()->subDays(3)]);
        $unrankedNew = Product::factory()->create(['category_id' => $this->perfumes->id, 'name' => ['en' => 'Unranked New', 'ar' => 'جديد'], 'sort_order' => 0, 'created_at' => now()->subDay()]);
        $second = Product::factory()->create(['category_id' => $this->perfumes->id, 'name' => ['en' => 'Ranked Second', 'ar' => 'ثاني'], 'sort_order' => 2, 'created_at' => now()->subDays(9)]);
        $first = Product::factory()->create(['category_id' => $this->perfumes->id, 'name' => ['en' => 'Ranked First', 'ar' => 'أول'], 'sort_order' => 1, 'created_at' => now()->subDays(8)]);

        $html = $this->get('/en/category/perfumes')->assertOk()->getContent();
        $positions = array_map(function ($name) use ($html) {
            return strpos($html, $name);
        }, ['Ranked First', 'Ranked Second', 'Unranked New', 'Unranked Old']);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'ranked 1, 2 lead; then unranked 0, newest first');
    }

    public function test_the_admin_api_accepts_the_new_fields_and_a_blank_sort_order_in_a_json_body(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->admin());
        $payload = [
            'name' => ['ar' => 'واجهة', 'en' => 'Api Product'], 'category_id' => $this->perfumes->id, 'base_price' => 60, 'stock_quantity' => 2,
            'meta_keywords' => 'x, y', 'sort_order' => 9,
        ];

        $this->postJson('/api/admin/products', $payload)->assertCreated();
        $created = Product::latest('id')->first();
        $this->assertSame(9, (int) $created->sort_order);
        $this->assertSame('x, y', $created->meta_keywords);

        // A blank Sort Order in a JSON body must mean "not provided", not NULL for a NOT NULL column.
        $this->postJson('/api/admin/products', array_merge($payload, ['name' => ['ar' => 'ثاني', 'en' => 'Api Two'], 'sort_order' => '']))->assertCreated();
        $this->assertSame(0, (int) Product::latest('id')->first()->sort_order);

        $this->postJson('/api/admin/products', array_merge($payload, ['name' => ['ar' => 'ثالث', 'en' => 'Api Three'], 'sort_order' => 'first']))
            ->assertStatus(422)->assertJsonValidationErrors('sort_order');
    }

    public function test_the_admin_product_form_saves_sort_order_and_meta_keywords(): void
    {
        $admin = $this->admin();
        $payload = [
            'name' => ['ar' => 'منتج', 'en' => 'Product'], 'category_id' => $this->perfumes->id, 'base_price' => 50, 'stock_quantity' => 1,
            'sort_order' => '7', 'meta_keywords' => 'a, b, c',
        ];

        $this->actingAs($admin)->post(route('admin.products.store'), $payload)->assertSessionHasNoErrors();

        $product = Product::latest('id')->first();
        $this->assertSame(7, (int) $product->sort_order);
        $this->assertSame('a, b, c', $product->meta_keywords);

        // Blank sort order = default 0 (not a NULL that would break the NOT NULL column).
        $this->actingAs($admin)->post(route('admin.products.store'), array_merge($payload, ['name' => ['ar' => 'آخر', 'en' => 'Other'], 'sort_order' => '']))->assertSessionHasNoErrors();
        $this->assertSame(0, (int) Product::latest('id')->first()->sort_order);

        $this->actingAs($admin)->get(route('admin.products.edit', $product))->assertOk()->assertSee('name="meta_keywords"', false)->assertSee('name="sort_order"', false);
    }
}
