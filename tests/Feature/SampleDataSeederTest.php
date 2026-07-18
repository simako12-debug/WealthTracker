<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\Institution;
use App\Models\Transaction;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SampleDataSeeder::class)]
class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function testSeederCreatesUsableSampleData(): void
    {
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(4, Currency::query()->count());
        $this->assertNotNull(Currency::query()->where('code', 'CZK')->first());
        $this->assertGreaterThanOrEqual(2, Institution::query()->count());
        $this->assertGreaterThanOrEqual(3, Account::query()->count());
        $this->assertSame(3, CurrencyPair::query()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::query()->count());
    }

    public function testSeederIsIdempotentForCurrencies(): void
    {
        $this->seed(SampleDataSeeder::class);
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(4, Currency::query()->count());
    }
}
