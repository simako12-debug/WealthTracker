<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Liability::class)]
class LiabilityTest extends TestCase
{
    use RefreshDatabase;

    public function testLiabilityRelationsAndCasts(): void
    {
        $institution = Institution::factory()->create();
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $liability = Liability::factory()->create([
            'institution_id' => $institution->id,
            'currency_id' => $czk->id,
            'start_date' => '2020-01-01',
        ]);

        $this->assertSame($institution->id, $liability->institution->id);
        $this->assertSame('CZK', $liability->currency->code);
        $this->assertSame('2020-01-01', $liability->start_date->toDateString());
        $this->assertTrue($liability->is_active);
    }
}
