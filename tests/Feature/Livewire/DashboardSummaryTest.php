<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\TransactionType;
use App\Livewire\DashboardSummary;
use App\Models\Account;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DashboardSummary::class)]
class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_account_count(): void
    {
        Account::factory()->count(2)->create();

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertViewHas('accountCount', 2);
    }

    public function test_shows_recent_transactions(): void
    {
        $account = Account::factory()->create(['name' => 'Fio CZK']);
        Transaction::factory()->create([
            'account_id' => $account->id, 'type' => TransactionType::DEPOSIT, 'transaction_date' => '2026-03-15',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('Fio CZK')
            ->assertSee('2026-03-15');
    }

    public function test_shows_active_liabilities_with_last_payment_date(): void
    {
        $liability = Liability::factory()->create(['name' => 'Mortgage', 'is_active' => true]);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-05-01']);
        Liability::factory()->create(['name' => 'Closed', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('Mortgage')
            ->assertSee('2026-05-01')
            ->assertDontSee('Closed');
    }

    public function test_active_liability_without_payments_shows_dash(): void
    {
        Liability::factory()->create(['name' => 'Fresh Loan', 'is_active' => true]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('Fresh Loan')
            ->assertSee('—');
    }
}
