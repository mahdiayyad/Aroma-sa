<?php

declare(strict_types=1);

namespace App\Support\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Generic Eloquent-backed repository. Concrete repositories extend this and
 * inject their model, overriding/adding query methods as the domain needs.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @var Model */
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->newQuery()->get($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage, $columns);
    }

    /** @param int|string $id */
    public function find($id, array $columns = ['*']): ?Model
    {
        return $this->model->newQuery()->find($id, $columns);
    }

    /** @param int|string $id */
    public function findOrFail($id, array $columns = ['*']): Model
    {
        return $this->model->newQuery()->findOrFail($id, $columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    /** @param int|string $id */
    public function update($id, array $attributes): Model
    {
        $record = $this->findOrFail($id);
        $record->update($attributes);

        return $record;
    }

    /** @param int|string $id */
    public function delete($id): bool
    {
        return (bool) $this->findOrFail($id)->delete();
    }

    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }
}
