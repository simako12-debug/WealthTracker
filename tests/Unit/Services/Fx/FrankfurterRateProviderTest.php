<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fx;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use App\Services\Fx\FrankfurterRateProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FrankfurterRateProvider::class)]
class FrankfurterRateProviderTest extends TestCase
{
    private function pair(string $baseCode, string $quoteCode): CurrencyPairData
    {
        return new CurrencyPairData(
            id: 'pair',
            baseCurrencyId: 'id-' . $baseCode,
            baseCurrencyCode: $baseCode,
            quoteCurrencyId: 'id-' . $quoteCode,
            quoteCurrencyCode: $quoteCode,
            source: FxSource::FRANKFURTER,
            isActive: true,
        );
    }

    public function testFetchRatesReturnsDirectRate(): void
    {
        Http::fake(['*frankfurter.app*' => Http::response([
            'amount' => 1.0,
            'base' => 'USD',
            'date' => '2026-07-17',
            'rates' => ['EUR' => 0.92],
        ], 200)]);

        $rates = (new FrankfurterRateProvider())->fetchRates(new Collection([$this->pair('USD', 'EUR')]));

        $this->assertCount(1, $rates);
        $rate = $rates->first();
        $this->assertSame('id-USD', $rate->currencyFromId);
        $this->assertSame('id-EUR', $rate->currencyToId);
        $this->assertSame('0.9200000000', $rate->rate);
        $this->assertSame('2026-07-17', $rate->rateDate->toDateString());
        $this->assertSame(FxSource::FRANKFURTER, $rate->source);
    }

    public function testSkipsWhenSymbolMissingOrRequestFails(): void
    {
        Http::fake(['*frankfurter.app*' => Http::response(['amount' => 1.0, 'base' => 'USD', 'date' => '2026-07-17', 'rates' => []], 200)]);

        $rates = (new FrankfurterRateProvider())->fetchRates(new Collection([$this->pair('USD', 'EUR')]));

        $this->assertCount(0, $rates);
    }
}
