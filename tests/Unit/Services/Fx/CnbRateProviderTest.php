<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fx;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use App\Services\Fx\CnbRateProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CnbRateProvider::class)]
class CnbRateProviderTest extends TestCase
{
    private const string BODY = "18.07.2026 #137\nzemě|měna|množství|kód|kurz\nUSA|dolar|1|USD|23,100\nEU|euro|1|EUR|25,300\nJaponsko|jen|100|JPY|15,000\n";

    private function pair(string $baseCode, string $quoteCode): CurrencyPairData
    {
        return new CurrencyPairData(
            id: 'pair-'.$baseCode.$quoteCode,
            baseCurrencyId: 'id-'.$baseCode,
            baseCurrencyCode: $baseCode,
            quoteCurrencyId: 'id-'.$quoteCode,
            quoteCurrencyCode: $quoteCode,
            source: FxSource::CNB,
            isActive: true,
        );
    }

    public function test_fetch_rates_for_foreign_to_czk_with_unit_normalization(): void
    {
        Http::fake(['*cnb.cz*' => Http::response(self::BODY, 200)]);

        $rates = (new CnbRateProvider)->fetchRates(new Collection([
            $this->pair('USD', 'CZK'),
            $this->pair('JPY', 'CZK'),
        ]));

        $this->assertCount(2, $rates);

        $usd = $rates->firstWhere('currencyFromId', 'id-USD');
        $this->assertSame('23.1000000000', $usd->rate);
        $this->assertSame('id-CZK', $usd->currencyToId);
        $this->assertSame('2026-07-18', $usd->rateDate->toDateString());
        $this->assertSame(FxSource::CNB, $usd->source);

        $jpy = $rates->firstWhere('currencyFromId', 'id-JPY');
        $this->assertSame('0.1500000000', $jpy->rate);
    }

    public function test_fetch_rates_inverts_czk_to_foreign(): void
    {
        Http::fake(['*cnb.cz*' => Http::response(self::BODY, 200)]);

        $rates = (new CnbRateProvider)->fetchRates(new Collection([$this->pair('CZK', 'USD')]));

        $this->assertCount(1, $rates);
        $this->assertSame('0.0432900432', $rates->first()->rate);
    }

    public function test_skips_unknown_currency_and_non_czk_pairs(): void
    {
        Http::fake(['*cnb.cz*' => Http::response(self::BODY, 200)]);

        $rates = (new CnbRateProvider)->fetchRates(new Collection([
            $this->pair('GBP', 'CZK'),
            $this->pair('USD', 'EUR'),
        ]));

        $this->assertCount(0, $rates);
    }

    public function test_returns_empty_on_http_failure(): void
    {
        Http::fake(['*cnb.cz*' => Http::response('', 500)]);

        $rates = (new CnbRateProvider)->fetchRates(new Collection([$this->pair('USD', 'CZK')]));

        $this->assertCount(0, $rates);
    }
}
