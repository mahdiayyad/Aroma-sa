<?php

declare(strict_types=1);

namespace App\Imports;

use App\Support\ProductSheet\Columns;
use App\Support\ProductSheet\RowNormalizer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * The per-sheet importer Laravel Excel drives (chunked ToCollection).
 *
 * @internal
 */
class ProductSheetWorker implements ToCollection, WithChunkReading, WithCalculatedFormulas
{
    /** @var callable */
    private $onHeader;

    /** @var callable */
    private $onRow;

    /** @var int rows consumed by previous chunks */
    private $offset = 0;

    /** @var array<int,string|null> column index => column key */
    private $map = [];

    public function __construct(callable $onHeader, callable $onRow)
    {
        $this->onHeader = $onHeader;
        $this->onRow = $onRow;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows->values() as $i => $cells) {
            $rowNumber = $this->offset + $i + 1;
            $cells = $cells instanceof Collection ? $cells->all() : (array) $cells;

            if ($rowNumber === 1) {
                $this->readHeader($cells);

                continue;
            }

            $mapped = [];
            $filled = false;

            foreach ($this->map as $index => $key) {
                if ($key === null) {
                    continue;
                }

                $value = RowNormalizer::cell($cells[$index] ?? null);
                $mapped[$key] = $value;
                $filled = $filled || $value !== null;
            }

            if ($filled) {
                ($this->onRow)($rowNumber, $mapped);
            }
        }

        $this->offset += $rows->count();
    }

    public function chunkSize(): int
    {
        return (int) config('aroma.import.chunk_size', 250);
    }

    /** @param array<int,mixed> $cells */
    private function readHeader(array $cells): void
    {
        $keys = [];
        $unknown = [];
        $seen = [];

        foreach ($cells as $index => $heading) {
            $heading = RowNormalizer::cell($heading);

            if ($heading === null) {
                $this->map[$index] = null;

                continue;
            }

            $key = Columns::resolve($heading);

            if ($key === null) {
                $unknown[] = $heading;
                $this->map[$index] = null;

                continue;
            }

            if (isset($seen[$key])) {
                throw new SheetFormatException('file_duplicate_column', ['column' => $heading]);
            }

            $seen[$key] = true;
            $this->map[$index] = $key;
            $keys[] = $key;
        }

        if ($keys === []) {
            throw new SheetFormatException('file_empty');
        }

        ($this->onHeader)($keys, $unknown);
    }
}
