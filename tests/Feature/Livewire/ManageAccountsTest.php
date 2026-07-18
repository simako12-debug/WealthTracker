<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\AccountType;
use App\Livewire\ManageAccounts;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageAccounts::class)]
class ManageAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/accounts')->assertRedirect('/login');
    }

    public function test_create_account(): void
    {
        $institution = Institution::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('create')
            ->set('form.institutionId', $institution->id)
            ->set('form.currencyId', $currency->id)
            ->set('form.name', 'eToro USD')
            ->set('form.type', AccountType::INVESTMENT->value)
            ->set('form.isActive', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('accounts', ['name' => 'eToro USD', 'type' => 'investment']);
    }

    public function test_validation_requires_institution_and_currency(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('create')
            ->set('form.name', 'x')
            ->set('form.type', AccountType::BANK->value)
            ->call('save')
            ->assertHasErrors(['form.institutionId', 'form.currencyId']);
    }

    public function test_edit_and_delete(): void
    {
        $account = Account::factory()->create(['name' => 'Old']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('edit', $account->id)
            ->assertSet('form.name', 'Old')
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Renamed']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('delete', $account->id);
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }
}
