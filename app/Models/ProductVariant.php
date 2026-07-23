<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use App\Support\Formatting\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['name'];

    protected $casts = [
        'attributes'     => 'array',
        'price'          => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function priceLabel(): string
    {
        return Money::format($this->price);
    }
}
