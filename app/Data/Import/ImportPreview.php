<?php

declare(strict_types=1);

namespace App\Data\Import;

use Illuminate\Support\Collection;

final readonly class ImportPreview
{
    /** @param Collection<int, ImportRowResult> $rows */
    public function __construct(
        public int $total,
        public int $validCount,
        public int $duplicateCount,
        public int $errorCount,
        public Collection $rows,
    ) {}
}
