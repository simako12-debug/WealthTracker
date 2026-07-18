<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyPair::class)]
class CurrencyPairTest extends TestCase
{
    use RefreshDatabase;

    public function test_pair_relates_to_base_and_quote_currencies(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $pair = CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
        ]);

        $this->assertSame('USD', $pair->baseCurrency->code);
        $this->assertSame('CZK', $pair->quoteCurrency->code);
        $this->assertSame(FxSource::CNB, $pair->source);
        $this->assertTrue($pair->is_active);
    }
}
