<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
        ];
    }
}
