<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID
            || $this->status === self::STATUS_PROCESSING
            || $this->status === self::STATUS_SHIPPED
            || $this->status === self::STATUS_DELIVERED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_PAID;
    }

    public function statusBadgeClass(): string
    {
        switch ($this->status) {
            case self::STATUS_PAID:
            case self::STATUS_DELIVERED:
                return 'aroma-badge-success';
            case self::STATUS_PENDING:
                return 'aroma-badge-warning';
            case self::STATUS_PROCESSING:
            case self::STATUS_SHIPPED:
                return 'aroma-badge-info';
            case self::STATUS_CANCELLED:
                return 'aroma-badge-danger';
            default:
                return 'aroma-badge-neutral';
        }
    }
}
