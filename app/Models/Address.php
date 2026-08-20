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
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'location_code' => $this->location_code,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'region' => $this->region,
            'district' => $this->district,
            'country' => $this->country,
            'formatted_address' => $this->formatted_address,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
