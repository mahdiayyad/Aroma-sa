<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AramexShipment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'history' => 'array',
        'weight_kg' => 'decimal:3',
        'pieces' => 'integer',
        'last_event_at' => 'datetime',
    ];

    /** Claimed: the CreateShipments call is in flight. */
    public const STATUS_PENDING = 'pending';
    /** CreateShipments failed; the admin can retry. */
    public const STATUS_FAILED = 'failed';
    /** AWB issued, not yet scanned by Aramex. */
    public const STATUS_CREATED = 'created';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_RETURNED = 'returned';
    /** Failed attempt, hold, refusal and similar problems. */
    public const STATUS_EXCEPTION = 'exception';

    /** Statuses that no later tracking event can change. */
    public const TERMINAL_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_RETURNED,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function hasAwb(): bool
    {
        return filled($this->awb_number);
    }

    public function isTerminal(): bool
    {
        return in_array($this->shipping_status, self::TERMINAL_STATUSES, true);
    }

    /** @return array<int,array<string,mixed>> newest first */
    public function timeline(): array
    {
        $history = $this->history ?? [];

        usort($history, function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return $history;
    }

    /** Tone for <x-admin.badge>. */
    public function badgeTone(): string
    {
        switch ($this->shipping_status) {
            case self::STATUS_DELIVERED:
                return 'success';
            case self::STATUS_FAILED:
            case self::STATUS_EXCEPTION:
            case self::STATUS_RETURNED:
                return 'danger';
            case self::STATUS_PENDING:
                return 'warning';
            default:
                return 'info';
        }
    }
}
