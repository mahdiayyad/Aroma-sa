<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One product-sheet import: the uploaded file, its options, live progress and
 * the outcome. The pipeline moves it through
 *
 *   pending -> (queued) -> validating -> invalid | ready -> (queued) -> applying -> completed | failed
 *
 * "cancelled" can be reached from invalid/ready. Nothing is written to the
 * catalog until the apply step, which is one database transaction.
 */
class ProductImportRun extends Model
{
    use Prunable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_READY = 'ready';
    public const STATUS_APPLYING = 'applying';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PHASE_VALIDATE = 'validate';
    public const PHASE_APPLY = 'apply';

    protected $guarded = ['id'];

    protected $casts = [
        'options'    => 'array',
        'summary'    => 'array',
        'errors'     => 'array',
        'warnings'   => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function option(string $key, $default = false)
    {
        return ($this->options ?? [])[$key] ?? $default;
    }

    /** Still moving on its own (a poller should keep asking). */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_QUEUED, self::STATUS_VALIDATING, self::STATUS_APPLYING], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_INVALID, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_INVALID, self::STATUS_READY], true);
    }

    /** Absolute filesystem path of the uploaded workbook (private, local disk). */
    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function workDir(): string
    {
        return 'imports/'.$this->id;
    }

    /**
     * Old runs that are not going anywhere (and their files) are removed by `model:prune`.
     * "ready" counts: an upload nobody confirmed still holds its file and pre-downloaded images.
     */
    public function prunable(): Builder
    {
        $days = (int) config('aroma.import.retention_days', 30);

        return static::query()
            ->whereIn('status', [self::STATUS_INVALID, self::STATUS_READY, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])
            ->where('created_at', '<', now()->subDays($days));
    }

    protected function pruning(): void
    {
        Storage::disk('local')->deleteDirectory($this->workDir());
    }
}
