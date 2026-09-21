<?php

declare(strict_types=1);

namespace App\Support\ProductSheet;

use App\Models\ProductImportRun;
use Illuminate\Support\Facades\Cache;

/**
 * Live progress for an import run, readable by the browser while a worker (or
 * the request itself) is still busy.
 *
 * The counters live in the cache, not only in the database: the apply step
 * runs inside ONE transaction, and rows updated inside it are invisible to
 * the status endpoint until commit — so DB-only progress would read "0%"
 * for the entire apply. The database still gets the phase boundaries.
 */
final class ProgressTracker
{
    private const TTL_MINUTES = 120;
    private const DB_EVERY = 25;

    private static function key(ProductImportRun $run): string
    {
        return 'product-import:'.$run->id.':progress';
    }

    public function begin(ProductImportRun $run, string $phase, int $total): void
    {
        $run->forceFill(['phase' => $phase, 'total_rows' => $total, 'processed_rows' => 0]);
        $run->saveQuietly();

        Cache::put(self::key($run), ['phase' => $phase, 'processed' => 0, 'total' => $total], now()->addMinutes(self::TTL_MINUTES));
    }

    /** @param bool $persist false while inside the apply transaction (cache only) */
    public function advance(ProductImportRun $run, int $processed, bool $persist = true): void
    {
        $state = Cache::get(self::key($run), []);
        $state['processed'] = $processed;
        $state['phase'] = $state['phase'] ?? $run->phase;
        $state['total'] = $state['total'] ?? $run->total_rows;
        Cache::put(self::key($run), $state, now()->addMinutes(self::TTL_MINUTES));

        if ($persist && $processed % self::DB_EVERY === 0) {
            ProductImportRun::whereKey($run->id)->update(['processed_rows' => $processed]);
        }
    }

    public function finish(ProductImportRun $run): void
    {
        Cache::forget(self::key($run));
    }

    /** @return array<string,mixed> what the status endpoint returns */
    public function snapshot(ProductImportRun $run): array
    {
        $state = $run->isActive() ? Cache::get(self::key($run)) : null;

        $total = (int) ($state['total'] ?? $run->total_rows);
        // A run waiting for a worker has done none of the work of the phase it is queued for
        // (the counters still hold the previous phase: 620/620 from validation before apply).
        $processed = $run->status === ProductImportRun::STATUS_QUEUED ? 0 : (int) ($state['processed'] ?? $run->processed_rows);
        $phase = $state['phase'] ?? $run->phase;

        return [
            'id'          => $run->id,
            'status'      => $run->status,
            'phase'       => $phase,
            'processed'   => $processed,
            'total'       => $total,
            'percent'     => $run->status === ProductImportRun::STATUS_COMPLETED
                ? 100
                : ($total > 0 ? min(100, (int) floor($processed / $total * 100)) : 0),
            'active'      => $run->isActive(),
            'finished'    => $run->isFinished(),
            'error_count' => (int) $run->error_count,
            'summary'     => $run->summary,
            'message'     => $run->message,
        ];
    }
}
