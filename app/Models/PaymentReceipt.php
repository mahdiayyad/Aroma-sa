<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'emailed_at' => 'datetime',
        'sending_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isEmailed(): bool
    {
        return $this->emailed_at !== null;
    }

    public function downloadName(): string
    {
        return 'receipt-'.$this->receipt_number.'.pdf';
    }
}
