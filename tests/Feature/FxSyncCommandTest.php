<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FxSyncCommand;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\FxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxSyncCommand::class)]
class FxSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandSyncsRatesAndReportsSummary(): void
    {
        Http::fake(['*cnb.cz*' => Http::response("18.07.2026 #137\nzemě|měna|množství|kód|kurz\nUSA|dolar|1|USD|23,100\n", 200)]);

        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => true,
        ]);

        $this->artisan('fx:sync')
            ->assertExitCode(0);

        $this->assertSame(1, FxRate::query()->count());
    }
}
