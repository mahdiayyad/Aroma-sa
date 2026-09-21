<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The admin product list's search / category / status filters, in one place so
 * the list page and "Export current filter" always mean exactly the same rows.
 */
final class ProductFilters
{
    /** @param array<string,mixed> $filters q, category, active */
    public static function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $search = (string) $filters['q'];

            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name->en', 'like', "%{$search}%")
                    ->orWhere('name->ar', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', (int) $filters['category']);
        }

        if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== 'all') {
            $query->where('is_active', (string) $filters['active'] === '1');
        }

        return $query;
    }

    /** @param array<string,mixed> $filters */
    public static function any(array $filters): bool
    {
        return ! empty($filters['q']) || ! empty($filters['category'])
            || (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== 'all');
    }
}
