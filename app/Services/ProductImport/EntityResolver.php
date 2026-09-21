<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Support\ProductSheet\RowNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds a category (or brand) from whatever an admin typed: its numeric ID,
 * its slug, or its name in Arabic or English (case, spacing and alef/yeh
 * variants ignored). Loaded once per pass — the lists are small.
 */
final class EntityResolver
{
    /** @var class-string<Model> */
    private $model;

    /** @var array<int,int>|null */
    private $ids;

    /** @var array<string,int> */
    private $bySlug = [];

    /** @var array<string,array<int,int>> name key => ids */
    private $byName = [];

    /** @param class-string<Model> $model */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * @return array{id:int}|array{error:string}  error = "not_found" | "ambiguous"
     */
    public function find(string $value): array
    {
        $this->load();
        $value = trim($value);

        if (preg_match('/^\d+$/', $value) && isset($this->ids[(int) $value])) {
            return ['id' => (int) $value];
        }

        $slug = mb_strtolower($value, 'UTF-8');
        if (isset($this->bySlug[$slug])) {
            return ['id' => $this->bySlug[$slug]];
        }

        $ids = $this->byName[RowNormalizer::matchKey($value)] ?? [];

        if (count($ids) === 1) {
            return ['id' => $ids[0]];
        }

        return ['error' => $ids === [] ? 'not_found' : 'ambiguous'];
    }

    private function load(): void
    {
        if ($this->ids !== null) {
            return;
        }

        $this->ids = [];

        foreach (($this->model)::query()->get() as $record) {
            $id = (int) $record->getKey();
            $this->ids[$id] = $id;
            $this->bySlug[mb_strtolower((string) $record->slug, 'UTF-8')] = $id;

            foreach ($record->getTranslations('name') as $name) {
                $key = RowNormalizer::matchKey((string) $name);

                if ($key !== '' && ! in_array($id, $this->byName[$key] ?? [], true)) {
                    $this->byName[$key][] = $id;
                }
            }
        }
    }
}
