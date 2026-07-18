<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Repositories\CurrencyPairRepository;
use App\Repositories\CurrencyPairRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyPairRepository::class)]
class CurrencyPairRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyPairRepositoryInterface
    {
        return $this->app->make(CurrencyPairRepositoryInterface::class);
    }

    public function test_active_pairs_returns_only_active_with_currency_codes(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
            'is_active' => true,
        ]);
        CurrencyPair::factory()->create([
            'base_currency_id' => $eur->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
            'is_active' => false,
        ]);

        $pairs = $this->repository()->activePairs();

        $this->assertCount(1, $pairs);
        $this->assertContainsOnlyInstancesOf(CurrencyPairData::class, $pairs);

        $pair = $pairs->first();
        $this->assertSame('USD', $pair->baseCurrencyCode);
        $this->assertSame('CZK', $pair->quoteCurrencyCode);
        $this->assertSame(FxSource::CNB, $pair->source);
        $this->assertTrue($pair->isActive);
    }
}
