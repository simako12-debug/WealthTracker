<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TransactionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionType::class)]
class TransactionTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('capital_gain', TransactionType::CAPITAL_GAIN->value);
        $this->assertSame('bond_income', TransactionType::BOND_INCOME->value);
        $this->assertCount(9, TransactionType::cases());
    }
}
