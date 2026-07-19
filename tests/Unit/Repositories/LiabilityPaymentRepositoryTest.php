<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityPaymentData;
use App\Models\Currency;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Repositories\LiabilityPaymentRepository;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiabilityPaymentRepository::class)]
class LiabilityPaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): LiabilityPaymentRepositoryInterface
    {
        return $this->app->make(LiabilityPaymentRepositoryInterface::class);
    }

    public function test_create_returns_denormalized_data(): void
    {
        $currency = Currency::factory()->create(['code' => 'EUR']);
        $liability = Liability::factory()->create(['name' => 'Mortgage', 'currency_id' => $currency->id]);

        $data = $this->repository()->create([
            'liability_id' => $liability->id,
            'payment_date' => '2026-03-15',
            'total_amount' => '12500.0000000000',
            'principal_portion' => '10000.0000000000',
            'interest_portion' => '2500.0000000000',
            'note' => 'March',
        ]);

        $this->assertInstanceOf(LiabilityPaymentData::class, $data);
        $this->assertSame('Mortgage', $data->liabilityName);
        $this->assertSame('EUR', $data->currencyCode);
        $this->assertSame('12500.0000000000', $data->totalAmount);
        $this->assertDatabaseHas('liability_payments', ['liability_id' => $liability->id, 'note' => 'March']);
    }

    public function test_recent_for_liability_newest_first_limited(): void
    {
        $liability = Liability::factory()->create();
        $other = Liability::factory()->create();
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-01-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-03-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-02-01']);
        LiabilityPayment::factory()->create(['liability_id' => $other->id, 'payment_date' => '2026-04-01']);

        $recent = $this->repository()->recentForLiability($liability->id, 2);

        $this->assertCount(2, $recent);
        $this->assertContainsOnlyInstancesOf(LiabilityPaymentData::class, $recent);
        $this->assertSame('2026-03-01', $recent->first()->paymentDate->toDateString());
    }

    public function test_count_for_liability(): void
    {
        $liability = Liability::factory()->create();
        LiabilityPayment::factory()->count(3)->create(['liability_id' => $liability->id]);
        LiabilityPayment::factory()->create(); // different liability

        $this->assertSame(3, $this->repository()->countForLiability($liability->id));
    }

    public function test_latest_date_by_liability(): void
    {
        $liability = Liability::factory()->create();
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-01-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-05-01']);

        $map = $this->repository()->latestDateByLiability();

        $this->assertSame('2026-05-01', $map->get($liability->id));
    }

    public function test_update_and_delete(): void
    {
        $payment = LiabilityPayment::factory()->create(['total_amount' => '100.0000000000']);

        $updated = $this->repository()->update($payment->id, [
            'liability_id' => $payment->liability_id,
            'payment_date' => $payment->payment_date->toDateString(),
            'total_amount' => '200.0000000000',
            'principal_portion' => null,
            'interest_portion' => null,
            'note' => 'edited',
        ]);
        $this->assertSame('200.0000000000', $updated->totalAmount);

        $this->repository()->delete($payment->id);
        $this->assertNull($this->repository()->find($payment->id));
    }
}
