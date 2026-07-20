# CSV Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the CSV import screen (design spec §6.5) — bulk-import historical **transactions**, **account balance snapshots**, and **liability payments** from fixed-template CSV files, with per-row validation, a preview, FK resolution by natural keys, and idempotent re-import.

**Architecture:** Same layered pattern as the rest of the app. A pure, testable `CsvImportService` owns parse/validate/resolve/import logic (no Livewire). An `ImportTarget` enum is the single source of truth for the three targets and their fixed column templates + sample rows. A full-page `ImportData` Livewire component (`WithFileUploads`, `#[Layout('layouts.app')]`) drives upload → preview → import. An invokable `ImportSampleController` serves downloadable template CSVs. The service reuses existing repositories for writes (`TransactionRepository::create`, `LiabilityPaymentRepository::create`, `AccountBalanceSnapshotRepository::upsert`) and gets new read helpers for FK resolution + duplicate detection.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Breeze, Spatie Data 4, PostgreSQL, bcmath, PHPUnit, PHPStan (Larastan) level 6, Pint. All commands inside the container via `./vendor/bin/sail` (ignore `WWWUSER/WWWGROUP` warnings).

## Global Constraints

- **`declare(strict_types=1);`** every PHP file; type everything.
- **Mirror the established pattern** (reference committed files: `app/Services/CurrencyConverter.php`, `app/Repositories/AccountRepository.php`, `app/Livewire/ManageTransactions.php`, `app/Enums/TransactionType.php`, `app/Enums/Concerns/HasLabel.php`): `final readonly` services/repos; repos return DTOs and eager-load relations in DTO-returning reads; enums are backed string enums using `HasLabel`; components `#[Layout('layouts.app')]` with repos/services METHOD-injected into `render()`/actions; Breeze view styling.
- **ISO formats:** decimal dot (`1234.56`), dates `YYYY-MM-DD` (`date_format:Y-m-d`). Money stays a decimal string; never float.
- **`note` is NOT imported** — templates exclude it; imported rows persist `note => null`.
- **FK resolution by natural key:** account = (institution name + account name); liability = name. Unknown FK → row error (no auto-create). Values are trimmed; an empty cell becomes `null`.
- **Import valid rows, report invalid:** validate ALL rows; import only valid, non-duplicate rows inside one `DB::transaction()`; skipped/failed rows are reported with a reason.
- **Idempotence (skip duplicates, default on):** snapshots → `upsert` on `(account_id, snapshot_date)` (always idempotent; the toggle has no effect for snapshots); transactions → skip if a row matches `(account_id, transaction_date, type, amount, counterparty)`; liability payments → skip if a row matches `(liability_id, payment_date, total_amount)`.
- **Tests:** `#[CoversClass]` via `use` import; **snake_case test method names**; assert real DB effects; `===` comparisons; `Livewire::actingAs(User::factory()->create())->test(...)`. **Run `./vendor/bin/sail php ./vendor/bin/pint` before the final commit of each task.**
- **Baseline stays green:** phpstan `[OK]`, pint `--test` clean, full suite passing (currently 129).
- **Existing APIs to reuse unchanged:** `TransactionRepositoryInterface::create(array): TransactionData`; `LiabilityPaymentRepositoryInterface::create(array): LiabilityPaymentData`; `AccountBalanceSnapshotRepositoryInterface::upsert(array): AccountBalanceSnapshotData`; `TransactionType` (backed enum, `HasLabel`); models `Account belongsTo institution/currency`, `Liability`, factories exist.

## File Structure

```
app/Enums/ImportTarget.php                                              # new enum (targets, headers, sampleRow)
app/Data/Import/ImportRowResult.php                                     # new (line, raw, status, error, attributes)
app/Data/Import/ImportPreview.php                                       # new (counts + rows)
app/Data/Import/ImportResult.php                                        # new (imported/skipped/failed + rows)
app/Services/CsvImport/ImportRowException.php                           # new (FK-not-found signalling)
app/Services/CsvImportService.php                                       # new (parse/preview/import)
app/Repositories/AccountRepositoryInterface.php + AccountRepository.php # add findByInstitutionAndName()
app/Repositories/LiabilityRepositoryInterface.php + LiabilityRepository.php  # add findByName()
app/Repositories/TransactionRepositoryInterface.php + TransactionRepository.php  # add existsMatching()
app/Repositories/LiabilityPaymentRepositoryInterface.php + LiabilityPaymentRepository.php  # add existsMatching()
app/Livewire/ImportData.php + resources/views/livewire/import-data.blade.php
app/Http/Controllers/ImportSampleController.php
routes/web.php + resources/views/layouts/navigation.blade.php
tests/Unit/Enums/ImportTargetTest.php
tests/Unit/Repositories/ImportResolverTest.php
tests/Unit/Services/CsvImportServiceTest.php
tests/Feature/Livewire/ImportDataTest.php
tests/Feature/Http/ImportSampleControllerTest.php
```

---

## Task 1: `ImportTarget` enum

**Files:**
- Create: `app/Enums/ImportTarget.php`
- Test: `tests/Unit/Enums/ImportTargetTest.php`

**Interfaces:**
- Produces: `ImportTarget` backed string enum — cases `TRANSACTIONS='transactions'`, `ACCOUNT_SNAPSHOTS='account_snapshots'`, `LIABILITY_PAYMENTS='liability_payments'`; uses `HasLabel` (`label()`); `headers(): list<string>` (ordered column names per §3 of the spec); `sampleRow(): list<string>` (values aligned to `headers()`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/ImportTargetTest.php`:
```php
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
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ImportTargetTest` → FAIL (class missing).

- [ ] **Step 3: Create the enum**

Create `app/Enums/ImportTarget.php`:
```php
<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ImportTarget: string
{
    use HasLabel;

    case TRANSACTIONS = 'transactions';
    case ACCOUNT_SNAPSHOTS = 'account_snapshots';
    case LIABILITY_PAYMENTS = 'liability_payments';

    /** @return list<string> */
    public function headers(): array
    {
        return match ($this) {
            self::TRANSACTIONS => ['institution', 'account', 'type', 'amount', 'transaction_date', 'counterparty'],
            self::ACCOUNT_SNAPSHOTS => ['institution', 'account', 'balance', 'snapshot_date'],
            self::LIABILITY_PAYMENTS => ['liability', 'payment_date', 'total_amount', 'principal_portion', 'interest_portion'],
        };
    }

    /** @return list<string> */
    public function sampleRow(): array
    {
        return match ($this) {
            self::TRANSACTIONS => ['Fio banka', 'Fio běžný účet', 'dividend', '120.50', '2026-01-15', 'AAPL'],
            self::ACCOUNT_SNAPSHOTS => ['Degiro', 'Broker USD', '15000.00', '2026-03-31'],
            self::LIABILITY_PAYMENTS => ['Hypotéka byt Praha', '2026-01-31', '12500.00', '10000.00', '2500.00'],
        };
    }
}
```

- [ ] **Step 4: Run + pint + commit** — `--filter=ImportTargetTest` → PASS. `./vendor/bin/sail php ./vendor/bin/pint`. Commit:
```bash
git add app/Enums/ImportTarget.php tests/Unit/Enums/ImportTargetTest.php
git commit -m "feat: add ImportTarget enum (targets, CSV headers, sample rows)"
```

---

## Task 2: Repository FK-resolver + duplicate-detection helpers

**Files:**
- Modify: `app/Repositories/AccountRepositoryInterface.php`, `app/Repositories/AccountRepository.php`, `app/Repositories/LiabilityRepositoryInterface.php`, `app/Repositories/LiabilityRepository.php`, `app/Repositories/TransactionRepositoryInterface.php`, `app/Repositories/TransactionRepository.php`, `app/Repositories/LiabilityPaymentRepositoryInterface.php`, `app/Repositories/LiabilityPaymentRepository.php`
- Test: `tests/Unit/Repositories/ImportResolverTest.php`

**Interfaces:**
- Produces:
  - `AccountRepositoryInterface::findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData` (exact match on institution name + account name; `null` if not found).
  - `LiabilityRepositoryInterface::findByName(string $name): ?LiabilityData`.
  - `TransactionRepositoryInterface::existsMatching(array<string,mixed> $key): bool` (matches `account_id, transaction_date, type, amount, counterparty`; null-safe on `counterparty`).
  - `LiabilityPaymentRepositoryInterface::existsMatching(array<string,mixed> $key): bool` (matches `liability_id, payment_date, total_amount`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/ImportResolverTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Institution;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\Transaction;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\AccountRepository::class)]
#[CoversClass(\App\Repositories\LiabilityRepository::class)]
#[CoversClass(\App\Repositories\TransactionRepository::class)]
#[CoversClass(\App\Repositories\LiabilityPaymentRepository::class)]
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
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ImportResolverTest` → FAIL (undefined methods).

- [ ] **Step 3: Add `findByInstitutionAndName` to the Account repository**

In `app/Repositories/AccountRepositoryInterface.php` add the method (Collection/AccountData already imported):
```php
    public function findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData;
```
In `app/Repositories/AccountRepository.php` add `use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;`? No — use the Eloquent relation query. Add the method:
```php
    public function findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData
    {
        $account = Account::query()
            ->with(['institution', 'currency'])
            ->where('name', $accountName)
            ->whereHas('institution', function ($query) use ($institutionName): void {
                $query->where('name', $institutionName);
            })
            ->first();

        return $account === null ? null : AccountData::fromModel($account);
    }
```
(Untyped `$query` closure param avoids a Larastan generic-relation import; the existing repo methods use no closures, so keep it simple. If PHPStan flags the closure param in Task 5, type it `\Illuminate\Contracts\Database\Eloquent\Builder $query`.)

- [ ] **Step 4: Add `findByName` to the Liability repository**

In `app/Repositories/LiabilityRepositoryInterface.php` add:
```php
    public function findByName(string $name): ?LiabilityData;
```
In `app/Repositories/LiabilityRepository.php` add:
```php
    public function findByName(string $name): ?LiabilityData
    {
        $liability = Liability::query()->with(['institution', 'currency'])->where('name', $name)->first();

        return $liability === null ? null : LiabilityData::fromModel($liability);
    }
```

- [ ] **Step 5: Add `existsMatching` to Transaction + LiabilityPayment repositories**

In `app/Repositories/TransactionRepositoryInterface.php` add:
```php
    /** @param array<string, mixed> $key */
    public function existsMatching(array $key): bool;
```
In `app/Repositories/TransactionRepository.php` add:
```php
    /** @param array<string, mixed> $key */
    public function existsMatching(array $key): bool
    {
        return Transaction::query()
            ->where('account_id', $key['account_id'])
            ->where('transaction_date', $key['transaction_date'])
            ->where('type', $key['type'])
            ->where('amount', $key['amount'])
            ->when(
                $key['counterparty'] === null,
                fn ($query) => $query->whereNull('counterparty'),
                fn ($query) => $query->where('counterparty', $key['counterparty']),
            )
            ->exists();
    }
```
In `app/Repositories/LiabilityPaymentRepositoryInterface.php` add:
```php
    /** @param array<string, mixed> $key */
    public function existsMatching(array $key): bool;
```
In `app/Repositories/LiabilityPaymentRepository.php` add:
```php
    /** @param array<string, mixed> $key */
    public function existsMatching(array $key): bool
    {
        return LiabilityPayment::query()
            ->where('liability_id', $key['liability_id'])
            ->where('payment_date', $key['payment_date'])
            ->where('total_amount', $key['total_amount'])
            ->exists();
    }
```

- [ ] **Step 6: Run + pint + commit** — `--filter=ImportResolverTest` → PASS. `pint`. Commit:
```bash
git add app/Repositories/
git add tests/Unit/Repositories/ImportResolverTest.php
git commit -m "feat: add import FK-resolver + duplicate-detection repository helpers"
```

---

## Task 3: Import DTOs + `CsvImportService`

**Files:**
- Create: `app/Data/Import/ImportRowResult.php`, `app/Data/Import/ImportPreview.php`, `app/Data/Import/ImportResult.php`, `app/Services/CsvImport/ImportRowException.php`, `app/Services/CsvImportService.php`
- Test: `tests/Unit/Services/CsvImportServiceTest.php`

**Interfaces:**
- Consumes: the Task 2 repo helpers; `AccountBalanceSnapshotRepositoryInterface::upsert`; `TransactionType`; `ImportTarget`.
- Produces:
  - `ImportRowResult(int $line, array<string,string> $raw, string $status, ?string $error = null, ?array<string,mixed> $attributes = null)` with `const string VALID='valid'; DUPLICATE='duplicate'; ERROR='error';`.
  - `ImportPreview(int $total, int $validCount, int $duplicateCount, int $errorCount, Collection<int,ImportRowResult> $rows)`.
  - `ImportResult(int $imported, int $skipped, int $failed, Collection<int,ImportRowResult> $rows)`.
  - `CsvImportService::parse(string $contents): list<array<string,string>>`; `preview(ImportTarget $target, string $contents, bool $skipDuplicates): ImportPreview`; `import(ImportTarget $target, string $contents, bool $skipDuplicates): ImportResult`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/CsvImportServiceTest.php`:
```php
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
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=CsvImportServiceTest` → FAIL.

- [ ] **Step 3: Create the DTOs**

Create `app/Data/Import/ImportRowResult.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data\Import;

final readonly class ImportRowResult
{
    public const string VALID = 'valid';

    public const string DUPLICATE = 'duplicate';

    public const string ERROR = 'error';

    /**
     * @param array<string, string> $raw
     * @param array<string, mixed>|null $attributes
     */
    public function __construct(
        public int $line,
        public array $raw,
        public string $status,
        public ?string $error = null,
        public ?array $attributes = null,
    ) {}
}
```

Create `app/Data/Import/ImportPreview.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data\Import;

use Illuminate\Support\Collection;

final readonly class ImportPreview
{
    /** @param Collection<int, ImportRowResult> $rows */
    public function __construct(
        public int $total,
        public int $validCount,
        public int $duplicateCount,
        public int $errorCount,
        public Collection $rows,
    ) {}
}
```

Create `app/Data/Import/ImportResult.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data\Import;

use Illuminate\Support\Collection;

final readonly class ImportResult
{
    /** @param Collection<int, ImportRowResult> $rows */
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $failed,
        public Collection $rows,
    ) {}
}
```

- [ ] **Step 4: Create the exception**

Create `app/Services/CsvImport/ImportRowException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\CsvImport;

use RuntimeException;

final class ImportRowException extends RuntimeException {}
```

- [ ] **Step 5: Create `CsvImportService`**

Create `app/Services/CsvImportService.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Import\ImportPreview;
use App\Data\Import\ImportResult;
use App\Data\Import\ImportRowResult;
use App\Enums\ImportTarget;
use App\Enums\TransactionType;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use App\Services\CsvImport\ImportRowException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CsvImportService
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private LiabilityRepositoryInterface $liabilities,
        private TransactionRepositoryInterface $transactions,
        private LiabilityPaymentRepositoryInterface $payments,
        private AccountBalanceSnapshotRepositoryInterface $snapshots,
    ) {}

    /** @return list<array<string, string>> */
    public function parse(string $contents): array
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($contents));

        if ($lines === false || $lines === [] || $lines[0] === '') {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $values = array_slice($values, 0, count($header));
            $values = array_pad($values, count($header), '');
            /** @var array<string, string> $row */
            $row = array_combine($header, array_map(fn (?string $v): string => (string) $v, $values));
            $rows[] = $row;
        }

        return $rows;
    }

    public function preview(ImportTarget $target, string $contents, bool $skipDuplicates): ImportPreview
    {
        $rows = $this->evaluate($target, $contents, $skipDuplicates);

        return new ImportPreview(
            total: $rows->count(),
            validCount: $rows->where('status', ImportRowResult::VALID)->count(),
            duplicateCount: $rows->where('status', ImportRowResult::DUPLICATE)->count(),
            errorCount: $rows->where('status', ImportRowResult::ERROR)->count(),
            rows: $rows,
        );
    }

    public function import(ImportTarget $target, string $contents, bool $skipDuplicates): ImportResult
    {
        $rows = $this->evaluate($target, $contents, $skipDuplicates);

        DB::transaction(function () use ($target, $rows): void {
            foreach ($rows as $row) {
                if ($row->status === ImportRowResult::VALID && $row->attributes !== null) {
                    $this->persist($target, $row->attributes);
                }
            }
        });

        return new ImportResult(
            imported: $rows->where('status', ImportRowResult::VALID)->count(),
            skipped: $rows->where('status', ImportRowResult::DUPLICATE)->count(),
            failed: $rows->where('status', ImportRowResult::ERROR)->count(),
            rows: $rows,
        );
    }

    /** @return Collection<int, ImportRowResult> */
    private function evaluate(ImportTarget $target, string $contents, bool $skipDuplicates): Collection
    {
        $rows = new Collection;

        foreach ($this->parse($contents) as $index => $raw) {
            $line = $index + 2; // +1 header, +1 to 1-index
            $normalized = $this->normalize($raw);

            $validator = Validator::make($normalized, $this->rules($target));
            if ($validator->fails()) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::ERROR, (string) $validator->errors()->first()));

                continue;
            }

            try {
                $attributes = $this->build($target, $normalized);
            } catch (ImportRowException $e) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::ERROR, $e->getMessage()));

                continue;
            }

            if ($skipDuplicates && $this->isDuplicate($target, $attributes)) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::DUPLICATE));

                continue;
            }

            $rows->push(new ImportRowResult($line, $raw, ImportRowResult::VALID, null, $attributes));
        }

        return $rows;
    }

    /**
     * @param array<string, string> $raw
     * @return array<string, string|null>
     */
    private function normalize(array $raw): array
    {
        return array_map(function (string $value): ?string {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }, $raw);
    }

    /** @return array<string, mixed> */
    private function rules(ImportTarget $target): array
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => [
                'institution' => ['required', 'string'],
                'account' => ['required', 'string'],
                'type' => ['required', Rule::enum(TransactionType::class)],
                'amount' => ['required', 'numeric'],
                'transaction_date' => ['required', 'date_format:Y-m-d'],
                'counterparty' => ['nullable', 'string', 'max:255'],
            ],
            ImportTarget::ACCOUNT_SNAPSHOTS => [
                'institution' => ['required', 'string'],
                'account' => ['required', 'string'],
                'balance' => ['required', 'numeric'],
                'snapshot_date' => ['required', 'date_format:Y-m-d'],
            ],
            ImportTarget::LIABILITY_PAYMENTS => [
                'liability' => ['required', 'string'],
                'payment_date' => ['required', 'date_format:Y-m-d'],
                'total_amount' => ['required', 'numeric'],
                'principal_portion' => ['nullable', 'numeric'],
                'interest_portion' => ['nullable', 'numeric'],
            ],
        };
    }

    /**
     * @param array<string, string|null> $row
     * @return array<string, mixed>
     */
    private function build(ImportTarget $target, array $row): array
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => [
                'account_id' => $this->resolveAccountId($row),
                'type' => $row['type'],
                'amount' => $row['amount'],
                'transaction_date' => $row['transaction_date'],
                'note' => null,
                'counterparty' => $row['counterparty'],
            ],
            ImportTarget::ACCOUNT_SNAPSHOTS => [
                'account_id' => $this->resolveAccountId($row),
                'balance' => $row['balance'],
                'snapshot_date' => $row['snapshot_date'],
                'note' => null,
            ],
            ImportTarget::LIABILITY_PAYMENTS => [
                'liability_id' => $this->resolveLiabilityId($row),
                'payment_date' => $row['payment_date'],
                'total_amount' => $row['total_amount'],
                'principal_portion' => $row['principal_portion'],
                'interest_portion' => $row['interest_portion'],
                'note' => null,
            ],
        };
    }

    /** @param array<string, string|null> $row */
    private function resolveAccountId(array $row): string
    {
        $account = $this->accounts->findByInstitutionAndName((string) $row['institution'], (string) $row['account']);

        if ($account === null) {
            throw new ImportRowException("Account '{$row['account']}' at institution '{$row['institution']}' not found.");
        }

        return $account->id;
    }

    /** @param array<string, string|null> $row */
    private function resolveLiabilityId(array $row): string
    {
        $liability = $this->liabilities->findByName((string) $row['liability']);

        if ($liability === null) {
            throw new ImportRowException("Liability '{$row['liability']}' not found.");
        }

        return $liability->id;
    }

    /** @param array<string, mixed> $attributes */
    private function isDuplicate(ImportTarget $target, array $attributes): bool
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => $this->transactions->existsMatching($attributes),
            ImportTarget::LIABILITY_PAYMENTS => $this->payments->existsMatching($attributes),
            ImportTarget::ACCOUNT_SNAPSHOTS => false, // upsert is idempotent; never a "duplicate"
        };
    }

    /** @param array<string, mixed> $attributes */
    private function persist(ImportTarget $target, array $attributes): void
    {
        match ($target) {
            ImportTarget::TRANSACTIONS => $this->transactions->create($attributes),
            ImportTarget::ACCOUNT_SNAPSHOTS => $this->snapshots->upsert($attributes),
            ImportTarget::LIABILITY_PAYMENTS => $this->payments->create($attributes),
        };
    }
}
```

- [ ] **Step 6: Run + pint + commit** — `--filter=CsvImportServiceTest` → PASS. Then `--filter=ImportResolverTest` and `--filter=ImportTargetTest` still green. `pint`. Commit:
```bash
git add app/Data/Import app/Services/CsvImport app/Services/CsvImportService.php tests/Unit/Services/CsvImportServiceTest.php
git commit -m "feat: add CsvImportService (parse, preview, idempotent import) + DTOs"
```

---

## Task 4: `ImportData` Livewire screen + sample-CSV controller

**Files:**
- Create: `app/Livewire/ImportData.php`, `resources/views/livewire/import-data.blade.php`, `app/Http/Controllers/ImportSampleController.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ImportDataTest.php`, `tests/Feature/Http/ImportSampleControllerTest.php`

**Interfaces:**
- Consumes: `CsvImportService`, `ImportTarget`.
- Produces: route `import` (auth) → `ImportData`; route `import.sample` (`/import/sample/{target}`, auth) → `ImportSampleController`. Behaviors: pick target → upload CSV (`wire:model="csv"`) → live preview table (status per row) → `skipDuplicates` toggle → `import()` persists and flashes a summary; sample-CSV download per target.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/ImportDataTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ImportData;
use App\Models\Account;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImportData::class)]
class ImportDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/import')->assertRedirect('/login');
    }

    private function transactionsCsv(): UploadedFile
    {
        $csv = implode("\n", [
            'institution,account,type,amount,transaction_date,counterparty',
            'Fio banka,Běžný účet,deposit,1500.00,2026-01-15,',
            'Fio banka,Neznámý,deposit,10.00,2026-01-16,',
        ]);

        return UploadedFile::fake()->createWithContent('transactions.csv', $csv);
    }

    public function test_preview_counts_valid_and_error_rows(): void
    {
        Account::factory()->create([
            'institution_id' => Institution::factory()->create(['name' => 'Fio banka'])->id,
            'name' => 'Běžný účet',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ImportData::class)
            ->set('target', 'transactions')
            ->set('csv', $this->transactionsCsv())
            ->assertSee('1') // valid count / row
            ->assertSee('not found');
    }

    public function test_import_persists_valid_rows_and_skips_duplicates_on_reimport(): void
    {
        Account::factory()->create([
            'institution_id' => Institution::factory()->create(['name' => 'Fio banka'])->id,
            'name' => 'Běžný účet',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ImportData::class)
            ->set('target', 'transactions')
            ->set('csv', $this->transactionsCsv())
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame(1, Transaction::query()->count());

        // Re-import the same file → the valid row is a duplicate, nothing added.
        Livewire::actingAs(User::factory()->create())
            ->test(ImportData::class)
            ->set('target', 'transactions')
            ->set('csv', $this->transactionsCsv())
            ->set('skipDuplicates', true)
            ->call('import');

        $this->assertSame(1, Transaction::query()->count());
    }
}
```

Create `tests/Feature/Http/ImportSampleControllerTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Http\Controllers\ImportSampleController::class)]
class ImportSampleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_sample(): void
    {
        $this->get('/import/sample/transactions')->assertRedirect('/login');
    }

    public function test_downloads_sample_csv_with_headers(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/import/sample/transactions');

        $response->assertOk();
        $response->assertDownloadOffered('transactions-sample.csv');
        $this->assertStringContainsString('institution,account,type,amount,transaction_date,counterparty', $response->streamedContent());
    }

    public function test_unknown_target_is_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/import/sample/nope')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ImportDataTest` and `--filter=ImportSampleControllerTest` → FAIL.

- [ ] **Step 3: Scaffold the component** — `./vendor/bin/sail artisan make:livewire ImportData --class`, then replace the class per below.

- [ ] **Step 4: Write the component**

Replace `app/Livewire/ImportData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ImportTarget;
use App\Services\CsvImportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ImportData extends Component
{
    use WithFileUploads;

    public ?string $target = null;

    public ?TemporaryUploadedFile $csv = null;

    public bool $skipDuplicates = true;

    public function updatedTarget(): void
    {
        $this->reset('csv');
    }

    public function import(CsvImportService $service): void
    {
        $importTarget = $this->target === null ? null : ImportTarget::tryFrom($this->target);

        if ($importTarget === null || $this->csv === null) {
            return;
        }

        $result = $service->import($importTarget, $this->csv->get(), $this->skipDuplicates);

        $this->reset('csv');

        session()->flash('status', "Imported {$result->imported}, skipped {$result->skipped}, failed {$result->failed}.");
    }

    public function render(CsvImportService $service): View
    {
        $importTarget = $this->target === null ? null : ImportTarget::tryFrom($this->target);

        $preview = ($importTarget !== null && $this->csv !== null)
            ? $service->preview($importTarget, $this->csv->get(), $this->skipDuplicates)
            : null;

        return view('livewire.import-data', [
            'targets' => ImportTarget::cases(),
            'preview' => $preview,
        ]);
    }
}
```
(`$this->csv->get()` reads the temporary upload's contents. `updatedTarget()` clears a stale file when the entity changes.)

- [ ] **Step 5: Write the view**

Create `resources/views/livewire/import-data.blade.php`, mirroring the Breeze styling used in `resources/views/livewire/manage-transactions.blade.php` (same `py-8` / `max-w-5xl` wrapper, card, `session('status')` green flash, `<x-primary-button>`, table classes). Required elements:
- Heading `Import`.
- Target `<select wire:model.live="target">` over `$targets` (`$t->value` / `$t->label()`), blank first option `—`.
- When a target is chosen: a `Download sample CSV` link — `<a href="{{ route('import.sample', $target) }}">` — and a file input `<input type="file" wire:model="csv" accept=".csv">` plus a `wire:loading` hint on `csv`.
- A checkbox `<input type="checkbox" wire:model.live="skipDuplicates">` labelled `Skip duplicate rows`.
- **Preview** — `@if ($preview !== null)`: a summary line `Valid: {{ $preview->validCount }} · Duplicates: {{ $preview->duplicateCount }} · Errors: {{ $preview->errorCount }}` and a table over `$preview->rows` (`wire:key="row-{{ $row->line }}"`): columns Line / Row (join `$row->raw` values with ` · ` — e.g. `{{ implode(' · ', $row->raw) }}`) / Status (`{{ ucfirst($row->status) }}`) / Message (`{{ $row->error }}`). Colour the status cell: green for `valid`, gray for `duplicate`, red for `error`.
- An Import button: `<x-primary-button wire:click="import" @disabled($preview === null || $preview->validCount === 0)>Import {{ $preview?->validCount ?? 0 }} rows</x-primary-button>`.
- `@if (session('status'))` green flash at the top (same markup as manage-transactions).

- [ ] **Step 6: Write the sample controller**

Create `app/Http/Controllers/ImportSampleController.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportTarget;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportSampleController extends Controller
{
    public function __invoke(string $target): StreamedResponse
    {
        $importTarget = ImportTarget::tryFrom($target);

        abort_if($importTarget === null, 404);

        $headers = $importTarget->headers();
        $sample = $importTarget->sampleRow();

        return response()->streamDownload(function () use ($headers, $sample): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            fputcsv($handle, $sample);
            fclose($handle);
        }, "{$target}-sample.csv", ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 7: Route + nav + run + pint + commit**

In `routes/web.php` add imports `use App\Http\Controllers\ImportSampleController;` and `use App\Livewire\ImportData;`, and in the `auth` group (place after the `transactions` route):
```php
    Route::get('/import', ImportData::class)->name('import');
    Route::get('/import/sample/{target}', ImportSampleController::class)->name('import.sample');
```
In `resources/views/layouts/navigation.blade.php` add a desktop `<x-nav-link :href="route('import')" :active="request()->routeIs('import')">{{ __('Import') }}</x-nav-link>` and the matching `<x-responsive-nav-link>` (place both after the `transactions` links). Run both `--filter=ImportDataTest` and `--filter=ImportSampleControllerTest` → PASS. `pint`. Commit:
```bash
git add app/Livewire/ImportData.php resources/views/livewire/import-data.blade.php app/Http/Controllers/ImportSampleController.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ImportDataTest.php tests/Feature/Http/ImportSampleControllerTest.php
git commit -m "feat: add CSV import screen (upload, preview, import) + sample downloads"
```

---

## Task 5: Static analysis, style, full-suite + route smoke

- [ ] **Step 1: PHPStan** `./vendor/bin/sail php ./vendor/bin/phpstan analyse --no-progress` → `[OK]`. Fix inline (Collection generics on `ImportPreview`/`ImportResult`/`evaluate`; the `whereHas`/`when` closure param types — type them `\Illuminate\Contracts\Database\Eloquent\Builder $query` if flagged; `str_getcsv`/`preg_split` nullable returns; the `?TemporaryUploadedFile` `->get()`). No blanket ignores.
- [ ] **Step 2: Pint** `./vendor/bin/sail php ./vendor/bin/pint` then `--test` → clean.
- [ ] **Step 3: Full suite** `./vendor/bin/sail artisan test` → all pass, pristine (baseline 129 + new).
- [ ] **Step 4: Route smoke** `./vendor/bin/sail artisan route:list | grep -E 'import'` shows `import` and `import.sample`; `./vendor/bin/sail npm run build` succeeds.
- [ ] **Step 5: Commit** (only if 1/2 changed files) `chore: satisfy phpstan/pint for CSV import`.

---

## Self-Review

**Spec coverage (§6.5 + brainstorm decisions):**
- fixed template per entity + downloadable sample CSV → `ImportTarget::headers()/sampleRow()` + `ImportSampleController` → Tasks 1/4 ✅
- three targets (transactions, account_snapshots, liability_payments) → `ImportTarget` + service match arms → Tasks 1/3 ✅
- ISO formats (dot decimal, `date_format:Y-m-d`) → `rules()` → Task 3 ✅
- `note` not imported → templates exclude it; `build()` sets `note => null` → Tasks 1/3 ✅
- FK resolution by natural key (institution+account, liability name); unknown → row error → `resolveAccountId`/`resolveLiabilityId` + `ImportRowException` → Tasks 2/3 ✅
- validate all, import only valid, report invalid → `evaluate()` + `import()` → Task 3 ✅
- idempotence: snapshot upsert (toggle no-op), transactions/payments skip-if-identical (default on) → `isDuplicate()` + `existsMatching` + snapshot `false` arm → Tasks 2/3 ✅
- upload → preview → import UI + summary → `ImportData` + view → Task 4 ✅

**Placeholder scan:** complete code for the enum, four repo helpers, three DTOs, exception, full `CsvImportService`, component, controller, and all tests; the view is specified element-by-element with exact bindings mirroring `manage-transactions.blade.php`. No TBD/TODO/placeholder code. ✅

**Type consistency:** `ImportTarget` values (`transactions`/`account_snapshots`/`liability_payments`) match `tryFrom` in the component/controller and the `match` arms in the service. `build()` attribute keys match each repo's `create`/`upsert` (`account_id`,`type`,`amount`,`transaction_date`,`note`,`counterparty` / `account_id`,`balance`,`snapshot_date`,`note` / `liability_id`,`payment_date`,`total_amount`,`principal_portion`,`interest_portion`,`note`). `isDuplicate()` passes the built attributes to `existsMatching`, whose key fields (`account_id,transaction_date,type,amount,counterparty` and `liability_id,payment_date,total_amount`) are all present in those attributes. `ImportRowResult::VALID/DUPLICATE/ERROR` are the only status values produced and consumed (view reads `$row->status`). `preview()`/`import()` return the documented `ImportPreview`/`ImportResult`. ✅

**Notes for the implementer:** keep `@js()`/`wire:key`, snake_case test methods, `?T` nullable syntax, single-line empty bodies; run Pint before each task commit. The snapshot arm of `isDuplicate()` returns `false` on purpose — `upsert` is idempotent, so a re-imported snapshot updates in place and is reported as `imported`, never `duplicate`. `$this->csv->get()` reads the temporary upload; preview recomputes in `render()` on every change (no cached property).
