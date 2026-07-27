<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-managed greeting card design a shopper can attach to a gift order.
 * One catalogue shared by checkout's Gift Options step and the cart gift studio.
 */
class GiftCard extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['name'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'greeting_card_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image);
    }
}
