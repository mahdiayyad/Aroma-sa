<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Support\Repositories\RepositoryInterface;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /** Active top-level categories ordered for navigation/tiles. */
    public function rootCategories(): Collection;

    /** Active categories flagged as featured (homepage tiles). */
    public function featured(int $limit = 6): Collection;

    public function findActiveBySlug(string $slug): ?\App\Models\Category;
}
