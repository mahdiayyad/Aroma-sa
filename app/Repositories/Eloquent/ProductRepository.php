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
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Product
    {
        return $this->model->newQuery()
            ->active()
            ->with(['images', 'brand', 'category', 'variants'])
            ->where('slug', $slug)
            ->first();
    }

    public function paginateForCategory(Category $category, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        // Include immediate sub-categories so a parent listing shows everything.
        $categoryIds = $category->children()->pluck('id')->push($category->id)->all();

        $query = $this->model->newQuery()
            ->active()
            ->with(['images', 'brand'])
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
