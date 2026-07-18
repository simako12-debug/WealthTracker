<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Currency::class)]
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function testFactoryCreatesCurrencyWithUuid(): void
    {
        $currency = Currency::factory()->create(['code' => 'CZK', 'name' => 'Czech koruna']);

        $this->assertSame('CZK', $currency->code);
        $this->assertIsString($currency->id);
        $this->assertSame(36, strlen($currency->id));
    }
}
