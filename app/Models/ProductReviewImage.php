<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductReviewImage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    public function url(): string
    {
        if (preg_match('#^(https?:)?/#', $this->path)) {
            return $this->path;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
