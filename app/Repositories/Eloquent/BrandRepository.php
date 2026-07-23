<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Support\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    public function active(): Collection
    {
        return $this->model->newQuery()
            ->active()
            ->orderBy('id')
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Brand
    {
        return $this->model->newQuery()
            ->active()
            ->where('slug', $slug)
            ->first();
    }
}
