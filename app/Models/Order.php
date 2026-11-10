<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'purchase_tracked_at' => 'datetime',
        'shipping_fee_deducted_at' => 'datetime',
        // Gifting
        'is_gift' => 'boolean',
        'is_anonymous' => 'boolean',
        'gift_wrap_fee' => 'decimal:2',
        'greeting_card_fee' => 'decimal:2',
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

    /**
     * Statuses that represent a genuinely confirmed purchase — used for
     * "verified purchase" review badges and first-order promo eligibility.
     * Kept as one constant (rather than each caller re-deriving its own
     * list, as DashboardController used to) so they can never drift; mirrors
     * isPaid()'s own logic exactly.
     */
    public const PAID_STATUSES = [
        self::STATUS_PAID,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
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

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class, 'greeting_card_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function aramexShipment(): HasOne
    {
        return $this->hasOne(AramexShipment::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    /**
     * The amount a refund on $payment should default to — the remaining
     * captured amount, minus any return fee still due (see returnFee()).
     * The admin UI pre-fills this but the field stays editable — this is a
     * suggestion, not an enforced amount.
     */
    public function suggestedRefundAmount(Payment $payment): float
    {
        $remaining = round((float) $payment->amount - (float) $payment->refunded_amount, 2);

        return max(0.0, round($remaining - $this->returnFee(), 2));
    }

    /**
     * The return fee still due on this order. Only an order that reached the
     * customer can be returned, the fee is waived when the customer's return
     * request says the store was at fault, and it's taken once:
     * shipping_fee_deducted_at (named for the earlier rule of deducting
     * shipping) marks it done, so later partial refunds go out whole.
     */
    public function returnFee(): float
    {
        if ($this->shipping_fee_deducted_at !== null || $this->delivered_at === null) {
            return 0.0;
        }

        $reason = $this->refundRequests()
            ->where('status', '!=', RefundRequest::STATUS_REJECTED)
            ->latest('id')
            ->value('reason');

        return in_array($reason, RefundRequest::NO_FEE_REASONS, true) ? 0.0 : RefundRequest::RETURN_FEE;
    }

    /**
     * Call once a refund has completed and $payment is up to date. Records
     * the fee as taken, unless the admin handed back so much that it can't
     * have been kept. Returns whether it was taken.
     */
    public function recordReturnFee(Payment $payment): bool
    {
        $fee = $this->returnFee();
        $stillHeld = round((float) $payment->amount - (float) $payment->refunded_amount, 2);

        if ($fee <= 0 || $stillHeld < $fee) {
            return false;
        }

        $this->update(['shipping_fee_deducted_at' => now()]);

        return true;
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
