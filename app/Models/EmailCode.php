<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCode extends Model
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_ADD_CREDENTIALS = 'add_credentials';

    protected $guarded = ['id'];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
