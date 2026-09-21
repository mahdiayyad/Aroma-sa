<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use Illuminate\Support\Facades\Storage;

/**
 * Images downloaded while an import is being VALIDATED wait here (private
 * storage, per run) until the admin confirms; the apply step then moves them
 * into the public catalog folder. That way a row-by-row report can include
 * "this image URL doesn't work" before anything is written, and applying
 * doesn't need the network at all.
 */
class TempImageStore
{
    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    private function dir(int $runId): string
    {
        return 'imports/'.$runId.'/images';
    }

    public function put(int $runId, string $hash, string $bytes, string $ext): void
    {
        Storage::disk('local')->put($this->dir($runId).'/'.$hash.'.'.$ext, $bytes);
    }

    public function has(int $runId, string $hash): bool
    {
        return $this->locate($runId, $hash) !== null;
    }

    /** @return array{bytes:string,mime:string,ext:string}|null */
    public function get(int $runId, string $hash): ?array
    {
        $path = $this->locate($runId, $hash);

        if ($path === null) {
            return null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return ['bytes' => (string) Storage::disk('local')->get($path), 'mime' => self::MIME[$ext] ?? 'application/octet-stream', 'ext' => $ext];
    }

    public function clear(int $runId): void
    {
        Storage::disk('local')->deleteDirectory($this->dir($runId));
    }

    private function locate(int $runId, string $hash): ?string
    {
        foreach (array_keys(self::MIME) as $ext) {
            $path = $this->dir($runId).'/'.$hash.'.'.$ext;

            if (Storage::disk('local')->exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
