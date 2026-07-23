<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use App\Support\Formatting\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['name', 'short_description', 'description', 'meta_title', 'meta_description'];

    protected $casts = [
        'base_price'        => 'decimal:2',
        'compare_at_price'  => 'decimal:2',
        'stock_quantity'    => 'integer',
        'has_variants'      => 'boolean',
        'is_active'         => 'boolean',
        'is_featured'       => 'boolean',
        'is_new_arrival'    => 'boolean',
        'is_gift_eligible'  => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* Relationships ------------------------------------------------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /* Scopes -------------------------------------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query->where('is_new_arrival', true);
    }

    /* Presentation helpers ------------------------------------------------ */

    public function primaryImageUrl(): string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        if (! $image) {
            return asset('images/placeholder.svg');
        }

        // Absolute URLs and root-relative paths (e.g. the seeded placeholder)
        // are returned as-is; everything else resolves against its storage disk.
        if (preg_match('#^(https?:)?/#', $image->path)) {
            return $image->path;
        }

        return Storage::disk($image->disk)->url($image->path);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->base_price;
    }

    public function inStock(): bool
    {
        if ($this->has_variants) {
            return $this->variants->sum('stock_quantity') > 0;
        }

        return $this->stock_quantity > 0;
    }

    public function priceLabel(): string
    {
        return Money::format($this->base_price);
    }

    public function compareAtLabel(): ?string
    {
        return $this->isOnSale() ? Money::format($this->compare_at_price) : null;
    }
}
