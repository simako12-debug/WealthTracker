<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Data\FxRateData;
use App\Enums\FxSource;
use Illuminate\Support\Collection;

interface RateProviderInterface
{
    public function source(): FxSource;

    /**
     * @param  Collection<int, \App\Data\CurrencyPairData>  $pairs
     * @return Collection<int, FxRateData>
     */
    public function fetchRates(Collection $pairs): Collection;
}
