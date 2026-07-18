<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\FxRateData;
use App\Models\FxRate;
use Carbon\CarbonImmutable;

final readonly class FxRateRepository implements FxRateRepositoryInterface
{
    public function upsert(FxRateData $data): FxRateData
    {
        $rate = FxRate::query()->updateOrCreate(
            [
                'currency_from_id' => $data->currencyFromId,
                'currency_to_id' => $data->currencyToId,
                'rate_date' => $data->rateDate->toDateString(),
                'source' => $data->source,
            ],
            [
                'rate' => $data->rate,
            ],
        );

        return FxRateData::fromModel($rate);
    }

    public function latestRate(
        string $currencyFromId,
        string $currencyToId,
        CarbonImmutable $onOrBefore,
    ): ?FxRateData {
        $rate = FxRate::query()
            ->where('currency_from_id', $currencyFromId)
            ->where('currency_to_id', $currencyToId)
            ->where('rate_date', '<=', $onOrBefore->toDateString())
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();

        return $rate === null ? null : FxRateData::fromModel($rate);
    }
}
