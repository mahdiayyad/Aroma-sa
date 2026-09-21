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
 * Step 2 of an import: write the validated sheet to the catalog in ONE
 * database transaction (all-or-nothing). Database work only — the images were
 * already downloaded during validation.
 */
class ApplyProductImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

        if ($run) {
            $service->apply($run);
        }
    }

    public function failed(Throwable $e): void
    {
        $run = ProductImportRun::find($this->runId);

        if ($run && ! $run->isFinished()) {
            app(ProductImportService::class)->fail($run, $e);
        }
    }
}
