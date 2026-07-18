<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\InstitutionType;
use App\Livewire\ManageInstitutions;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageInstitutions::class)]
class ManageInstitutionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/institutions')->assertRedirect('/login');
    }

    public function test_lists_institutions(): void
    {
        Institution::factory()->create(['name' => 'Fio banka']);

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->assertOk()
            ->assertSee('Fio banka');
    }

    public function test_create_institution(): void
    {
        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('create')
            ->set('form.name', 'eToro')
            ->set('form.type', InstitutionType::BROKER->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('institutions', ['name' => 'eToro', 'type' => 'broker']);
    }

    public function test_validation_fails_without_name(): void
    {
        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('create')
            ->set('form.name', '')
            ->set('form.type', InstitutionType::BANK->value)
            ->call('save')
            ->assertHasErrors(['form.name']);
    }

    public function test_edit_institution(): void
    {
        $institution = Institution::factory()->create(['name' => 'Old', 'type' => InstitutionType::BANK]);

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('edit', $institution->id)
            ->assertSet('form.name', 'Old')
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'name' => 'Renamed']);
    }

    public function test_delete_institution(): void
    {
        $institution = Institution::factory()->create();

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('delete', $institution->id);

        $this->assertDatabaseMissing('institutions', ['id' => $institution->id]);
    }
}
