<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use App\Repositories\CurrencyRepository;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyRepository::class)]
class CurrencyRepositoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyRepositoryInterface
    {
        return $this->app->make(CurrencyRepositoryInterface::class);
    }

    public function test_paginate_find_create_update_delete(): void
    {
        Currency::factory()->create(['code' => 'AAA']);
        $page = $this->repository()->paginate('code', 'asc', 15);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertContainsOnlyInstancesOf(CurrencyData::class, $page->items());

        $created = $this->repository()->create(['code' => 'CZK', 'name' => 'Czech koruna']);
        $this->assertSame('CZK', $created->code);
        $this->assertDatabaseHas('currencies', ['code' => 'CZK']);

        $found = $this->repository()->find($created->id);
        $this->assertInstanceOf(CurrencyData::class, $found);

        $updated = $this->repository()->update($created->id, ['code' => 'CZK', 'name' => 'Koruna']);
        $this->assertSame('Koruna', $updated->name);

        $this->repository()->delete($created->id);
        $this->assertNull($this->repository()->find($created->id));
    }
}
