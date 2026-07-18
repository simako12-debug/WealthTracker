<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use Illuminate\Support\Collection;

interface CurrencyPairRepositoryInterface
{
    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection;
}
