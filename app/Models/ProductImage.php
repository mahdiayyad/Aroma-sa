<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['alt'];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function url(): string
    {
        // Absolute URLs and root-relative paths (e.g. the seeded placeholder)
        // are returned as-is; everything else resolves against its storage disk.
        if (preg_match('#^(https?:)?/#', $this->path)) {
            return $this->path;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
