<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Our record of one Tawked verification: its id and the result we last saw.
 * The one-time code itself never reaches this app — Tawked owns it.
 */
class PhoneVerification extends Model
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_CHANGE_PHONE = 'change_phone';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
