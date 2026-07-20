<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Import\ImportRowResult;
use App\Enums\ImportTarget;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Institution;
use App\Models\Liability;
use App\Models\Transaction;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CsvImportService::class)]
class CsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CsvImportService
    {
        return $this->app->make(CsvImportService::class);
    }

    private function account(string $institution = 'Fio banka', string $name = 'Běžný účet'): Account
    {
        return Account::factory()->create([
            'institution_id' => Institution::factory()->create(['name' => $institution])->id,
            'name' => $name,
        ]);
    }

    public function test_parse_strips_bom_and_maps_headers_and_blanks(): void
    {
        $csv = "\xEF\xBB\xBFinstitution,account,balance,snapshot_date\nDegiro,Broker USD,15000.00,2026-03-31\n";
        $rows = $this->service()->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('Degiro', $rows[0]['institution']);
        $this->assertSame('2026-03-31', $rows[0]['snapshot_date']);
    }

    public function test_transactions_import_creates_valid_rows_and_reports_errors(): void
    {
        $account = $this->account();
        $csv = implode("\n", [
            'institution,account,type,amount,transaction_date,counterparty',
            'Fio banka,Běžný účet,dividend,120.50,2026-01-15,AAPL',      // valid
            'Fio banka,Neznámý,deposit,10.00,2026-01-16,',               // account not found
            'Fio banka,Běžný účet,deposit,notnum,2026-01-17,',           // non-numeric amount
            'Fio banka,Běžný účet,badtype,10.00,2026-01-18,',            // invalid type
        ]);

        $result = $this->service()->import(ImportTarget::TRANSACTIONS, $csv, true);

        $this->assertSame(1, $result->imported);
        $this->assertSame(3, $result->failed);
        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'type' => 'dividend', 'counterparty' => 'AAPL']);
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_transactions_reimport_skips_duplicates_when_enabled(): void
    {
        $this->account();
        $csv = "institution,account,type,amount,transaction_date,counterparty\nFio banka,Běžný účet,deposit,1500.00,2026-01-15,\n";

        $this->assertSame(1, $this->service()->import(ImportTarget::TRANSACTIONS, $csv, true)->imported);
        $second = $this->service()->import(ImportTarget::TRANSACTIONS, $csv, true);

        $this->assertSame(0, $second->imported);
        $this->assertSame(1, $second->skipped);
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_transactions_reimport_inserts_again_when_skip_disabled(): void
    {
        $this->account();
        $csv = "institution,account,type,amount,transaction_date,counterparty\nFio banka,Běžný účet,deposit,1500.00,2026-01-15,\n";

        $this->service()->import(ImportTarget::TRANSACTIONS, $csv, false);
        $this->service()->import(ImportTarget::TRANSACTIONS, $csv, false);

        $this->assertSame(2, Transaction::query()->count());
    }

    public function test_snapshot_import_is_idempotent_via_upsert(): void
    {
        $account = $this->account('Degiro', 'Broker USD');
        $csv = "institution,account,balance,snapshot_date\nDegiro,Broker USD,100.00,2026-03-31\n";
        $csv2 = "institution,account,balance,snapshot_date\nDegiro,Broker USD,250.00,2026-03-31\n";

        $this->service()->import(ImportTarget::ACCOUNT_SNAPSHOTS, $csv, true);
        $this->service()->import(ImportTarget::ACCOUNT_SNAPSHOTS, $csv2, true);

        $this->assertSame(1, AccountBalanceSnapshot::query()->count());
        $this->assertDatabaseHas('account_balance_snapshots', [
            'account_id' => $account->id, 'snapshot_date' => '2026-03-31', 'balance' => '250.0000000000',
        ]);
    }

    public function test_liability_payment_import_and_preview_does_not_write(): void
    {
        Liability::factory()->create(['name' => 'Hypotéka byt Praha']);
        $csv = "liability,payment_date,total_amount,principal_portion,interest_portion\nHypotéka byt Praha,2026-01-31,12500.00,10000.00,2500.00\n";

        $preview = $this->service()->preview(ImportTarget::LIABILITY_PAYMENTS, $csv, true);
        $this->assertSame(1, $preview->validCount);
        $this->assertDatabaseCount('liability_payments', 0); // preview writes nothing

        $result = $this->service()->import(ImportTarget::LIABILITY_PAYMENTS, $csv, true);
        $this->assertSame(1, $result->imported);
        $this->assertDatabaseHas('liability_payments', ['total_amount' => '12500.0000000000', 'principal_portion' => '10000.0000000000']);
    }

    public function test_intra_batch_identical_transactions_dedupe_when_skip_enabled(): void
    {
        $this->account();
        $csv = implode("\n", [
            'institution,account,type,amount,transaction_date,counterparty',
            'Fio banka,Běžný účet,fee,10.00,2026-01-15,',
            'Fio banka,Běžný účet,fee,10.00,2026-01-15,',
        ]);

        $skipOn = $this->service()->import(ImportTarget::TRANSACTIONS, $csv, true);
        $this->assertSame(1, $skipOn->imported);
        $this->assertSame(1, $skipOn->skipped);
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_intra_batch_identical_transactions_both_insert_when_skip_disabled(): void
    {
        $this->account();
        $csv = implode("\n", [
            'institution,account,type,amount,transaction_date,counterparty',
            'Fio banka,Běžný účet,fee,10.00,2026-01-15,',
            'Fio banka,Běžný účet,fee,10.00,2026-01-15,',
        ]);

        $this->service()->import(ImportTarget::TRANSACTIONS, $csv, false);
        $this->assertSame(2, Transaction::query()->count());
    }

    public function test_rejects_wrong_date_format(): void
    {
        $this->account();
        $csv = "institution,account,type,amount,transaction_date,counterparty\nFio banka,Běžný účet,deposit,10.00,15.01.2026,\n";

        $preview = $this->service()->preview(ImportTarget::TRANSACTIONS, $csv, true);

        $this->assertSame(1, $preview->errorCount);
    }

    public function test_empty_optional_numeric_portions_import_as_null(): void
    {
        Liability::factory()->create(['name' => 'Hypotéka byt Praha']);
        $csv = "liability,payment_date,total_amount,principal_portion,interest_portion\nHypotéka byt Praha,2026-01-31,12500.00,,\n";

        $result = $this->service()->import(ImportTarget::LIABILITY_PAYMENTS, $csv, true);

        $this->assertSame(1, $result->imported);
        $this->assertDatabaseHas('liability_payments', [
            'total_amount' => '12500.0000000000', 'principal_portion' => null, 'interest_portion' => null,
        ]);
    }

    public function test_preview_reports_error_status_and_message_for_bad_row(): void
    {
        $this->account();
        $csv = "institution,account,type,amount,transaction_date,counterparty\nFio banka,Neznámý,deposit,10.00,2026-01-16,\n";

        $preview = $this->service()->preview(ImportTarget::TRANSACTIONS, $csv, true);

        $this->assertSame(1, $preview->errorCount);
        $row = $preview->rows->first();
        $this->assertInstanceOf(ImportRowResult::class, $row);
        $this->assertSame(ImportRowResult::ERROR, $row->status);
        $this->assertNotNull($row->error);
    }
}
