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
class CurrencyConverterByCodeTest extends TestCase
{
    use RefreshDatabase;

    private function converter(): CurrencyConverter
    {
        return $this->app->make(CurrencyConverter::class);
    }

    public function test_czk_code_is_identity(): void
    {
        Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzkByCode('500.0000000000', 'CZK', CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('500.0000000000', $result->amount);
    }

    public function test_converts_by_code_using_latest_rate(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id, 'currency_to_id' => $czk->id,
            'rate' => '23.0000000000', 'rate_date' => '2026-03-10', 'source' => FxSource::CNB,
        ]);

        $result = $this->converter()->toCzkByCode('10.0000000000', 'USD', CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('230.0000000000', $result->amount);
    }

    public function test_returns_null_for_unknown_code(): void
    {
        Currency::factory()->create(['code' => 'CZK']);

        $this->assertNull($this->converter()->toCzkByCode('10.0000000000', 'ZZZ', CarbonImmutable::parse('2026-03-15')));
    }
}
