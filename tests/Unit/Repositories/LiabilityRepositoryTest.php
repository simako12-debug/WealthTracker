<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityData;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\LiabilityRepository::class)]
class LiabilityRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): LiabilityRepositoryInterface
    {
        return $this->app->make(LiabilityRepositoryInterface::class);
    }

    public function test_create_and_read_with_relations_and_dates(): void
    {
        $institution = Institution::factory()->create(['name' => 'KB']);
        $currency = Currency::factory()->create(['code' => 'CZK']);

        $data = $this->repository()->create([
            'institution_id' => $institution->id,
            'currency_id' => $currency->id,
            'name' => 'Hypotéka byt Praha',
            'principal_amount' => '3500000.0000000000',
            'interest_rate' => '4.9000',
            'monthly_payment' => '18000.0000000000',
            'start_date' => '2024-01-01',
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ]);

        $this->assertInstanceOf(LiabilityData::class, $data);
        $this->assertSame('KB', $data->institutionName);
        $this->assertSame('CZK', $data->currencyCode);
        $this->assertSame('2024-01-01', $data->startDate->toDateString());
        $this->assertNull($data->endDate);
        $this->assertDatabaseHas('liabilities', ['name' => 'Hypotéka byt Praha']);
    }

    public function test_update_and_delete(): void
    {
        $liability = Liability::factory()->create(['name' => 'Old']);

        $updated = $this->repository()->update($liability->id, array_merge($liability->only([
            'institution_id', 'currency_id',
        ]), [
            'name' => 'New',
            'principal_amount' => $liability->principal_amount,
            'interest_rate' => $liability->interest_rate,
            'monthly_payment' => $liability->monthly_payment,
            'start_date' => $liability->start_date->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ]));
        $this->assertSame('New', $updated->name);

        $this->repository()->delete($liability->id);
        $this->assertNull($this->repository()->find($liability->id));
    }
}
