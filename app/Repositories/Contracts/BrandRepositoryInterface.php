<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Support\Repositories\RepositoryInterface;
use Illuminate\Support\Collection;

interface BrandRepositoryInterface extends RepositoryInterface
{
    public function active(): Collection;

    public function findActiveBySlug(string $slug): ?\App\Models\Brand;
}
