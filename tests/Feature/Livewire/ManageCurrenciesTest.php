<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageCurrencies;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageCurrencies::class)]
class ManageCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotAccessRoute(): void
    {
        $this->get('/currencies')->assertRedirect('/login');
    }

    public function testCreateCurrencyUppercasesCode(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('create')
            ->set('form.code', 'usd')
            ->set('form.name', 'US dollar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'name' => 'US dollar']);
    }

    public function testCodeMustBeUnique(): void
    {
        Currency::factory()->create(['code' => 'EUR']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('create')
            ->set('form.code', 'EUR')
            ->set('form.name', 'Euro')
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function testDeleteCurrency(): void
    {
        $currency = Currency::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('delete', $currency->id);

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }
}
