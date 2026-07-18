<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AccountType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountType::class)]
class AccountTypeTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('investment', AccountType::INVESTMENT->value);
        $this->assertCount(4, AccountType::cases());
    }
}
