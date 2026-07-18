<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Data\CurrencyPairData;
use App\Data\FxRateData;
use App\Enums\FxSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final readonly class CnbRateProvider implements RateProviderInterface
{
    private const string URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';
    private const string CZK = 'CZK';
    private const int SCALE = 10;

    public function source(): FxSource
    {
        return FxSource::CNB;
    }

    /**
     * @param  Collection<int, CurrencyPairData>  $pairs
     * @return Collection<int, FxRateData>
     */
    public function fetchRates(Collection $pairs): Collection
    {
        $response = Http::get(self::URL);

        if ($response->failed() === true) {
            return new Collection();
        }

        [$date, $perUnit] = $this->parse($response->body());

        return $pairs
            ->map(function (CurrencyPairData $pair) use ($date, $perUnit): ?FxRateData {
                $rate = $this->rateForPair($pair, $perUnit);

                if ($rate === null) {
                    return null;
                }

                return new FxRateData(
                    id: null,
                    currencyFromId: $pair->baseCurrencyId,
                    currencyToId: $pair->quoteCurrencyId,
                    rate: $rate,
                    rateDate: $date,
                    source: FxSource::CNB,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, string>  $perUnit  CZK per 1 unit of the currency code
     */
    private function rateForPair(CurrencyPairData $pair, array $perUnit): ?string
    {
        if ($pair->quoteCurrencyCode === self::CZK) {
            return $perUnit[$pair->baseCurrencyCode] ?? null;
        }

        if ($pair->baseCurrencyCode === self::CZK) {
            $quote = $perUnit[$pair->quoteCurrencyCode] ?? null;

            return $quote === null ? null : bcdiv('1', $quote, self::SCALE);
        }

        return null;
    }

    /**
     * @return array{0: CarbonImmutable, 1: array<string, string>}
     */
    private function parse(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        $header = array_shift($lines) ?? '';
        $datePart = trim(explode('#', $header)[0]);
        $date = CarbonImmutable::createFromFormat('d.m.Y', $datePart)->startOfDay();

        array_shift($lines); // column header "zemlje|měna|množství|kód|kurz"

        $perUnit = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = explode('|', $line);
            if (count($cols) < 5) {
                continue;
            }
            $amount = $cols[2];
            $code = $cols[3];
            $rate = str_replace(',', '.', $cols[4]);
            $perUnit[$code] = bcdiv($rate, $amount, self::SCALE);
        }

        return [$date, $perUnit];
    }
}
