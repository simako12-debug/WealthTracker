<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountBalanceSnapshotData;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Currency;
use App\Models\Institution;
use App\Repositories\AccountBalanceSnapshotRepository;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountBalanceSnapshotRepository::class)]
class AccountBalanceSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): AccountBalanceSnapshotRepositoryInterface
    {
        return $this->app->make(AccountBalanceSnapshotRepositoryInterface::class);
    }

    public function test_upsert_returns_denormalized_data(): void
    {
        $institution = Institution::factory()->create(['name' => 'Degiro']);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $account = Account::factory()->create([
            'institution_id' => $institution->id, 'currency_id' => $currency->id, 'name' => 'Broker USD',
        ]);

        $data = $this->repository()->upsert([
            'account_id' => $account->id,
            'balance' => '15000.0000000000',
            'snapshot_date' => '2026-03-31',
            'note' => 'Q1',
        ]);

        $this->assertInstanceOf(AccountBalanceSnapshotData::class, $data);
        $this->assertSame('Broker USD', $data->accountName);
        $this->assertSame('Degiro', $data->institutionName);
        $this->assertSame('USD', $data->currencyCode);
        $this->assertSame('15000.0000000000', $data->balance);
    }

    public function test_upsert_is_idempotent_on_account_and_date(): void
    {
        $account = Account::factory()->create();

        $this->repository()->upsert([
            'account_id' => $account->id, 'balance' => '100.0000000000', 'snapshot_date' => '2026-03-31', 'note' => null,
        ]);
        $this->repository()->upsert([
            'account_id' => $account->id, 'balance' => '250.0000000000', 'snapshot_date' => '2026-03-31', 'note' => null,
        ]);

        $this->assertSame(1, AccountBalanceSnapshot::query()->count());
        $this->assertDatabaseHas('account_balance_snapshots', [
            'account_id' => $account->id, 'snapshot_date' => '2026-03-31', 'balance' => '250.0000000000',
        ]);
    }

    public function test_recent_returns_newest_first_limited(): void
    {
        $account = Account::factory()->create();
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-01-31']);
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-03-31']);
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-02-28']);

        $recent = $this->repository()->recent(2);

        $this->assertCount(2, $recent);
        $this->assertContainsOnlyInstancesOf(AccountBalanceSnapshotData::class, $recent);
        $this->assertSame('2026-03-31', $recent->first()->snapshotDate->toDateString());
    }

    public function test_update_and_delete(): void
    {
        $snapshot = AccountBalanceSnapshot::factory()->create(['balance' => '10.0000000000']);

        $updated = $this->repository()->update($snapshot->id, [
            'account_id' => $snapshot->account_id,
            'balance' => '20.0000000000',
            'snapshot_date' => $snapshot->snapshot_date->toDateString(),
            'note' => 'edited',
        ]);
        $this->assertSame('20.0000000000', $updated->balance);

        $this->repository()->delete($snapshot->id);
        $this->assertNull($this->repository()->find($snapshot->id));
    }
}
