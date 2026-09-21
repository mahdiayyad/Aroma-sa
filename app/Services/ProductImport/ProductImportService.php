<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Imports\ProductSheetReader;
use App\Imports\SheetFormatException;
use App\Jobs\ApplyProductImport;
use App\Jobs\ValidateProductImport;
use App\Models\ProductImportRun;
use App\Models\User;
use App\Support\ProductSheet\ProgressTracker;
use App\Support\ProductSheet\RowNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Orchestrates a product-sheet import from upload to completion.
 *
 *   createRun  -> start  -> validate (no catalog writes, downloads images to temp)
 *                        -> invalid | ready
 *   confirm    -> apply  (ONE transaction; files moved in; all-or-nothing)
 *
 * Small files run inline (instant, no worker needed); larger ones are queued on
 * the dedicated `imports` queue. Every step is logged to the `imports` channel
 * and persisted on the ProductImportRun for the history page.
 */
class ProductImportService
{
    private const COUNT_KEYS = [RowPlan::CREATE => 'created', RowPlan::UPDATE => 'updated', RowPlan::UNCHANGED => 'unchanged'];

    /** @var RowProcessor */
    private $processor;

    /** @var TempImageStore */
    private $temp;

    /** @var ProgressTracker */
    private $progress;

    public function __construct(RowProcessor $processor, TempImageStore $temp, ProgressTracker $progress)
    {
        $this->processor = $processor;
        $this->temp = $temp;
        $this->progress = $progress;
    }

    /* Upload & dispatch ---------------------------------------------------- */

    /** @param array{auto_apply?:bool,replace_images?:bool,ignore_image_failures?:bool} $options */
    public function createRun(User $user, UploadedFile $file, array $options): ProductImportRun
    {
        $run = ProductImportRun::create([
            'user_id'       => $user->id,
            'status'        => ProductImportRun::STATUS_PENDING,
            'original_name' => $file->getClientOriginalName(),
            'path'          => '',
            'options'       => [
                'auto_apply'            => (bool) ($options['auto_apply'] ?? false),
                'replace_images'        => (bool) ($options['replace_images'] ?? false),
                'ignore_image_failures' => (bool) ($options['ignore_image_failures'] ?? false),
                'locale'                => app()->getLocale(),
            ],
        ]);

        $run->update(['path' => $file->storeAs($run->workDir(), 'source.xlsx', 'local')]);

        try {
            $run->update(['total_rows' => ProductSheetReader::countRows($run->absolutePath())]);
        } catch (SheetFormatException $e) {
            $this->failFile($run, $e);

            return $run;
        }

        $this->log('upload', $run, ['file' => $run->original_name, 'rows' => $run->total_rows, 'options' => $run->options]);

        return $run;
    }

    /** Kick off validation: inline for small files, on the imports queue for big ones. */
    public function start(ProductImportRun $run): void
    {
        if ($run->isFinished()) {
            return; // the file was unreadable
        }

        if ($this->runInline($run)) {
            ValidateProductImport::dispatchSync($run->id);

            return;
        }

        $run->update(['status' => ProductImportRun::STATUS_QUEUED, 'phase' => ProductImportRun::PHASE_VALIDATE]);
        ValidateProductImport::dispatch($run->id)->onQueue((string) config('aroma.import.queue', 'imports'));
        $this->log('validation queued', $run);
    }

    /**
     * The admin reviewed the preview and confirmed: write it. Applying is
     * database-only (images were downloaded during validation), so anything up
     * to apply_sync_max_rows runs inline; bigger files go to the queue.
     */
    public function confirm(ProductImportRun $run): void
    {
        if (! $run->canConfirm()) {
            return;
        }

        if (config('queue.default') === 'sync' || $run->total_rows <= (int) config('aroma.import.apply_sync_max_rows', 500)) {
            ApplyProductImport::dispatchSync($run->id);

            return;
        }

        $run->update(['status' => ProductImportRun::STATUS_QUEUED, 'phase' => ProductImportRun::PHASE_APPLY]);
        ApplyProductImport::dispatch($run->id)->onQueue((string) config('aroma.import.queue', 'imports'));
        $this->log('apply queued', $run);
    }

    public function cancel(ProductImportRun $run): void
    {
        if (! $run->canCancel()) {
            return;
        }

        $run->update(['status' => ProductImportRun::STATUS_CANCELLED, 'finished_at' => now()]);
        $this->temp->clear($run->id);
        $this->log('cancelled', $run);
    }

    private function runInline(ProductImportRun $run): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        if ($run->total_rows > (int) config('aroma.import.sync_max_rows', 100)) {
            return false;
        }

        // Small file: peek at how many images it would download (slow network work belongs
        // on the queue). This shop's own image URLs — what an export contains — are matched
        // to the product's stored files, never fetched, so they don't count.
        $images = 0;
        $ownHost = strtolower((string) parse_url(url('/'), PHP_URL_HOST));

        try {
            $this->readSheet($run, new RunContext($run, RunContext::VALIDATE), function (int $row, array $cells) use (&$images, $ownHost) {
                foreach (RowNormalizer::list($cells['image_urls'] ?? '') as $url) {
                    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                    if ($host !== '' && $host !== $ownHost) {
                        $images++;
                    }
                }
            });
        } catch (Throwable $e) {
            return true; // validation will report the real problem
        }

        return $images <= (int) config('aroma.import.sync_max_images', 25);
    }

    /* Validate (dry run) --------------------------------------------------- */

    public function validate(ProductImportRun $run): void
    {
        $this->useLocale($run);
        $began = microtime(true);

        $run->forceFill([
            'status' => ProductImportRun::STATUS_VALIDATING, 'started_at' => now(), 'finished_at' => null,
            'errors' => null, 'warnings' => null, 'summary' => null, 'message' => null, 'error_count' => 0,
        ])->save();

        $this->log('validation started', $run);

        $ctx = new RunContext($run, RunContext::VALIDATE);
        $processed = 0;

        try {
            $this->progress->begin($run, ProductImportRun::PHASE_VALIDATE, (int) $run->total_rows);

            $this->readSheet($run, $ctx, function (int $row, array $cells) use ($ctx, $run, &$processed) {
                $plan = $this->processor->plan($row, $cells, $ctx);
                $ctx->record($plan);

                if (! $plan->hasErrors()) {
                    $ctx->counts[self::COUNT_KEYS[$plan->action]]++;
                    $ctx->counts['images_new'] += $plan->newImages;
                }

                $this->progress->advance($run, ++$processed);
            });

            if ($processed === 0) {
                $this->addFileError($ctx, 'file_no_rows');
            }
        } catch (SheetFormatException $e) {
            $this->addFileError($ctx, $e->errorKey, $e->replace);
        } catch (Throwable $e) {
            $this->fail($run, $e);

            return;
        }

        $status = $ctx->errorCount > 0 ? ProductImportRun::STATUS_INVALID : ProductImportRun::STATUS_READY;

        $run->forceFill([
            'status'         => $status,
            'phase'          => null,
            'processed_rows' => $processed,
            'total_rows'     => $processed,
            'summary'        => $ctx->counts + ['rows' => $processed, 'seconds' => round(microtime(true) - $began, 1)],
            'errors'         => $ctx->errors,
            'warnings'       => $ctx->warnings,
            'error_count'    => $ctx->errorCount,
            'finished_at'    => $status === ProductImportRun::STATUS_INVALID ? now() : null,
        ])->save();

        $this->progress->finish($run);

        if ($ctx->errorCount > count($ctx->errors)) {
            // More errors than the run keeps: the full list is what the downloadable report reads.
            Storage::disk('local')->put($run->workDir().'/errors.json', (string) json_encode($ctx->allErrors, JSON_UNESCAPED_UNICODE));
        }

        if ($status === ProductImportRun::STATUS_INVALID) {
            $this->temp->clear($run->id);
        }

        $this->log('validation finished', $run, ['status' => $status, 'errors' => $ctx->errorCount, 'summary' => $run->summary]);

        if ($status === ProductImportRun::STATUS_READY && $run->option('auto_apply')) {
            $this->confirm($run);
        }
    }

    /* Apply (one transaction) ---------------------------------------------- */

    public function apply(ProductImportRun $run): void
    {
        if (! in_array($run->status, [ProductImportRun::STATUS_READY, ProductImportRun::STATUS_QUEUED], true)) {
            return; // cancelled, already applied, or a replayed job
        }

        $this->useLocale($run);
        $began = microtime(true);

        $run->forceFill(['status' => ProductImportRun::STATUS_APPLYING, 'message' => null])->save();
        $this->log('apply started', $run, ['rows' => $run->total_rows]);

        $ctx = new RunContext($run, RunContext::APPLY);
        $processed = 0;

        try {
            $this->progress->begin($run, ProductImportRun::PHASE_APPLY, (int) $run->total_rows);

            DB::transaction(function () use ($run, $ctx, &$processed) {
                $this->readSheet($run, $ctx, function (int $row, array $cells) use ($run, $ctx, &$processed) {
                    $plan = $this->processor->plan($row, $cells, $ctx);

                    if ($plan->hasErrors()) {
                        // The catalog changed between the review and now (e.g. someone took the slug).
                        throw new ImportAbortedException($row, $plan->errors[0]['message']);
                    }

                    try {
                        $this->processor->persist($plan, $ctx);
                    } catch (Throwable $e) {
                        throw new ImportAbortedException($row, $e->getMessage(), $e);
                    }

                    // Cache only: rows written inside this transaction are invisible to the poller until commit.
                    $this->progress->advance($run, ++$processed, false);
                });
            });
        } catch (Throwable $e) {
            // The transaction has rolled back; take back the image files it had already stored.
            foreach ($ctx->createdFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            $this->fail($run, $e, $e instanceof ImportAbortedException ? $e->row : null);

            return;
        }

        // Committed: only now is it safe to delete the files of removed images.
        foreach ($ctx->filesToDelete as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->temp->clear($run->id);
        $this->progress->finish($run);

        // Downloads happened during validation; carry that number into the final summary.
        $counts = $ctx->counts;
        $counts['images_downloaded'] = (int) (($run->summary ?? [])['images_downloaded'] ?? 0);

        $run->forceFill([
            'status'         => ProductImportRun::STATUS_COMPLETED,
            'phase'          => null,
            'processed_rows' => $processed,
            'total_rows'     => $processed,
            'summary'        => $counts + ['rows' => $processed, 'seconds' => round(microtime(true) - $began, 1)],
            'finished_at'    => now(),
        ])->save();

        $this->log('completed', $run, ['summary' => $run->summary]);
    }

    /* Shared --------------------------------------------------------------- */

    /** @param callable(int,array<string,string|null>):void $onRow */
    private function readSheet(ProductImportRun $run, RunContext $ctx, callable $onRow): void
    {
        $index = ProductSheetReader::sheetIndex($run->absolutePath());

        $reader = new ProductSheetReader(
            $index,
            function (array $keys, array $unknown) use ($ctx) {
                if (! in_array('sku', $keys, true)) {
                    throw new SheetFormatException('file_no_sku_column');
                }

                $ctx->columns = $keys;

                foreach ($unknown as $heading) {
                    $ctx->warnings[] = ['row' => 1, 'column' => null, 'message' => (string) __('admin.products_io.errors.unknown_column', ['column' => $heading]), 'value' => $heading];
                }
            },
            $onRow
        );

        Excel::import($reader, $run->path, 'local', ExcelType::XLSX);
    }

    /** @param array<string,mixed> $replace */
    private function addFileError(RunContext $ctx, string $key, array $replace = []): void
    {
        $ctx->errorCount++;
        $ctx->errors[] = ['row' => 1, 'column' => null, 'message' => (string) __('admin.products_io.errors.'.$key, $replace), 'value' => null];
    }

    private function failFile(ProductImportRun $run, SheetFormatException $e): void
    {
        $this->useLocale($run);

        $run->forceFill([
            'status'      => ProductImportRun::STATUS_INVALID,
            'error_count' => 1,
            'errors'      => [['row' => 1, 'column' => null, 'message' => (string) __('admin.products_io.errors.'.$e->errorKey, $e->replace), 'value' => null]],
            'finished_at' => now(),
        ])->save();

        $this->log('file rejected', $run, ['reason' => $e->errorKey]);
    }

    /** @return array<int,array<string,mixed>> every recorded error, for the downloadable report */
    public function allErrors(ProductImportRun $run): array
    {
        $path = $run->workDir().'/errors.json';

        if (Storage::disk('local')->exists($path)) {
            $decoded = json_decode((string) Storage::disk('local')->get($path), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return (array) $run->errors;
    }

    /** Unexpected failure (or a rolled-back apply): record it and keep the reason readable. */
    public function fail(ProductImportRun $run, Throwable $e, ?int $row = null): void
    {
        $this->useLocale($run);
        $this->progress->finish($run);

        $message = $row !== null
            ? (string) __('admin.products_io.apply_failed_row', ['row' => $row, 'message' => $e->getMessage()])
            : (string) __('admin.products_io.import_failed', ['message' => $e->getMessage()]);

        $run->forceFill(['status' => ProductImportRun::STATUS_FAILED, 'phase' => null, 'message' => $message, 'finished_at' => now()])->save();
        $this->temp->clear($run->id);

        $this->log('failed', $run, ['row' => $row, 'error' => $e->getMessage(), 'exception' => get_class($e)], 'error');
    }

    private function useLocale(ProductImportRun $run): void
    {
        app()->setLocale((string) $run->option('locale', config('app.locale')));
    }

    /** @param array<string,mixed> $context */
    public function log(string $event, ProductImportRun $run, array $context = [], string $level = 'info'): void
    {
        Log::channel('imports')->{$level}('product import '.$event, array_merge([
            'run_id'  => $run->id,
            'user_id' => $run->user_id,
            'file'    => $run->original_name,
        ], $context));
    }
}
