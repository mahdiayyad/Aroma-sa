<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    public const METHOD_NATIONAL_CODE = 'national_code';
    public const METHOD_MANUAL = 'manual';

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
            'method' => $this->method,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'location_code' => $this->location_code,
            'street_address' => $this->street_address,
            'building_number' => $this->building_number,
            'apartment_number' => $this->apartment_number,
            'city' => $this->city,
            'region' => $this->region,
            'district' => $this->district,
            'country' => $this->country,
            'formatted_address' => $this->formatted_address,
            'postal_code' => $this->postal_code,
            'additional_notes' => $this->additional_notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
