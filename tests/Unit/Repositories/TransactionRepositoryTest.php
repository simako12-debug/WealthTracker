<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\TransactionData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TransactionRepository::class)]
class TransactionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): TransactionRepositoryInterface
    {
        return $this->app->make(TransactionRepositoryInterface::class);
    }

    public function test_create_returns_denormalized_data(): void
    {
        $institution = Institution::factory()->create(['name' => 'Fio banka']);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $account = Account::factory()->create([
            'institution_id' => $institution->id,
            'currency_id' => $currency->id,
            'name' => 'eToro USD',
        ]);

        $data = $this->repository()->create([
            'account_id' => $account->id,
            'type' => TransactionType::DIVIDEND->value,
            'amount' => '12.5000000000',
            'transaction_date' => '2026-03-15',
            'note' => null,
            'counterparty' => 'AAPL',
        ]);

        $this->assertInstanceOf(TransactionData::class, $data);
        $this->assertSame('eToro USD', $data->accountName);
        $this->assertSame('Fio banka', $data->institutionName);
        $this->assertSame('USD', $data->accountCurrencyCode);
        $this->assertSame(TransactionType::DIVIDEND, $data->type);
        $this->assertSame('AAPL', $data->counterparty);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'type' => 'dividend']);
    }

    public function test_recent_returns_newest_first_limited(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->create(['account_id' => $account->id, 'transaction_date' => '2026-01-01']);
        Transaction::factory()->create(['account_id' => $account->id, 'transaction_date' => '2026-03-01']);
        Transaction::factory()->create(['account_id' => $account->id, 'transaction_date' => '2026-02-01']);

        $recent = $this->repository()->recent(2);

        $this->assertCount(2, $recent);
        $this->assertContainsOnlyInstancesOf(TransactionData::class, $recent);
        $this->assertSame('2026-03-01', $recent->first()->transactionDate->toDateString());
    }

    public function test_update_and_delete(): void
    {
        $transaction = Transaction::factory()->create(['amount' => '10.0000000000']);

        $updated = $this->repository()->update($transaction->id, [
            'account_id' => $transaction->account_id,
            'type' => $transaction->type->value,
            'amount' => '20.0000000000',
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'note' => 'edited',
            'counterparty' => null,
        ]);
        $this->assertSame('20.0000000000', $updated->amount);

        $this->repository()->delete($transaction->id);
        $this->assertNull($this->repository()->find($transaction->id));
    }
}
