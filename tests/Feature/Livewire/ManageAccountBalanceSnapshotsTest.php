<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageAccountBalanceSnapshots;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageAccountBalanceSnapshots::class)]
class ManageAccountBalanceSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/account-snapshots')->assertRedirect('/login');
    }

    public function test_only_active_accounts_are_selectable(): void
    {
        Account::factory()->create(['name' => 'Active Broker', 'is_active' => true]);
        Account::factory()->create(['name' => 'Closed Account', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->assertSee('Active Broker')
            ->assertDontSee('Closed Account');
    }

    public function test_selecting_account_shows_currency_badge(): void
    {
        $currency = Currency::factory()->create(['code' => 'GBP']);
        $account = Account::factory()->create(['currency_id' => $currency->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)
            ->assertSee('GBP');
    }

    public function test_create_snapshot_and_keep_account_selected(): void
    {
        $account = Account::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)
            ->set('form.balance', '15000')
            ->set('form.snapshotDate', '2026-03-31')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('account_balance_snapshots', ['account_id' => $account->id, 'balance' => '15000.0000000000']);
        $component->assertSet('form.accountId', $account->id)
            ->assertSet('form.balance', null);
    }

    public function test_saving_same_account_and_date_is_idempotent(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)->set('form.balance', '100')->set('form.snapshotDate', '2026-03-31')
            ->call('save')->assertHasNoErrors();
        Livewire::actingAs($user)->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)->set('form.balance', '250')->set('form.snapshotDate', '2026-03-31')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, AccountBalanceSnapshot::query()->count());
        $this->assertDatabaseHas('account_balance_snapshots', [
            'account_id' => $account->id, 'snapshot_date' => '2026-03-31', 'balance' => '250.0000000000',
        ]);
    }

    public function test_validation_requires_account_balance_date(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.snapshotDate', null)
            ->call('save')
            ->assertHasErrors(['form.accountId', 'form.balance', 'form.snapshotDate']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $snapshot = AccountBalanceSnapshot::factory()->create(['balance' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->call('edit', $snapshot->id)
            ->assertSet('form.accountId', $snapshot->account_id)
            ->set('form.balance', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('account_balance_snapshots', ['id' => $snapshot->id, 'balance' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->call('delete', $snapshot->id);
        $this->assertDatabaseMissing('account_balance_snapshots', ['id' => $snapshot->id]);
    }
}
