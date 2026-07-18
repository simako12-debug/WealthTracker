<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxRate::class)]
class FxRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_fx_rate_stores_high_precision_rate_and_relations(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $rate = FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '23.1234567890',
            'rate_date' => '2026-03-15',
            'source' => FxSource::CNB,
        ]);

        $this->assertSame('USD', $rate->currencyFrom->code);
        $this->assertSame('CZK', $rate->currencyTo->code);
        $this->assertSame('23.1234567890', $rate->rate);
        $this->assertSame('2026-03-15', $rate->rate_date->toDateString());
        $this->assertSame(FxSource::CNB, $rate->source);
    }
}
