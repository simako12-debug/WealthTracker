<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Institution::class)]
class InstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_institution_with_enum_cast(): void
    {
        $institution = Institution::factory()->create(['type' => InstitutionType::BROKER]);

        $this->assertInstanceOf(InstitutionType::class, $institution->type);
        $this->assertSame(InstitutionType::BROKER, $institution->type);
    }
}
