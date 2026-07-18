<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Enums\TransactionType;
use App\Livewire\ManageTransactions;
use App\Models\Account;
use App\Models\Currency;
use App\Models\FxRate;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageTransactions::class)]
class ManageTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/transactions')->assertRedirect('/login');
    }

    public function test_selecting_institution_filters_accounts(): void
    {
        $institution = Institution::factory()->create();
        $account = Account::factory()->create(['institution_id' => $institution->id, 'name' => 'Fio CZK']);
        Account::factory()->create(['name' => 'Other']); // different institution

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->assertSee('Fio CZK')
            ->assertDontSee('Other');
    }

    public function test_create_transaction_and_keep_account_selected(): void
    {
        $institution = Institution::factory()->create();
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $account = Account::factory()->create(['institution_id' => $institution->id, 'currency_id' => $czk->id]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->set('form.accountId', $account->id)
            ->set('form.type', TransactionType::DEPOSIT->value)
            ->set('form.amount', '1500')
            ->set('form.transactionDate', '2026-03-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'type' => 'deposit']);
        // account stays selected for rapid entry; amount cleared
        $component->assertSet('form.accountId', $account->id)
            ->assertSet('form.amount', null);
    }

    public function test_live_czk_preview_uses_converter(): void
    {
        $institution = Institution::factory()->create();
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $account = Account::factory()->create(['institution_id' => $institution->id, 'currency_id' => $usd->id]);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id, 'currency_to_id' => $czk->id,
            'rate' => '23.0000000000', 'rate_date' => '2026-03-10', 'source' => FxSource::CNB,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->set('form.accountId', $account->id)
            ->set('form.amount', '10')
            ->set('form.transactionDate', '2026-03-15')
            ->assertSee('230');
    }

    public function test_validation_requires_account_type_amount(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('save')
            ->assertHasErrors(['form.accountId', 'form.type', 'form.amount']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $transaction = Transaction::factory()->create(['amount' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('edit', $transaction->id)
            ->assertSet('form.accountId', $transaction->account_id)
            ->set('form.amount', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('delete', $transaction->id);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}
