<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\InstitutionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstitutionType::class)]
class InstitutionTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('bank', InstitutionType::BANK->value);
        $this->assertSame('lender', InstitutionType::LENDER->value);
        $this->assertCount(5, InstitutionType::cases());
    }
}
