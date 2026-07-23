<?php

declare(strict_types=1);

namespace App\Support\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contract for all repositories. Controllers/services depend on repository
 * interfaces (bound in RepositoryServiceProvider) rather than concrete Eloquent
 * queries, keeping data access swappable and testable.
 *
 * (Signatures avoid PHP 8 union types so the codebase stays PHP 7.4 compatible.)
 */
interface RepositoryInterface
{
    public function all(array $columns = ['*']): Collection;

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /** @param int|string $id */
    public function find($id, array $columns = ['*']): ?Model;

    /** @param int|string $id */
    public function findOrFail($id, array $columns = ['*']): Model;

    public function create(array $attributes): Model;

    /** @param int|string $id */
    public function update($id, array $attributes): Model;

    /** @param int|string $id */
    public function delete($id): bool;

    public function newQuery(): Builder;
}
