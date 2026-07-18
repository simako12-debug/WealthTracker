<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\LiabilityPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiabilityPayment::class)]
class LiabilityPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function testPaymentBelongsToLiabilityWithDateCast(): void
    {
        $payment = LiabilityPayment::factory()->create([
            'payment_date' => '2026-05-01',
            'total_amount' => '18000.0000000000',
        ]);

        $this->assertNotNull($payment->liability);
        $this->assertSame('2026-05-01', $payment->payment_date->toDateString());
        $this->assertSame('18000.0000000000', $payment->total_amount);
    }
}
