<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class FxSyncResult extends Data
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public int $synced,
        public int $skipped,
        public array $messages,
    ) {
    }
}
