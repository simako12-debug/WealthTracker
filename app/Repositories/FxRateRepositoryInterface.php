<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\FxRateData;
use Carbon\CarbonImmutable;

interface FxRateRepositoryInterface
{
    public function upsert(FxRateData $data): FxRateData;

    public function latestRate(
        string $currencyFromId,
        string $currencyToId,
        CarbonImmutable $onOrBefore,
    ): ?FxRateData;
}
