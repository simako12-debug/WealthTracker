<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use Illuminate\Support\Collection;

final readonly class CurrencyRepository implements CurrencyRepositoryInterface
{
    /** @return Collection<int, CurrencyData> */
    public function all(): Collection
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): CurrencyData => CurrencyData::fromModel($currency));
    }

    public function findByCode(string $code): ?CurrencyData
    {
        $currency = Currency::query()->where('code', $code)->first();

        return $currency === null ? null : CurrencyData::fromModel($currency);
    }
}
