<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageLiabilityPayments;
use App\Models\Currency;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageLiabilityPayments::class)]
class ManageLiabilityPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/liability-payments')->assertRedirect('/login');
    }

    public function test_only_active_liabilities_are_selectable(): void
    {
        Liability::factory()->create(['name' => 'Active Mortgage', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Closed Loan', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->assertSee('Active Mortgage')
            ->assertDontSee('Closed Loan');
    }

    public function test_selecting_liability_shows_context(): void
    {
        $currency = Currency::factory()->create(['code' => 'CZK']);
        $liability = Liability::factory()->create([
            'name' => 'Flat Mortgage', 'currency_id' => $currency->id,
        ]);
        LiabilityPayment::factory()->create([
            'liability_id' => $liability->id, 'payment_date' => '2026-02-01', 'total_amount' => '9000.0000000000',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.liabilityId', $liability->id)
            ->assertSee('2026-02-01')   // last payment date in context
            ->assertSee('Payments recorded');
    }

    public function test_create_payment_and_keep_liability_selected(): void
    {
        $liability = Liability::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.liabilityId', $liability->id)
            ->set('form.totalAmount', '5000')
            ->set('form.paymentDate', '2026-03-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('liability_payments', ['liability_id' => $liability->id, 'total_amount' => '5000.0000000000']);
        $component->assertSet('form.liabilityId', $liability->id)
            ->assertSet('form.totalAmount', null);
    }

    public function test_validation_requires_liability_amount_date(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.paymentDate', null)
            ->call('save')
            ->assertHasErrors(['form.liabilityId', 'form.totalAmount', 'form.paymentDate']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $payment = LiabilityPayment::factory()->create(['total_amount' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->call('edit', $payment->id)
            ->assertSet('form.liabilityId', $payment->liability_id)
            ->set('form.totalAmount', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('liability_payments', ['id' => $payment->id, 'total_amount' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->call('delete', $payment->id);
        $this->assertDatabaseMissing('liability_payments', ['id' => $payment->id]);
    }
}
