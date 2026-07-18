<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Data\CurrencyPairData;
use App\Data\FxRateData;
use App\Enums\FxSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final readonly class FrankfurterRateProvider implements RateProviderInterface
{
    private const string URL = 'https://api.frankfurter.app/latest';
    private const int SCALE = 10;

    public function source(): FxSource
    {
        return FxSource::FRANKFURTER;
    }

    /**
     * @param  Collection<int, CurrencyPairData>  $pairs
     * @return Collection<int, FxRateData>
     */
    public function fetchRates(Collection $pairs): Collection
    {
        return $pairs
            ->map(fn (CurrencyPairData $pair): ?FxRateData => $this->fetchOne($pair))
            ->filter()
            ->values();
    }

    private function fetchOne(CurrencyPairData $pair): ?FxRateData
    {
        $response = Http::get(self::URL, [
            'base' => $pair->baseCurrencyCode,
            'symbols' => $pair->quoteCurrencyCode,
        ]);

        if ($response->failed() === true) {
            return null;
        }

        $value = $response->json('rates.' . $pair->quoteCurrencyCode);
        $date = $response->json('date');

        if ($value === null || $date === null) {
            return null;
        }

        return new FxRateData(
            id: null,
            currencyFromId: $pair->baseCurrencyId,
            currencyToId: $pair->quoteCurrencyId,
            rate: bcadd((string) $value, '0', self::SCALE),
            rateDate: CarbonImmutable::parse($date)->startOfDay(),
            source: FxSource::FRANKFURTER,
        );
    }
}
