<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\InstitutionData;
use App\Enums\InstitutionType;
use App\Models\Institution;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\InstitutionRepository::class)]
class InstitutionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): InstitutionRepositoryInterface
    {
        return $this->app->make(InstitutionRepositoryInterface::class);
    }

    public function testPaginateReturnsDataObjects(): void
    {
        Institution::factory()->create(['name' => 'Alpha']);
        Institution::factory()->create(['name' => 'Beta']);

        $page = $this->repository()->paginate('name', 'asc', 15);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertCount(2, $page->items());
        $this->assertContainsOnlyInstancesOf(InstitutionData::class, $page->items());
        $this->assertSame('Alpha', $page->items()[0]->name);
    }

    public function testCreatePersistsAndReturnsData(): void
    {
        $data = $this->repository()->create([
            'name' => 'Fio banka',
            'type' => InstitutionType::BANK->value,
            'note' => null,
        ]);

        $this->assertInstanceOf(InstitutionData::class, $data);
        $this->assertSame('Fio banka', $data->name);
        $this->assertSame(InstitutionType::BANK, $data->type);
        $this->assertDatabaseHas('institutions', ['name' => 'Fio banka', 'type' => 'bank']);
    }

    public function testUpdateChangesRow(): void
    {
        $institution = Institution::factory()->create(['name' => 'Old', 'type' => InstitutionType::BANK]);

        $data = $this->repository()->update($institution->id, [
            'name' => 'New',
            'type' => InstitutionType::BROKER->value,
            'note' => 'moved',
        ]);

        $this->assertSame('New', $data->name);
        $this->assertSame(InstitutionType::BROKER, $data->type);
        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'name' => 'New', 'type' => 'broker']);
    }

    public function testFindAndDelete(): void
    {
        $institution = Institution::factory()->create();

        $this->assertInstanceOf(InstitutionData::class, $this->repository()->find($institution->id));

        $this->repository()->delete($institution->id);

        $this->assertNull($this->repository()->find($institution->id));
        $this->assertDatabaseMissing('institutions', ['id' => $institution->id]);
    }
}
