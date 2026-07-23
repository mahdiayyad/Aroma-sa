<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Category;
use App\Models\Product;
use App\Support\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function featured(int $limit = 8): Collection;

    public function newArrivals(int $limit = 8): Collection;

    public function findActiveBySlug(string $slug): ?Product;

    /**
     * Paginate active products in a category, honouring optional filters:
     *   brand (slug), price_min, price_max, sort (newest|price_asc|price_desc).
     *
     * @param array<string,mixed> $filters
     */
    public function paginateForCategory(Category $category, array $filters = [], int $perPage = 12): LengthAwarePaginator;
}
