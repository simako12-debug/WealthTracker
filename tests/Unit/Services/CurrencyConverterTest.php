<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\ConversionResult;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use App\Services\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyConverter::class)]
class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private function converter(): CurrencyConverter
    {
        return $this->app->make(CurrencyConverter::class);
    }

    public function test_czk_to_czk_is_identity(): void
    {
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzk('1500.0000000000', $czk, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('1500.0000000000', $result->amount);
        $this->assertSame('1.0000000000', $result->rate);
    }

    public function test_converts_using_latest_rate_on_or_before_date(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '23.0000000000',
            'rate_date' => '2026-03-10',
            'source' => FxSource::CNB,
        ]);

        $result = $this->converter()->toCzk('10.0000000000', $usd, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('230.0000000000', $result->amount);
        $this->assertSame('23.0000000000', $result->rate);
        $this->assertSame('2026-03-10', $result->rateDate->toDateString());
    }

    public function test_returns_null_when_no_rate_available(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzk('10.0000000000', $usd, CarbonImmutable::parse('2026-03-15'));

        $this->assertNull($result);
    }
}
