<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Support\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function rootCategories(): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->roots()
            ->orderBy('sort_order')
            ->get();
    }

    public function featured(int $limit = 6): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->featured()
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        return $this->model->newQuery()
            ->active()
            ->where('slug', $slug)
            ->first();
    }
}
