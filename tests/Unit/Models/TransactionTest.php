<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_belongs_to_account_with_enum_and_date_casts(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'type' => TransactionType::DIVIDEND,
            'transaction_date' => '2026-02-01',
        ]);

        $this->assertSame($account->id, $transaction->account->id);
        $this->assertSame(TransactionType::DIVIDEND, $transaction->type);
        $this->assertSame('2026-02-01', $transaction->transaction_date->toDateString());
    }
}
