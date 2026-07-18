<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ConversionResult;
use App\Models\Currency;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\FxRateRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class CurrencyConverter
{
    private const string CZK = 'CZK';

    public function __construct(
        private CurrencyRepositoryInterface $currencies,
        private FxRateRepositoryInterface $rates,
    ) {
    }

    public function toCzk(string $amount, Currency $from, CarbonImmutable $date): ?ConversionResult
    {
        if ($from->code === self::CZK) {
            return new ConversionResult(amount: $amount, rate: '1', rateDate: $date);
        }

        $czk = $this->currencies->findByCode(self::CZK);

        if ($czk === null) {
            return null;
        }

        $rate = $this->rates->latestRate($from->id, $czk->id, $date);

        if ($rate === null) {
            return null;
        }

        return new ConversionResult(
            amount: bcmul($amount, $rate->rate, 10),
            rate: $rate->rate,
            rateDate: $rate->rateDate,
        );
    }
}
