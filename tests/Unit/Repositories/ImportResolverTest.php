<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Institution;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\Transaction;
use App\Repositories\AccountRepository;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepository;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepository;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepository;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountRepository::class)]
#[CoversClass(LiabilityRepository::class)]
#[CoversClass(TransactionRepository::class)]
#[CoversClass(LiabilityPaymentRepository::class)]
class ImportResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_account_by_institution_and_name(): void
    {
        $fio = Institution::factory()->create(['name' => 'Fio banka']);
        $other = Institution::factory()->create(['name' => 'Degiro']);
        Account::factory()->create(['institution_id' => $fio->id, 'name' => 'Běžný účet']);
        Account::factory()->create(['institution_id' => $other->id, 'name' => 'Běžný účet']);

        $repo = $this->app->make(AccountRepositoryInterface::class);

        $found = $repo->findByInstitutionAndName('Fio banka', 'Běžný účet');
        $this->assertNotNull($found);
        $this->assertSame('Fio banka', $found->institutionName);
        $this->assertNull($repo->findByInstitutionAndName('Fio banka', 'Nope'));
        $this->assertNull($repo->findByInstitutionAndName('Nope', 'Běžný účet'));
    }

    public function test_find_liability_by_name(): void
    {
        Liability::factory()->create(['name' => 'Hypotéka byt Praha']);
        $repo = $this->app->make(LiabilityRepositoryInterface::class);

        $this->assertNotNull($repo->findByName('Hypotéka byt Praha'));
        $this->assertNull($repo->findByName('Neexistuje'));
    }

    public function test_transaction_exists_matching_is_null_safe_on_counterparty(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->create([
            'account_id' => $account->id, 'type' => TransactionType::DEPOSIT,
            'amount' => '1500.0000000000', 'transaction_date' => '2026-01-15', 'counterparty' => null,
        ]);
        $repo = $this->app->make(TransactionRepositoryInterface::class);

        $this->assertTrue($repo->existsMatching([
            'account_id' => $account->id, 'transaction_date' => '2026-01-15',
            'type' => 'deposit', 'amount' => '1500.00', 'counterparty' => null,
        ]));
        $this->assertFalse($repo->existsMatching([
            'account_id' => $account->id, 'transaction_date' => '2026-01-15',
            'type' => 'deposit', 'amount' => '1500.00', 'counterparty' => 'AAPL',
        ]));
    }

    public function test_liability_payment_exists_matching(): void
    {
        $liability = Liability::factory()->create();
        LiabilityPayment::factory()->create([
            'liability_id' => $liability->id, 'payment_date' => '2026-01-31', 'total_amount' => '12500.0000000000',
        ]);
        $repo = $this->app->make(LiabilityPaymentRepositoryInterface::class);

        $this->assertTrue($repo->existsMatching([
            'liability_id' => $liability->id, 'payment_date' => '2026-01-31', 'total_amount' => '12500.00',
        ]));
        $this->assertFalse($repo->existsMatching([
            'liability_id' => $liability->id, 'payment_date' => '2026-02-28', 'total_amount' => '12500.00',
        ]));
    }
}
