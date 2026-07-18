<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\CurrencyRepository::class)]
class CurrencyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyRepositoryInterface
    {
        return $this->app->make(CurrencyRepositoryInterface::class);
    }

    public function testFindByCodeReturnsDataObject(): void
    {
        Currency::factory()->create(['code' => 'USD', 'name' => 'US dollar']);

        $data = $this->repository()->findByCode('USD');

        $this->assertInstanceOf(CurrencyData::class, $data);
        $this->assertSame('USD', $data->code);
        $this->assertSame('US dollar', $data->name);
    }

    public function testFindByCodeReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository()->findByCode('ZZZ'));
    }

    public function testAllReturnsCollectionOfDataObjects(): void
    {
        Currency::factory()->create(['code' => 'CZK']);
        Currency::factory()->create(['code' => 'EUR']);

        $all = $this->repository()->all();

        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(CurrencyData::class, $all);
    }
}
