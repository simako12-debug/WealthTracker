<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FxSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FxSource::class)]
class FxSourceTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('cnb', FxSource::CNB->value);
        $this->assertSame('frankfurter', FxSource::FRANKFURTER->value);
        $this->assertCount(2, FxSource::cases());
    }
}
