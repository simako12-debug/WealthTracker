<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fx;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\FxRate;
use App\Services\Fx\FxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxSyncService::class)]
class FxSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string CNB_BODY = "18.07.2026 #137\nzemě|měna|množství|kód|kurz\nUSA|dolar|1|USD|23,100\n";

    private function fakeHttp(): void
    {
        Http::fake([
            '*cnb.cz*' => Http::response(self::CNB_BODY, 200),
            '*frankfurter.app*' => Http::response([
                'amount' => 1.0, 'base' => 'USD', 'date' => '2026-07-17', 'rates' => ['EUR' => 0.92],
            ], 200),
        ]);
    }

    private function seedPairs(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => true,
        ]);
        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $eur->id,
            'source' => FxSource::FRANKFURTER, 'is_active' => true,
        ]);
        CurrencyPair::factory()->create([
            'base_currency_id' => $eur->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => false,
        ]);
    }

    public function test_sync_upserts_active_pairs_from_both_sources(): void
    {
        $this->fakeHttp();
        $this->seedPairs();

        $result = $this->app->make(FxSyncService::class)->sync();

        $this->assertSame(2, $result->synced);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(2, FxRate::query()->count());
        $this->assertDatabaseHas('fx_rates', ['rate' => '23.1000000000', 'source' => 'cnb']);
        $this->assertDatabaseHas('fx_rates', ['rate' => '0.9200000000', 'source' => 'frankfurter']);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->fakeHttp();
        $this->seedPairs();

        $service = $this->app->make(FxSyncService::class);
        $service->sync();
        $service->sync();

        $this->assertSame(2, FxRate::query()->count());
    }

    public function test_sync_counts_skipped_when_rate_unavailable(): void
    {
        Http::fake([
            '*cnb.cz*' => Http::response("18.07.2026 #137\nzemě|měna|množství|kód|kurz\n", 200),
        ]);
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => true,
        ]);

        $result = $this->app->make(FxSyncService::class)->sync();

        $this->assertSame(0, $result->synced);
        $this->assertSame(1, $result->skipped);
    }
}
