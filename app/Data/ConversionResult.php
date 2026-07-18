<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ConversionResult extends Data
{
    public function __construct(
        public string $amount,
        public string $rate,
        public CarbonImmutable $rateDate,
    ) {
    }
}
