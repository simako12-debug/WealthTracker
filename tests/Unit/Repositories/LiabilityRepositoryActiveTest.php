<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityData;
use App\Models\Liability;
use App\Repositories\LiabilityRepository;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiabilityRepository::class)]
class LiabilityRepositoryActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_returns_only_active_liabilities_ordered_by_name(): void
    {
        Liability::factory()->create(['name' => 'Zeta loan', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Alpha loan', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Closed loan', 'is_active' => false]);

        $result = $this->app->make(LiabilityRepositoryInterface::class)->active();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(LiabilityData::class, $result);
        $this->assertSame('Alpha loan', $result->first()->name);
    }
}
