<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\FxRateData;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use App\Repositories\FxRateRepository;
use App\Repositories\FxRateRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxRateRepository::class)]
class FxRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): FxRateRepositoryInterface
    {
        return $this->app->make(FxRateRepositoryInterface::class);
    }

    public function test_upsert_creates_then_updates_same_row(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $data = new FxRateData(
            id: null,
            currencyFromId: $usd->id,
            currencyToId: $czk->id,
            rate: '23.1000000000',
            rateDate: CarbonImmutable::parse('2026-03-15'),
            source: FxSource::CNB,
        );

        $this->repository()->upsert($data);

        $updated = new FxRateData(
            id: null,
            currencyFromId: $usd->id,
            currencyToId: $czk->id,
            rate: '23.5000000000',
            rateDate: CarbonImmutable::parse('2026-03-15'),
            source: FxSource::CNB,
        );
        $result = $this->repository()->upsert($updated);

        $this->assertSame(1, FxRate::query()->count());
        $this->assertSame('23.5000000000', $result->rate);
    }

    public function test_latest_rate_returns_newest_on_or_before_date(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        foreach (['2026-03-01' => '22.0000000000', '2026-03-10' => '23.0000000000', '2026-03-20' => '24.0000000000'] as $date => $rate) {
            FxRate::factory()->create([
                'currency_from_id' => $usd->id,
                'currency_to_id' => $czk->id,
                'rate' => $rate,
                'rate_date' => $date,
                'source' => FxSource::CNB,
            ]);
        }

        $result = $this->repository()->latestRate($usd->id, $czk->id, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(FxRateData::class, $result);
        $this->assertSame('23.0000000000', $result->rate);
        $this->assertSame('2026-03-10', $result->rateDate->toDateString());
    }

    public function test_latest_rate_returns_null_when_no_rate_on_or_before(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '24.0000000000',
            'rate_date' => '2026-03-20',
            'source' => FxSource::CNB,
        ]);

        $result = $this->repository()->latestRate($usd->id, $czk->id, CarbonImmutable::parse('2026-03-15'));

        $this->assertNull($result);
    }
}
