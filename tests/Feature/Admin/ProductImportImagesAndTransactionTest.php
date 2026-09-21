<?php

namespace Tests\Feature\Admin;

use App\Jobs\ApplyProductImport;
use App\Jobs\ValidateProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportRun;
use App\Models\User;
use App\Services\ProductImport\ImageFetcher;
use App\Services\ProductImport\ProductImportService;
use App\Support\ProductSheet\ProgressTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductWorkbooks;
use Tests\TestCase;

/**
 * The parts of the import with real consequences: image download/dedupe/replace,
 * the all-or-nothing transaction, catalog drift between review and apply, and
 * the queued path with progress.
 */
class ProductImportImagesAndTransactionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProductWorkbooks;

    private const IMG_A = 'https://images.example.test/a.png';
    private const IMG_B = 'https://images.example.test/b.png';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        app()->setLocale('en');

        Category::factory()->create(['name' => ['en' => 'Perfumes', 'ar' => 'العطور'], 'slug' => 'perfumes']);

        // A public address for every host, so no real DNS is needed.
        $this->app->bind(ImageFetcher::class, function () {
            return new ImageFetcher(function () {
                return ['93.184.216.34'];
            });
        });
    }

    private function fakeImages(): void
    {
        Http::fake(['images.example.test/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);
    }

    private function runImport(array $rows, array $options = [], bool $confirm = true): ProductImportRun
    {
        $service = app(ProductImportService::class);
        $run = $service->createRun(User::factory()->create(['role' => User::ROLE_ADMIN]), $this->workbook($rows), $options);
        $service->start($run);
        $run = $run->fresh();

        if ($confirm && $run->status === ProductImportRun::STATUS_READY) {
            $service->confirm($run);
            $run = $run->fresh();
        }

        return $run;
    }

    /* Images --------------------------------------------------------------- */

    public function test_images_are_downloaded_stored_and_the_first_is_primary(): void
    {
        $this->fakeImages();

        $run = $this->runImport([$this->row(['sku' => 'IMG-1', 'image_urls' => self::IMG_A.'|'.self::IMG_B])]);

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, json_encode($run->errors).$run->message);

        $product = Product::where('sku', 'IMG-1')->first();
        $images = $product->images()->orderBy('sort_order')->get();

        $this->assertCount(2, $images);
        $this->assertTrue((bool) $images[0]->is_primary);
        $this->assertFalse((bool) $images[1]->is_primary);
        $this->assertSame(self::IMG_A, $images[0]->source_url);
        $this->assertSame(ImageFetcher::hash(self::IMG_A), $images[0]->source_hash);
        $this->assertSame('public', $images[0]->disk);
        Storage::disk('public')->assertExists($images[0]->path);
        Storage::disk('public')->assertExists($images[1]->path);
        $this->assertSame(2, $run->summary['images_added']);
        $this->assertSame(2, $run->summary['images_downloaded']);
    }

    public function test_reimporting_the_same_image_links_does_not_download_or_duplicate(): void
    {
        $this->fakeImages();
        $row = $this->row(['sku' => 'IMG-2', 'image_urls' => self::IMG_A]);

        $this->runImport([$row]);
        Http::assertSentCount(1);

        $second = $this->runImport([$row]);

        Http::assertSentCount(1); // no second download
        $this->assertSame(1, Product::where('sku', 'IMG-2')->first()->images()->count());
        $this->assertSame(1, $second->summary['unchanged']);
    }

    public function test_an_export_style_own_storage_url_is_recognised_as_an_existing_image(): void
    {
        Http::fake();
        $product = Product::factory()->create(['category_id' => Category::first()->id, 'sku' => 'OWN-1']);
        Storage::disk('public')->put('products/existing.jpg', $this->pngBytes());
        $image = $product->images()->create(['disk' => 'public', 'path' => 'products/existing.jpg', 'is_primary' => true, 'sort_order' => 1]);

        // The URL the export writes for that image — even from another host.
        $run = $this->runImport([['sku' => 'OWN-1', 'image_urls' => 'https://staging.example.com/storage/products/existing.jpg']]);

        Http::assertNothingSent();
        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, json_encode($run->errors).$run->message);
        $this->assertSame(1, $product->images()->count());
        $this->assertSame($image->id, $product->images()->first()->id);
    }

    public function test_a_broken_image_link_is_a_row_error_unless_failures_are_ignored(): void
    {
        Http::fake(['images.example.test/*' => Http::response('nope', 404)]);
        $row = $this->row(['sku' => 'BAD-IMG', 'image_urls' => self::IMG_A]);

        $strict = $this->runImport([$row]);
        $this->assertSame(ProductImportRun::STATUS_INVALID, $strict->status);
        $this->assertSame('image_urls', $strict->errors[0]['column']);
        $this->assertStringContainsString('404', $strict->errors[0]['message']);
        $this->assertSame(0, Product::count());

        $lenient = $this->runImport([$row], ['ignore_image_failures' => true]);
        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $lenient->status, $lenient->message.json_encode($lenient->errors));
        $this->assertSame(0, Product::where('sku', 'BAD-IMG')->first()->images()->count(), 'imported without the failing image');
        $this->assertNotEmpty($lenient->warnings);
    }

    public function test_internal_addresses_are_refused_before_any_request_is_made(): void
    {
        Http::fake();
        $this->app->bind(ImageFetcher::class, function () {
            return new ImageFetcher(function (string $host) {
                return $host === 'internal.example.test' ? ['10.0.0.8'] : ['93.184.216.34'];
            });
        });

        $run = $this->runImport([
            $this->row(['sku' => 'SSRF-1', 'image_urls' => 'http://internal.example.test/secret.png']),
            $this->row(['sku' => 'SSRF-2', 'image_urls' => 'http://127.0.0.1/x.png']),
            $this->row(['sku' => 'SSRF-3', 'image_urls' => 'file:///etc/passwd']),
        ]);

        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);
        $this->assertCount(3, $run->errors);
        Http::assertNothingSent();
    }

    public function test_non_images_and_svg_are_rejected_even_with_an_image_content_type(): void
    {
        Http::fake(['images.example.test/*' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 200, ['Content-Type' => 'image/png'])]);

        $run = $this->runImport([$this->row(['sku' => 'SVG-1', 'image_urls' => self::IMG_A])]);

        $this->assertSame(ProductImportRun::STATUS_INVALID, $run->status);
        $this->assertStringContainsString('not an image', $run->errors[0]['message']);
    }

    public function test_replace_images_removes_unlisted_images_and_their_files(): void
    {
        $this->fakeImages();
        $product = Product::factory()->create(['category_id' => Category::first()->id, 'sku' => 'REP-1']);
        Storage::disk('public')->put('products/old.png', $this->pngBytes());
        $old = $product->images()->create(['disk' => 'public', 'path' => 'products/old.png', 'is_primary' => true, 'sort_order' => 1]);

        $run = $this->runImport([['sku' => 'REP-1', 'image_urls' => self::IMG_B]], ['replace_images' => true]);

        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->status, $run->message.json_encode($run->errors));
        $this->assertSame(1, $product->images()->count());
        $this->assertDatabaseMissing('product_images', ['id' => $old->id]);
        Storage::disk('public')->assertMissing('products/old.png');
        $this->assertSame(self::IMG_B, $product->images()->first()->source_url);
        $this->assertTrue((bool) $product->images()->first()->is_primary);
        $this->assertSame(1, $run->summary['images_removed']);
    }

    public function test_appending_keeps_existing_images_and_a_clear_token_removes_them_all(): void
    {
        $this->fakeImages();
        $product = Product::factory()->create(['category_id' => Category::first()->id, 'sku' => 'APP-1']);
        Storage::disk('public')->put('products/keep.png', $this->pngBytes());
        $product->images()->create(['disk' => 'public', 'path' => 'products/keep.png', 'is_primary' => true, 'sort_order' => 1]);

        $this->runImport([['sku' => 'APP-1', 'image_urls' => self::IMG_A]]);
        $this->assertSame(2, $product->images()->count(), 'appended, not replaced');
        $this->assertTrue((bool) $product->images()->where('path', 'products/keep.png')->first()->is_primary, 'the existing primary stays primary');

        $this->runImport([['sku' => 'APP-1', 'image_urls' => '[clear]']]);
        $this->assertSame(0, $product->images()->count());
        Storage::disk('public')->assertMissing('products/keep.png');
    }

    /* Transaction ---------------------------------------------------------- */

    public function test_a_failure_part_way_through_apply_rolls_everything_back_and_removes_stored_images(): void
    {
        $this->fakeImages();

        // Validation passes; the third product blows up while being written.
        Product::creating(function (Product $product) {
            if ($product->sku === 'BOOM') {
                throw new \RuntimeException('disk full');
            }
        });

        $run = $this->runImport([
            $this->row(['sku' => 'OK-1', 'image_urls' => self::IMG_A]),
            $this->row(['sku' => 'OK-2', 'image_urls' => self::IMG_B]),
            $this->row(['sku' => 'BOOM']),
        ]);

        $this->assertSame(ProductImportRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Row 4', (string) $run->message);
        $this->assertStringContainsString('disk full', (string) $run->message);
        $this->assertSame(0, Product::count(), 'no partial import');
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('products'), 'files stored before the failure are removed');
    }

    public function test_catalog_drift_between_review_and_confirm_aborts_without_writing(): void
    {
        $service = app(ProductImportService::class);
        $run = $service->createRun(User::factory()->create(['role' => User::ROLE_ADMIN]), $this->workbook([
            $this->row(['sku' => 'D-1', 'slug' => 'wanted-slug']),
            $this->row(['sku' => 'D-2']),
        ]), []);
        $service->start($run);
        $this->assertSame(ProductImportRun::STATUS_READY, $run->fresh()->status);

        // Someone else takes the slug while the admin is reviewing.
        Product::factory()->create(['category_id' => Category::first()->id, 'sku' => 'INTRUDER', 'slug' => 'wanted-slug']);

        $service->confirm($run->fresh());
        $run = $run->fresh();

        $this->assertSame(ProductImportRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Row 2', (string) $run->message);
        $this->assertNull(Product::where('sku', 'D-2')->first(), 'nothing from the file was written');
    }

    public function test_a_discarded_import_writes_nothing_and_cannot_be_confirmed(): void
    {
        $service = app(ProductImportService::class);
        $run = $service->createRun(User::factory()->create(['role' => User::ROLE_ADMIN]), $this->workbook([$this->row(['sku' => 'C-1'])]), []);
        $service->start($run);

        $service->cancel($run->fresh());
        $service->confirm($run->fresh());

        $this->assertSame(ProductImportRun::STATUS_CANCELLED, $run->fresh()->status);
        $this->assertSame(0, Product::count());
    }

    /* Queue & progress ------------------------------------------------------ */

    public function test_big_files_go_to_the_imports_queue_and_finish_through_the_jobs(): void
    {
        config(['queue.default' => 'database', 'aroma.import.sync_max_rows' => 1, 'aroma.import.apply_sync_max_rows' => 1]);
        Queue::fake();

        $service = app(ProductImportService::class);
        $run = $service->createRun(User::factory()->create(['role' => User::ROLE_ADMIN]), $this->workbook([
            $this->row(['sku' => 'Q-1']), $this->row(['sku' => 'Q-2']), $this->row(['sku' => 'Q-3']),
        ]), []);

        $service->start($run);

        Queue::assertPushedOn('imports', ValidateProductImport::class);
        $this->assertSame(ProductImportRun::STATUS_QUEUED, $run->fresh()->status);
        $this->assertSame(0, Product::count());

        // The worker picks it up.
        (new ValidateProductImport($run->id))->handle($service);
        $this->assertSame(ProductImportRun::STATUS_READY, $run->fresh()->status);

        $service->confirm($run->fresh());
        Queue::assertPushedOn('imports', ApplyProductImport::class);
        $this->assertSame(ProductImportRun::STATUS_QUEUED, $run->fresh()->status);

        (new ApplyProductImport($run->id))->handle($service);
        $this->assertSame(ProductImportRun::STATUS_COMPLETED, $run->fresh()->status);
        $this->assertSame(3, Product::count());
    }

    public function test_the_progress_snapshot_reads_live_counters_and_finishes_at_100_percent(): void
    {
        $tracker = app(ProgressTracker::class);
        $run = ProductImportRun::create(['status' => ProductImportRun::STATUS_APPLYING, 'original_name' => 'x.xlsx', 'path' => 'x']);

        $tracker->begin($run, ProductImportRun::PHASE_APPLY, 200);
        $tracker->advance($run, 50, false); // inside the apply transaction: cache only

        $snapshot = $tracker->snapshot($run->fresh());
        $this->assertSame(50, $snapshot['processed']);
        $this->assertSame(200, $snapshot['total']);
        $this->assertSame(25, $snapshot['percent']);
        $this->assertTrue($snapshot['active']);

        $run->update(['status' => ProductImportRun::STATUS_COMPLETED]);
        $tracker->finish($run);
        $this->assertSame(100, $tracker->snapshot($run->fresh())['percent']);
    }
}
