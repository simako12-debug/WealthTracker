<?php

declare(strict_types=1);

namespace App\Data\Import;

use Illuminate\Support\Collection;

final readonly class ImportResult
{
    /** @param Collection<int, ImportRowResult> $rows */
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $failed,
        public Collection $rows,
    ) {}
}
