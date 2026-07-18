<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Livewire\FxSyncButton;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\FxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxSyncButton::class)]
class FxSyncButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_fetches_and_reports_summary(): void
    {
        Http::fake(['*cnb.cz*' => Http::response("18.07.2026 #137\nzemě|měna|množství|kód|kurz\nUSA|dolar|1|USD|23,100\n", 200)]);

        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => true,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(FxSyncButton::class)
            ->call('sync')
            ->assertSee('1 synced');

        $this->assertSame(1, FxRate::query()->count());
    }
}
