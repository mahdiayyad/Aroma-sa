<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductImportRun;
use App\Services\ProductImport\ProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Step 1 of an import: read + validate the workbook (no catalog writes) and
 * pre-download images. Runs on the dedicated `imports` queue for big files, or
 * inline for small ones (dispatchSync).
 */
class ValidateProductImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Long files with many image downloads; retry_after (config/queue.php) is larger than this. */
    public $timeout = 1800;

    public $tries = 1;

    /** @var int */
    private $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
    }

    public function handle(ProductImportService $service): void
    {
        $run = ProductImportRun::find($this->runId);

        if ($run && ! $run->isFinished()) {
            $service->validate($run);
        }
    }

    /** Worker timeout / crash: don't leave the run "validating" forever. */
    public function failed(Throwable $e): void
    {
        $run = ProductImportRun::find($this->runId);

        if ($run && ! $run->isFinished()) {
            app(ProductImportService::class)->fail($run, $e);
        }
    }
}
