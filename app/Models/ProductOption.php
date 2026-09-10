<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A per-product customisation the shopper picks before adding to cart —
 * "Closure Style" (Open / Closed) today, and the same table carries sleeve
 * style / length / fabric / embroidery / colour later with no schema change.
 * Distinct from ProductVariant, which owns the one axis that needs its own
 * price + stock (size / volume).
 */
class ProductOption extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['label'];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order');
    }

    public function activeValues(): HasMany
    {
        return $this->values()->where('is_active', true);
    }

    public function defaultValue(): ?ProductOptionValue
    {
        return $this->values->firstWhere('is_default', true);
    }
}
