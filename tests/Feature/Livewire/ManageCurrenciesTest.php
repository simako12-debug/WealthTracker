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

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/currencies')->assertRedirect('/login');
    }

    public function test_create_currency_uppercases_code(): void
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

    public function test_code_must_be_unique(): void
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

    public function test_delete_currency(): void
    {
        $currency = Currency::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('delete', $currency->id);

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_lowercase_code_is_rejected_when_uppercase_exists(): void
    {
        Currency::factory()->create(['code' => 'USD']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('create')
            ->set('form.code', 'usd')
            ->set('form.name', 'US dollar')
            ->call('save')
            ->assertHasErrors(['form.code']);

        $this->assertSame(1, Currency::query()->where('code', 'USD')->count());
        $this->assertDatabaseMissing('currencies', ['code' => 'usd']);
    }
}
