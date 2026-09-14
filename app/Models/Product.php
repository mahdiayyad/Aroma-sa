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
        'reviews_avg_rating' => 'decimal:2',
        'reviews_count'     => 'integer',
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

    /**
     * Active customisation options (closure style, …). Filtered to is_active
     * here — unlike variants() — so a disabled option never reaches the PDP
     * or the "required option" enforcement.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** All options regardless of state — for the admin editor. */
    public function allOptions(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', ProductReview::STATUS_APPROVED);
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

        return $image ? $image->url() : asset('images/placeholder.svg');
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

    /**
     * Real low-stock signal for the product card — driven by the actual
     * stock_quantity column, never a fabricated/decorative "hurry" message.
     * Scoped to simple (non-variant) products only: a has_variants product's
     * stock is split per-variant, so a single aggregate number here could
     * read as "5 left" while the shopper's actual size/scent choice has
     * plenty — misleading in the other direction. Variant-level stock is
     * still shown correctly on the PDP's own variant picker.
     */
    public function isLowStock(int $threshold = 5): bool
    {
        if ($this->has_variants) {
            return false;
        }

        return $this->stock_quantity > 0 && $this->stock_quantity <= $threshold;
    }

    /**
     * True when the shopper must make a selection on the product page before
     * this can be added to cart — a variant, or a required customisation
     * option. Drives whether a listing card shows a direct "add" or a "view"
     * link. Uses the required_options_count withCount alias when a listing
     * eager-loaded it; otherwise falls back to a bounded count query.
     */
    public function requiresOptionSelection(): bool
    {
        if ($this->has_variants) {
            return true;
        }

        $count = $this->required_options_count
            ?? $this->options()->where('is_required', true)->count();

        return (int) $count > 0;
    }

    public function priceLabel(): string
    {
        return Money::format($this->base_price);
    }

    public function compareAtLabel(): ?string
    {
        return $this->isOnSale() ? Money::format($this->compare_at_price) : null;
    }

    public function discountPercent(): ?int
    {
        if (!$this->isOnSale()) {
            return null;
        }

        $compareAt = (float) $this->compare_at_price;
        $base = (float) $this->base_price;

        return (int) round((($compareAt - $base) / $compareAt) * 100);
    }

    /**
     * schema.org Product structured data (for the product detail page's
     * JSON-LD). @return array<string,mixed>
     */
    public function toSchemaOrgArray(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->name,
            'image' => [url($this->primaryImageUrl())],
            'description' => (string) ($this->translate('short_description') ?? ''),
            'sku' => $this->sku,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $this->currency ?? 'SAR',
                'price' => (string) $this->base_price,
                'availability' => $this->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ($this->brand) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $this->brand->name];
        }

        // Google requires aggregateRating to be backed by real review counts
        // (reviews_count is only ever incremented via approved reviews) —
        // omitted entirely rather than emitting a misleading 0/0 block.
        if ($this->reviews_count > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $this->reviews_avg_rating,
                'reviewCount' => (string) $this->reviews_count,
            ];
        }

        return $schema;
    }
}
