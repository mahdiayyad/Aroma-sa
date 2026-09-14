<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    public const TYPE_REFERRAL_REWARD = 'referral_reward';
    public const TYPE_REFERRAL_SIGNUP_BONUS = 'referral_signup_bonus';
    public const TYPE_REDEMPTION = 'redemption';
    public const TYPE_ADMIN_ADJUSTMENT = 'admin_adjustment';
    public const TYPE_REFUND_REVERSAL = 'refund_reversal';
    public const TYPE_PROMOTIONAL_REWARD = 'promotional_reward';

    protected $guarded = ['id'];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
