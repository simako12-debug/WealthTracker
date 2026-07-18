<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use App\Models\CurrencyPair;
use Illuminate\Support\Collection;

final readonly class CurrencyPairRepository implements CurrencyPairRepositoryInterface
{
    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection
    {
        return CurrencyPair::query()
            ->with(['baseCurrency', 'quoteCurrency'])
            ->where('is_active', true)
            ->get()
            ->map(fn (CurrencyPair $pair): CurrencyPairData => CurrencyPairData::fromModel($pair));
    }
}
