<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-model for the storefront catalog. Controllers call this service; it
 * composes the category/brand/product repositories so views stay dumb and the
 * query logic is reusable and testable.
 */
class CatalogService extends BaseService
{
    /** @var CategoryRepositoryInterface */
    private $categories;

    /** @var BrandRepositoryInterface */
    private $brands;

    /** @var ProductRepositoryInterface */
    private $products;

    public function __construct(
        CategoryRepositoryInterface $categories,
        BrandRepositoryInterface $brands,
        ProductRepositoryInterface $products
    ) {
        $this->categories = $categories;
        $this->brands     = $brands;
        $this->products   = $products;
    }

    /** @return array<string,mixed> Data for the homepage sections. */
    public function homepage(): array
    {
        $featuredCategories = $this->categories->featured(6);

        return [
            'featuredCategories' => $featuredCategories->isNotEmpty()
                ? $featuredCategories
                : $this->categories->rootCategories(),
            'featuredProducts' => $this->products->featured(8),
            'newArrivals'      => $this->products->newArrivals(8),
        ];
    }

    public function findCategory(string $slug): ?Category
    {
        return $this->categories->findActiveBySlug($slug);
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function productsForCategory(Category $category, array $filters = []): LengthAwarePaginator
    {
        return $this->products->paginateForCategory($category, $filters);
    }

    public function activeBrands(): Collection
    {
        return $this->brands->active();
    }

    public function findProduct(string $slug): ?Product
    {
        return $this->products->findActiveBySlug($slug);
    }
}
