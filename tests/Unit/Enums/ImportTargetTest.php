<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ImportTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImportTarget::class)]
class ImportTargetTest extends TestCase
{
    public function test_headers_and_sample_row_align_for_every_target(): void
    {
        foreach (ImportTarget::cases() as $target) {
            $this->assertSame(count($target->headers()), count($target->sampleRow()), $target->value);
            $this->assertNotSame([], $target->headers());
        }
    }

    public function test_transactions_headers_exclude_note(): void
    {
        $headers = ImportTarget::TRANSACTIONS->headers();

        $this->assertSame(['institution', 'account', 'type', 'amount', 'transaction_date', 'counterparty'], $headers);
        $this->assertNotContains('note', $headers);
    }

    public function test_label_is_human_readable(): void
    {
        $this->assertSame('Account snapshots', ImportTarget::ACCOUNT_SNAPSHOTS->label());
    }
}
