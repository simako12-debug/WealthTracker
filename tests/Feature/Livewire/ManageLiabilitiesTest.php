<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageLiabilities;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageLiabilities::class)]
class ManageLiabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/liabilities')->assertRedirect('/login');
    }

    public function test_create_liability(): void
    {
        $institution = Institution::factory()->create();
        $currency = Currency::factory()->create(['code' => 'CZK']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilities::class)
            ->call('create')
            ->set('form.institutionId', $institution->id)
            ->set('form.currencyId', $currency->id)
            ->set('form.name', 'Hypotéka byt Praha')
            ->set('form.principalAmount', '3500000')
            ->set('form.interestRate', '4.9')
            ->set('form.startDate', '2024-01-01')
            ->set('form.isActive', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('liabilities', ['name' => 'Hypotéka byt Praha']);
    }

    public function test_validation_requires_institution_currency_name_principal_rate_start_date(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilities::class)
            ->call('create')
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors([
                'form.institutionId',
                'form.currencyId',
                'form.name',
                'form.principalAmount',
                'form.interestRate',
                'form.startDate',
            ]);
    }

    public function test_edit_and_delete(): void
    {
        $liability = Liability::factory()->create(['name' => 'Old']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilities::class)
            ->call('edit', $liability->id)
            ->assertSet('form.name', 'Old')
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('liabilities', ['id' => $liability->id, 'name' => 'Renamed']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilities::class)
            ->call('delete', $liability->id);
        $this->assertDatabaseMissing('liabilities', ['id' => $liability->id]);
    }
}
