<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Livewire\ManageCurrencyPairs;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageCurrencyPairs::class)]
class ManageCurrencyPairsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/currency-pairs')->assertRedirect('/login');
    }

    public function test_source_prefills_cnb_when_czk_involved(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->assertSet('form.source', FxSource::CNB->value);
    }

    public function test_source_prefills_frankfurter_when_no_czk(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $eur->id)
            ->assertSet('form.source', FxSource::FRANKFURTER->value);
    }

    public function test_create_pair(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->set('form.source', FxSource::CNB->value)
            ->set('form.isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currency_pairs', [
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id, 'source' => 'cnb',
        ]);
    }

    public function test_duplicate_pair_is_rejected(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create(['base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->set('form.source', FxSource::CNB->value)
            ->call('save')
            ->assertHasErrors(['form.baseCurrencyId']);
    }
}
