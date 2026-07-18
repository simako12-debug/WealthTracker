<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountBalanceSnapshot::class)]
class AccountBalanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_belongs_to_account_with_date_cast(): void
    {
        $account = Account::factory()->create();

        $snapshot = AccountBalanceSnapshot::factory()->create([
            'account_id' => $account->id,
            'snapshot_date' => '2026-06-30',
            'balance' => '125000.0000000000',
        ]);

        $this->assertSame($account->id, $snapshot->account->id);
        $this->assertSame('2026-06-30', $snapshot->snapshot_date->toDateString());
        $this->assertSame('125000.0000000000', $snapshot->balance);
    }
}
