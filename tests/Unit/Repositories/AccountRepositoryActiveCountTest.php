<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use App\Repositories\AccountRepository;
use App\Repositories\AccountRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountRepository::class)]
class AccountRepositoryActiveCountTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): AccountRepositoryInterface
    {
        return $this->app->make(AccountRepositoryInterface::class);
    }

    public function test_active_returns_only_active_accounts_ordered_by_name(): void
    {
        Account::factory()->create(['name' => 'Zeta', 'is_active' => true]);
        Account::factory()->create(['name' => 'Alpha', 'is_active' => true]);
        Account::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $result = $this->repository()->active();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(AccountData::class, $result);
        $this->assertSame('Alpha', $result->first()->name);
    }

    public function test_count_returns_total_accounts(): void
    {
        Account::factory()->count(3)->create();

        $this->assertSame(3, $this->repository()->count());
    }
}
