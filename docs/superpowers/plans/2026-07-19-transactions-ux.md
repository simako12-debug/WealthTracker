# Transactions Write Screen (priority UX) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the transactions entry screen — the most-used, fastest-path form in the app: pick institution → cascade-filtered account → see the account's currency → enter type + amount with a live "≈ X CZK" conversion preview → date (default today) → optional note/counterparty; on save, keep the account/date selected for rapid repeated entry; a recent-transactions table below allows quick edit/delete.

**Architecture:** Same layered pattern as the other CRUD screens — `TransactionData` DTO (denormalizing account/institution/currency for display), a `final readonly TransactionRepository` (`recent/find/create/update/delete`), a `Livewire\Form` (`TransactionForm`), and a full-page Livewire component (`#[Layout('layouts.app')]`). Two additions: `AccountRepository::forInstitution()` (for the cascade select) and `CurrencyConverter::toCzkByCode()` (a code-based entry point so the Livewire layer never touches Eloquent `Currency` models). The live CZK preview is a computed value in `render()` using the selected account's currency.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4.3, Breeze, Spatie Data 4, PostgreSQL, bcmath, PHPUnit, PHPStan (Larastan) level 6, Pint. All commands inside the container via `./vendor/bin/sail` (ignore `WWWUSER/WWWGROUP` warnings).

## Global Constraints

- **`declare(strict_types=1);`** every PHP file; type everything.
- **Mirror the established refined CRUD pattern** (reference committed files: `app/Repositories/AccountRepository.php`, `app/Livewire/ManageAccounts.php`, `app/Livewire/Forms/AccountForm.php`, `resources/views/livewire/manage-accounts.blade.php`): `final readonly` repos returning DTOs; eager-load relations in every DTO-returning read; `delete()` idempotent; components `#[Layout('layouts.app')]`, repos METHOD-injected into `render()`/actions; row ids via `@js($id)`; enum via `->label()`; forms `Livewire\Form` with `rules()`/`toAttributes()`; nullable props `?string`.
- **Money:** amounts are decimal strings (`decimal:10`); conversion uses `CurrencyConverter` (bcmath scale 10). Never float.
- **Reuse Plan-2/3/5 code unchanged unless noted:** `App\Services\CurrencyConverter` (extend with `toCzkByCode`, keep `toCzk(Currency)` working), `App\Data\ConversionResult(amount, rate, rateDate)`, `CurrencyRepositoryInterface::findByCode`, `App\Enums\TransactionType` (has `label()` via `HasLabel`).
- **Tests:** `#[CoversClass]` via `use` import; **snake_case test method names** (repo Pint convention); `Livewire::actingAs(User::factory()->create())->test(...)`; assert real DB effects; `=== ` comparisons. **Run `./vendor/bin/sail php ./vendor/bin/pint` before the final commit of each task** (the repo's Pint enforces `?T` nullable syntax, single-line empty bodies, ordered imports, snake_case test methods — do not skip it).
- **Baseline stays green:** phpstan `[OK]`, pint `--test` clean, full suite passing.
- **Existing model API (Plan 1):** `Transaction(account_id, type:TransactionType, amount:decimal:10 string, transaction_date:date, note?, counterparty?; belongsTo account)`; `Account belongsTo institution/currency`.

## File Structure

```
app/Data/TransactionData.php
app/Repositories/TransactionRepositoryInterface.php + TransactionRepository.php
app/Repositories/AccountRepositoryInterface.php + AccountRepository.php   # add forInstitution()
app/Services/CurrencyConverter.php                                        # add toCzkByCode()
app/Livewire/Forms/TransactionForm.php
app/Livewire/ManageTransactions.php + resources/views/livewire/manage-transactions.blade.php
app/Providers/RepositoryServiceProvider.php   # bind TransactionRepository
routes/web.php + navigation.blade.php
tests/Unit/Repositories/TransactionRepositoryTest.php
tests/Unit/Repositories/AccountRepositoryForInstitutionTest.php
tests/Unit/Services/CurrencyConverterByCodeTest.php
tests/Feature/Livewire/ManageTransactionsTest.php
```

---

## Task 1: Transaction DTO + repository, Account `forInstitution()`

**Files:**
- Create: `app/Data/TransactionData.php`, `app/Repositories/TransactionRepositoryInterface.php`, `app/Repositories/TransactionRepository.php`
- Modify: `app/Repositories/AccountRepositoryInterface.php`, `app/Repositories/AccountRepository.php`, `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/TransactionRepositoryTest.php`, `tests/Unit/Repositories/AccountRepositoryForInstitutionTest.php`

**Interfaces:**
- Produces: `TransactionData(string $id, string $accountId, string $accountName, string $institutionName, string $accountCurrencyCode, TransactionType $type, string $amount, CarbonImmutable $transactionDate, ?string $note, ?string $counterparty)`; `TransactionRepositoryInterface` with `recent(int $limit): Collection<int,TransactionData>`, `find(string $id): ?TransactionData`, `create(array): TransactionData`, `update(string $id, array): TransactionData`, `delete(string $id): void`; `AccountRepositoryInterface::forInstitution(string $institutionId): Collection<int,AccountData>` (active accounts of that institution, ordered by name).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Repositories/TransactionRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\TransactionData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\Transaction;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\TransactionRepository::class)]
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
```

Create `tests/Unit/Repositories/AccountRepositoryForInstitutionTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use App\Models\Institution;
use App\Repositories\AccountRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\AccountRepository::class)]
class AccountRepositoryForInstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_institution_returns_only_that_institutions_active_accounts(): void
    {
        $a = Institution::factory()->create();
        $b = Institution::factory()->create();
        Account::factory()->create(['institution_id' => $a->id, 'name' => 'A1', 'is_active' => true]);
        Account::factory()->create(['institution_id' => $a->id, 'name' => 'A2', 'is_active' => false]);
        Account::factory()->create(['institution_id' => $b->id, 'name' => 'B1', 'is_active' => true]);

        $result = $this->app->make(AccountRepositoryInterface::class)->forInstitution($a->id);

        $this->assertCount(1, $result);
        $this->assertContainsOnlyInstancesOf(AccountData::class, $result);
        $this->assertSame('A1', $result->first()->name);
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=TransactionRepositoryTest` and `--filter=AccountRepositoryForInstitutionTest` → FAIL.

- [ ] **Step 3: Create `TransactionData`**

Create `app/Data/TransactionData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class TransactionData extends Data
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $accountName,
        public string $institutionName,
        public string $accountCurrencyCode,
        public TransactionType $type,
        public string $amount,
        public CarbonImmutable $transactionDate,
        public ?string $note,
        public ?string $counterparty,
    ) {
    }

    public static function fromModel(Transaction $transaction): self
    {
        return new self(
            id: $transaction->id,
            accountId: $transaction->account_id,
            accountName: $transaction->account->name,
            institutionName: $transaction->account->institution->name,
            accountCurrencyCode: $transaction->account->currency->code,
            type: $transaction->type,
            amount: $transaction->amount,
            transactionDate: $transaction->transaction_date->toImmutable(),
            note: $transaction->note,
            counterparty: $transaction->counterparty,
        );
    }
}
```

- [ ] **Step 4: Create the repository interface + impl**

Create `app/Repositories/TransactionRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\TransactionData;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    /** @return Collection<int, TransactionData> */
    public function recent(int $limit): Collection;

    public function find(string $id): ?TransactionData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TransactionData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): TransactionData;

    public function delete(string $id): void;
}
```

Create `app/Repositories/TransactionRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\TransactionData;
use App\Models\Transaction;
use Illuminate\Support\Collection;

final readonly class TransactionRepository implements TransactionRepositoryInterface
{
    private const array WITH = ['account.institution', 'account.currency'];

    /** @return Collection<int, TransactionData> */
    public function recent(int $limit): Collection
    {
        return Transaction::query()
            ->with(self::WITH)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction): TransactionData => TransactionData::fromModel($transaction));
    }

    public function find(string $id): ?TransactionData
    {
        $transaction = Transaction::query()->with(self::WITH)->find($id);

        return $transaction === null ? null : TransactionData::fromModel($transaction);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TransactionData
    {
        $transaction = Transaction::query()->create($attributes);

        return TransactionData::fromModel($transaction->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): TransactionData
    {
        $transaction = Transaction::query()->findOrFail($id);
        $transaction->update($attributes);

        return TransactionData::fromModel($transaction->load(self::WITH));
    }

    public function delete(string $id): void
    {
        Transaction::query()->where('id', $id)->delete();
    }
}
```

- [ ] **Step 5: Add `forInstitution()` to the Account repository**

In `app/Repositories/AccountRepositoryInterface.php` add (import `Collection`):
```php
    /** @return Collection<int, AccountData> */
    public function forInstitution(string $institutionId): Collection;
```
In `app/Repositories/AccountRepository.php` add:
```php
    /** @return Collection<int, AccountData> */
    public function forInstitution(string $institutionId): Collection
    {
        return Account::query()
            ->with(['institution', 'currency'])
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): AccountData => AccountData::fromModel($account));
    }
```
(Add `use Illuminate\Support\Collection;` if not already imported.)

- [ ] **Step 6: Bind + run + pint + commit**

Add `TransactionRepositoryInterface::class => TransactionRepository::class` to `RepositoryServiceProvider`. Run both filters → PASS. Run `./vendor/bin/sail php ./vendor/bin/pint`. Commit:
```bash
git add app/Data/TransactionData.php app/Repositories/TransactionRepositoryInterface.php app/Repositories/TransactionRepository.php app/Repositories/AccountRepositoryInterface.php app/Repositories/AccountRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/TransactionRepositoryTest.php tests/Unit/Repositories/AccountRepositoryForInstitutionTest.php
git commit -m "feat: add Transaction DTO+repository and Account forInstitution()"
```

---

## Task 2: CurrencyConverter `toCzkByCode()`

**Files:**
- Modify: `app/Services/CurrencyConverter.php`
- Test: `tests/Unit/Services/CurrencyConverterByCodeTest.php`

**Interfaces:**
- Produces: `CurrencyConverter::toCzkByCode(string $amount, string $currencyCode, CarbonImmutable $date): ?ConversionResult` — same semantics as `toCzk` but keyed by currency code (resolves the currency via `CurrencyRepositoryInterface::findByCode`; returns null if the code is unknown or no rate exists). The existing `toCzk(Currency, ...)` keeps working (both delegate to a shared private method).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/CurrencyConverterByCodeTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\ConversionResult;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use App\Services\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyConverter::class)]
class CurrencyConverterByCodeTest extends TestCase
{
    use RefreshDatabase;

    private function converter(): CurrencyConverter
    {
        return $this->app->make(CurrencyConverter::class);
    }

    public function test_czk_code_is_identity(): void
    {
        Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzkByCode('500.0000000000', 'CZK', CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('500.0000000000', $result->amount);
    }

    public function test_converts_by_code_using_latest_rate(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id, 'currency_to_id' => $czk->id,
            'rate' => '23.0000000000', 'rate_date' => '2026-03-10', 'source' => FxSource::CNB,
        ]);

        $result = $this->converter()->toCzkByCode('10.0000000000', 'USD', CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('230.0000000000', $result->amount);
    }

    public function test_returns_null_for_unknown_code(): void
    {
        Currency::factory()->create(['code' => 'CZK']);

        $this->assertNull($this->converter()->toCzkByCode('10.0000000000', 'ZZZ', CarbonImmutable::parse('2026-03-15')));
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=CurrencyConverterByCodeTest` → FAIL.

- [ ] **Step 3: Refactor + add the method**

Edit `app/Services/CurrencyConverter.php`. Keep the constructor and `CZK` const. Replace the body of `toCzk()` to delegate to a shared private `convert()`, and add `toCzkByCode()`:
```php
    public function toCzk(string $amount, Currency $from, CarbonImmutable $date): ?ConversionResult
    {
        return $this->convert($amount, $from->id, $from->code, $date);
    }

    public function toCzkByCode(string $amount, string $currencyCode, CarbonImmutable $date): ?ConversionResult
    {
        $from = $this->currencies->findByCode($currencyCode);

        if ($from === null) {
            return null;
        }

        return $this->convert($amount, $from->id, $from->code, $date);
    }

    private function convert(string $amount, string $fromId, string $fromCode, CarbonImmutable $date): ?ConversionResult
    {
        if ($fromCode === self::CZK) {
            return new ConversionResult(amount: $amount, rate: '1.0000000000', rateDate: $date);
        }

        $czk = $this->currencies->findByCode(self::CZK);

        if ($czk === null) {
            return null;
        }

        $rate = $this->rates->latestRate($fromId, $czk->id, $date);

        if ($rate === null) {
            return null;
        }

        return new ConversionResult(
            amount: bcmul($amount, $rate->rate, 10),
            rate: $rate->rate,
            rateDate: $rate->rateDate,
        );
    }
```
(`CurrencyData::$id` supplies `$from->id`; `findByCode` returns `CurrencyData`. Confirm the existing `toCzk` test still passes — it exercises the same `convert()` path.)

- [ ] **Step 4: Run + pint + commit** — `--filter=CurrencyConverter` (both converter tests) → PASS. `pint`. Commit `feat: add CurrencyConverter::toCzkByCode for the Livewire preview`.

---

## Task 3: ManageTransactions screen (cascade + live CZK preview + recent table)

**Files:**
- Create: `app/Livewire/Forms/TransactionForm.php`, `app/Livewire/ManageTransactions.php`, `resources/views/livewire/manage-transactions.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageTransactionsTest.php`

**Interfaces:**
- Consumes: `TransactionRepositoryInterface`, `AccountRepositoryInterface` (`forInstitution`, `find`), `InstitutionRepositoryInterface::all()`, `CurrencyConverter::toCzkByCode`, `TransactionType`.
- Produces: route `transactions` (auth) → `ManageTransactions`. Behaviors: institution select cascades to account select (`forInstitution`); selecting an account shows its currency code; entering amount+account+date shows a live "≈ N CZK" preview (or "rate unavailable"); save persists then keeps institution/account/date (clears amount/type/note/counterparty) for rapid entry; a recent-transactions table supports edit (loads into form) and delete.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageTransactionsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Enums\TransactionType;
use App\Livewire\ManageTransactions;
use App\Models\Account;
use App\Models\Currency;
use App\Models\FxRate;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageTransactions::class)]
class ManageTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/transactions')->assertRedirect('/login');
    }

    public function test_selecting_institution_filters_accounts(): void
    {
        $institution = Institution::factory()->create();
        $account = Account::factory()->create(['institution_id' => $institution->id, 'name' => 'Fio CZK']);
        Account::factory()->create(['name' => 'Other']); // different institution

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->assertSee('Fio CZK')
            ->assertDontSee('Other');
    }

    public function test_create_transaction_and_keep_account_selected(): void
    {
        $institution = Institution::factory()->create();
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $account = Account::factory()->create(['institution_id' => $institution->id, 'currency_id' => $czk->id]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->set('form.accountId', $account->id)
            ->set('form.type', TransactionType::DEPOSIT->value)
            ->set('form.amount', '1500')
            ->set('form.transactionDate', '2026-03-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', ['account_id' => $account->id, 'type' => 'deposit']);
        // account stays selected for rapid entry; amount cleared
        $component->assertSet('form.accountId', $account->id)
            ->assertSet('form.amount', null);
    }

    public function test_live_czk_preview_uses_converter(): void
    {
        $institution = Institution::factory()->create();
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $account = Account::factory()->create(['institution_id' => $institution->id, 'currency_id' => $usd->id]);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id, 'currency_to_id' => $czk->id,
            'rate' => '23.0000000000', 'rate_date' => '2026-03-10', 'source' => FxSource::CNB,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->set('form.institutionId', $institution->id)
            ->set('form.accountId', $account->id)
            ->set('form.amount', '10')
            ->set('form.transactionDate', '2026-03-15')
            ->assertSee('230');
    }

    public function test_validation_requires_account_type_amount(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('save')
            ->assertHasErrors(['form.accountId', 'form.type', 'form.amount']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $transaction = Transaction::factory()->create(['amount' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('edit', $transaction->id)
            ->assertSet('form.accountId', $transaction->account_id)
            ->set('form.amount', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageTransactions::class)
            ->call('delete', $transaction->id);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ManageTransactionsTest` → FAIL.

- [ ] **Step 3: Scaffold** — `./vendor/bin/sail artisan livewire:form TransactionForm` and `make:livewire ManageTransactions --class`.

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/TransactionForm.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\TransactionData;
use App\Enums\TransactionType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TransactionForm extends Form
{
    public ?string $id = null;

    public ?string $institutionId = null;

    public ?string $accountId = null;

    public ?string $type = null;

    public ?string $amount = null;

    public ?string $transactionDate = null;

    public ?string $note = null;

    public ?string $counterparty = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accountId' => ['required', 'exists:accounts,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric'],
            'transactionDate' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'counterparty' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function setTransaction(TransactionData $data): void
    {
        $this->id = $data->id;
        $this->accountId = $data->accountId;
        $this->type = $data->type->value;
        $this->amount = $data->amount;
        $this->transactionDate = $data->transactionDate->toDateString();
        $this->note = $data->note;
        $this->counterparty = $data->counterparty;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'account_id' => $this->accountId,
            'type' => $this->type,
            'amount' => $this->amount,
            'transaction_date' => $this->transactionDate,
            'note' => $this->note,
            'counterparty' => $this->counterparty,
        ];
    }
}
```
(`institutionId` is a UI-only cascade helper — not part of `toAttributes()`.)

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageTransactions.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\ConversionResult;
use App\Enums\TransactionType;
use App\Livewire\Forms\TransactionForm;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\InstitutionRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use App\Services\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageTransactions extends Component
{
    public TransactionForm $form;

    public function mount(): void
    {
        $this->form->transactionDate = CarbonImmutable::now()->toDateString();
    }

    public function updatedFormInstitutionId(): void
    {
        $this->form->accountId = null;
    }

    public function edit(string $id, TransactionRepositoryInterface $transactions, AccountRepositoryInterface $accounts): void
    {
        $data = $transactions->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setTransaction($data);
        $account = $accounts->find($data->accountId);
        $this->form->institutionId = $account?->institutionId;
    }

    public function save(TransactionRepositoryInterface $transactions): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $transactions->create($this->form->toAttributes());
        } else {
            $transactions->update($this->form->id, $this->form->toAttributes());
        }

        // Keep institution/account/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->type = null;
        $this->form->amount = null;
        $this->form->note = null;
        $this->form->counterparty = null;

        session()->flash('status', 'Transaction saved.');
    }

    public function delete(string $id, TransactionRepositoryInterface $transactions): void
    {
        $transactions->delete($id);
    }

    private function preview(): ?ConversionResult
    {
        if ($this->form->accountId === null || $this->form->amount === null || $this->form->transactionDate === null) {
            return null;
        }

        if (is_numeric($this->form->amount) === false) {
            return null;
        }

        $account = app(AccountRepositoryInterface::class)->find($this->form->accountId);

        if ($account === null) {
            return null;
        }

        return app(CurrencyConverter::class)->toCzkByCode(
            (string) $this->form->amount,
            $account->currencyCode,
            CarbonImmutable::parse($this->form->transactionDate),
        );
    }

    public function render(
        TransactionRepositoryInterface $transactions,
        AccountRepositoryInterface $accounts,
        InstitutionRepositoryInterface $institutions,
    ): View {
        /** @var Collection<int, \App\Data\AccountData> $accountOptions */
        $accountOptions = $this->form->institutionId === null
            ? new Collection()
            : $accounts->forInstitution($this->form->institutionId);

        $selectedAccount = $this->form->accountId === null ? null : $accounts->find($this->form->accountId);

        return view('livewire.manage-transactions', [
            'institutions' => $institutions->all(),
            'accountOptions' => $accountOptions,
            'selectedAccount' => $selectedAccount,
            'preview' => $this->preview(),
            'types' => TransactionType::cases(),
            'recent' => $transactions->recent(15),
        ]);
    }
}
```
(Uses `app()` in `preview()`/`render` helper paths where method injection isn't available; action methods use injection. This matches how lifecycle-adjacent helpers resolve services elsewhere in the app.)

- [ ] **Step 6: Write the view**

Create `resources/views/livewire/manage-transactions.blade.php`. Requirements (mirror the Breeze styling used in the other screens):
- A card with the entry form (NOT a modal — this is the primary page):
  - Institution `<select wire:model.live="form.institutionId">` (options from `$institutions`, `$i->id`/`$i->name`; a blank first option).
  - Account `<select wire:model.live="form.accountId">` (options from `$accountOptions`, `$a->id`/`$a->name`; disabled/empty when no institution chosen).
  - When `$selectedAccount !== null`: a readonly currency badge `{{ $selectedAccount->currencyCode }}`.
  - Type `<select wire:model="form.type">` over `$types` using `$type->label()`.
  - Amount `<x-text-input wire:model.live="form.amount" />` (numeric).
  - **Live preview:** `@if ($preview !== null) ≈ {{ number_format((float) $preview->amount, 2) }} CZK @elseif ($selectedAccount !== null && filled($form->amount)) <span>rate unavailable</span> @endif` (only compute/show when an account + amount are present).
  - Date `<input type="date" wire:model.live="form.transactionDate">`.
  - Note textarea, counterparty text input (optional).
  - `<x-input-error :messages="$errors->get('form.<field>')" />` under each field.
  - Save button (`wire:click="save"` or a `<form wire:submit="save">`).
  - `@if (session('status')) ... @endif` flash.
- Below, a "Recent transactions" table: columns Date / Account / Type / Amount / Counterparty / actions. Iterate `$recent`; show `$t->transactionDate->toDateString()`, `$t->accountName` (+ `$t->institutionName` sub-text), `$t->type->label()`, `{{ $t->amount }} {{ $t->accountCurrencyCode }}`, `$t->counterparty`. Edit button `wire:click="edit(@js($t->id))"`; Delete `wire:click="delete(@js($t->id))" wire:confirm="Delete this transaction?"`.

- [ ] **Step 7: Route + nav + run + pint + commit**

Add `Route::get('/transactions', \App\Livewire\ManageTransactions::class)->name('transactions');` to the `auth` group; add nav links (desktop + responsive) for `transactions` — place it first (most-used). Run `--filter=ManageTransactionsTest` → PASS. `pint`. Commit `feat: add transactions entry screen (cascade, live CZK preview, recent table)`.

---

## Task 4: Static analysis, style, full-suite + route smoke

- [ ] **Step 1: PHPStan** `./vendor/bin/sail php ./vendor/bin/phpstan analyse --no-progress` → `[OK]`. Fix inline (Collection/paginator generics, the `?ConversionResult` preview type, `app()` resolutions typed). No blanket ignores.
- [ ] **Step 2: Pint** `pint` then `--test` → clean.
- [ ] **Step 3: Full suite** `./vendor/bin/sail artisan test` → all pass, pristine.
- [ ] **Step 4: Route smoke** `route:list | grep transactions` shows the route; `npm run build` succeeds.
- [ ] **Step 5: Commit** (only if 1/2 changed files) `chore: satisfy phpstan/pint for transactions screen`.

---

## Self-Review

**Spec coverage (spec §6.2 transactions — the priority form):**
- institution select → cascade-filtered account select → `forInstitution()` → Task 1/3 ✅
- account currency badge (readonly) → `$selectedAccount->currencyCode` → Task 3 ✅
- type select, amount input → Task 3 ✅
- live "≈ X CZK" via last rate ≤ transaction_date (fallback "rate unavailable") → `CurrencyConverter::toCzkByCode` + `preview()` → Tasks 2/3 ✅
- date default today → `mount()` sets `transactionDate` = today ✅
- optional note/counterparty → Task 3 ✅
- save resets for rapid repeated entry, stays on page → `save()` keeps institution/account/date, clears amount/type/note/counterparty ✅
- recent-transactions table with quick edit/delete → `recent(15)` + edit/delete actions ✅
- **Deferred to later plans:** liability_payments + account_balance_snapshots screens + in-app dashboard widgets (next plan); CSV import (final plan).

**Placeholder scan:** complete code for DTO, repositories, converter, form, component, and all tests; the view is specified element-by-element with the exact bindings/fields (mirroring the existing screens) — concrete, not a placeholder. ✅

**Type consistency:** `TransactionData` denormalizes account/institution/currency (eager-loaded `account.institution`,`account.currency`); form maps `accountId`→`account_id` etc. (`institutionId` is UI-only, excluded from `toAttributes()`); `toCzkByCode(amount, currencyCode, date)` matches the `preview()` call and returns `?ConversionResult` (`amount` string, used via `number_format`); `recent()`/`find()`/`create()`/`update()`/`delete()` signatures match the component's injected calls. `wire:model.live` on institution/account/amount/date drives the cascade + preview re-render. ✅

**Notes for the implementer:** the preview must be defensive — no conversion attempt until an account + numeric amount + date are all present (avoids errors on partial input and a wasted query). Keep `@js()` ids, `->label()`, snake_case test methods, and run Pint before committing each task (the DTO nullable props will be normalized to `?T`, empty bodies to `) {}`).
```
