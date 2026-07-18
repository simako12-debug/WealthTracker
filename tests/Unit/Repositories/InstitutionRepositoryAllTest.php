<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\InstitutionData;
use App\Models\Institution;
use App\Repositories\InstitutionRepository;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InstitutionRepository::class)]
class InstitutionRepositoryAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_returns_collection_of_data_ordered_by_name(): void
    {
        Institution::factory()->create(['name' => 'Zeta']);
        Institution::factory()->create(['name' => 'Alpha']);

        $all = $this->app->make(InstitutionRepositoryInterface::class)->all();

        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(InstitutionData::class, $all);
        $this->assertSame('Alpha', $all->first()->name);
    }
}
