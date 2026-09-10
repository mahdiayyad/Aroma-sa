<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function featured(int $limit = 8): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->featured()
            ->with(['images', 'brand'])
            ->withCount(['options as required_options_count' => fn ($q) => $q->where('is_required', true)])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function newArrivals(int $limit = 8): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->newArrivals()
            ->with(['images', 'brand'])
            ->withCount(['options as required_options_count' => fn ($q) => $q->where('is_required', true)])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Product
    {
        return $this->model->newQuery()
            ->active()
            ->with(['images', 'brand', 'category', 'variants', 'options.values'])
            ->where('slug', $slug)
            ->first();
    }

    public function giftSuggestions(Product $product, int $limit = 6): Collection
    {
        $base = function () use ($product) {
            return $this->model->newQuery()
                ->active()
                ->where('id', '!=', $product->id)
                ->where('is_gift_eligible', true)
                // In stock: either simple stock on hand, or variant-managed.
                ->where(function ($q) {
                    $q->where('stock_quantity', '>', 0)->orWhere('has_variants', true);
                })
                ->with(['images'])
                ->withCount(['options as required_options_count' => fn ($q) => $q->where('is_required', true)]);
        };

        $suggestions = $base()
            ->where('category_id', $product->category_id)
            ->latest()
            ->limit($limit)
            ->get();

        // Top up from the wider catalogue so the strip always has something.
        if ($suggestions->count() < $limit) {
            $filler = $base()
                ->whereNotIn('id', $suggestions->pluck('id')->all())
                ->orderByDesc('is_featured')
                ->latest()
                ->limit($limit - $suggestions->count())
                ->get();

            $suggestions = $suggestions->concat($filler);
        }

        return $suggestions;
    }

    public function paginateForCategory(Category $category, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        // Include immediate sub-categories so a parent listing shows everything.
        $categoryIds = $category->children()->pluck('id')->push($category->id)->all();

        $query = $this->model->newQuery()
            ->active()
            ->with(['images', 'brand'])
            ->withCount(['options as required_options_count' => fn ($q) => $q->where('is_required', true)])
            ->whereIn('category_id', $categoryIds);

        if ($brandSlug = Arr::get($filters, 'brand')) {
            $query->whereHas('brand', function ($q) use ($brandSlug) {
                $q->where('slug', $brandSlug);
            });
        }

        if (($min = Arr::get($filters, 'price_min')) !== null && $min !== '') {
            $query->where('base_price', '>=', (float) $min);
        }

        if (($max = Arr::get($filters, 'price_max')) !== null && $max !== '') {
            $query->where('base_price', '<=', (float) $max);
        }

        switch (Arr::get($filters, 'sort')) {
            case 'price_asc':
                $query->orderBy('base_price');
                break;
            case 'price_desc':
                $query->orderByDesc('base_price');
                break;
            default:
                $query->latest();
                break;
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
