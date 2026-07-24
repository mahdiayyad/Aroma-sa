<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared helpers for the admin write paths (API + Blade UI): unique slug
 * generation and public-disk uploads. Keeps controllers thin and consistent.
 */
trait HandlesAdminForms
{
    protected function uniqueSlug(string $table, ?string $slug, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $fallback) ?: Str::random(8);
        $candidate = $base;
        $i = 1;

        while (
            DB::table($table)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.(++$i);
        }

        return $candidate;
    }

    protected function storeUpload(?UploadedFile $file, string $folder): ?string
    {
        return $file ? $file->store($folder, 'public') : null;
    }
}
