<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use App\Models\Institution;
use App\Repositories\AccountRepository;
use App\Repositories\AccountRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountRepository::class)]
class AccountRepositoryForInstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_institution_returns_only_that_institutions_active_accounts(): void
    {
        $a = Institution::factory()->create();
        $b = Institution::factory()->create();
        Account::factory()->create(['institution_id' => $a->id, 'name' => 'A1', 'is_active' => true]);
        Account::factory()->create(['institution_id' => $a->id, 'name' => 'A2', 'is_active' => false]);
        Account::factory()->create(['institution_id' => $b->id, 'name' => 'B1', 'is_active' => true]);

        $result = $this->app->make(AccountRepositoryInterface::class)->forInstitution($a->id);

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(AccountData::class, $result);
        $this->assertSame('A1', $result->first()->name);
    }
}
