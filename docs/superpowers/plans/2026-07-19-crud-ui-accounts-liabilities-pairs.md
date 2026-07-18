# CRUD UI — Accounts, Liabilities, Currency Pairs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the generic-CRUD write UI by adding Accounts, Liabilities, and Currency Pairs management screens, following the CRUD pattern already established and refined for Institutions/Currencies. These three add FK dropdowns (institution/currency), enum fields, dates, and (for pairs) a source auto-prefill rule.

**Architecture:** Identical to the established pattern — each entity has a `final readonly {Entity}Repository` (interface-bound, returns Spatie Data DTOs; `paginate` returns `LengthAwarePaginator<int, {Entity}Data>`), a `Livewire\Form` with `rules()`, and a full-page Livewire component (`#[Layout('layouts.app')]`, `WithPagination`, method-injected repos, actions `create/edit/save/delete/cancel/sortBy`). DTOs denormalize FK display fields (institution name, currency code) via eager-loaded relations. **The already-committed Institution slice is the reference implementation** — mirror `app/Repositories/InstitutionRepository.php`, `app/Livewire/ManageInstitutions.php`, `app/Livewire/Forms/InstitutionForm.php`, and `resources/views/livewire/manage-institutions.blade.php` for structure; this plan specifies the per-entity field differences and gives complete code for the parts that differ.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4.3, Breeze, Spatie Data 4, PostgreSQL, PHPUnit, PHPStan (Larastan) level 6, Pint. All commands inside the container via `./vendor/bin/sail` (ignore `WWWUSER/WWWGROUP` warnings).

## Global Constraints

- **`declare(strict_types=1);`** every PHP file; type everything.
- **Mirror the established refined pattern exactly** (read the reference files above): `final readonly` repos returning DTOs; `paginate(sortField, sortDirection, perPage): LengthAwarePaginator` with a `@return LengthAwarePaginator<int, XData>` docblock and a `SORTABLE` allowlist guarding `orderBy`; `delete()` idempotent via `where('id',$id)->delete()`; `update()`/`edit()` load via `find`/`findOrFail`. Components: `#[Layout('layouts.app')]`, `WithPagination`, repos METHOD-injected into `render()`/actions, actions `create/edit/save/delete/cancel/sortBy`, modal Cancel → `cancel()`. Row action ids via `@js($model->id)`. Enum labels via `->label()` (the `App\Enums\Concerns\HasLabel` trait). Forms are `Livewire\Form` subclasses with `rules()`, `set{Entity}()`, `toAttributes()`; nullable props typed `?string` (matching existing forms).
- **DTOs:** `final extends Data`, explicit `fromModel()`; enums as `App\Enums\*`; dates as `CarbonImmutable`; money/decimals as `string` (models cast `decimal:10`/`decimal:4`); `null|string` union order in DTO property declarations.
- **FK dropdowns:** the component's `render()` loads option lists via repositories (`CurrencyRepositoryInterface::all()`, `InstitutionRepositoryInterface::all()`), never raw Eloquent in the component.
- **Auth:** routes inside the `auth` group; guest-redirect tested. Nav links desktop (`x-nav-link`) + responsive (`x-responsive-nav-link`).
- **Tests:** `#[CoversClass]` via `use` import; **snake_case test method names** (repo Pint convention — e.g. `test_creates_account`); `Livewire::actingAs(User::factory()->create())->test(...)`; assert real DB effects; `=== ` comparisons.
- **Baseline stays green:** phpstan `[OK]`, pint `--test` clean, full suite passing.
- **Existing model APIs (Plan 1, unchanged):** `Account(institution_id, currency_id, name, type:AccountType, is_active:bool, note; belongsTo institution/currency)`; `Liability(institution_id, name, principal_amount, currency_id, interest_rate, monthly_payment?, start_date, end_date?, is_active, note; belongsTo institution/currency)`; `CurrencyPair(base_currency_id, quote_currency_id, source:FxSource, is_active, note; belongsTo baseCurrency/quoteCurrency; unique(base,quote))`.

## File Structure

```
app/Enums/AccountType.php                 # add: use HasLabel
app/Enums/FxSource.php                     # add: use HasLabel
app/Repositories/InstitutionRepositoryInterface.php  # add all()
app/Repositories/InstitutionRepository.php           # add all()
app/Data/AccountData.php
app/Repositories/AccountRepository{Interface}.php
app/Livewire/Forms/AccountForm.php
app/Livewire/ManageAccounts.php + resources/views/livewire/manage-accounts.blade.php
app/Data/LiabilityData.php
app/Repositories/LiabilityRepository{Interface}.php
app/Livewire/Forms/LiabilityForm.php
app/Livewire/ManageLiabilities.php + resources/views/livewire/manage-liabilities.blade.php
app/Data/CurrencyPairData.php              # extend: add note
app/Repositories/CurrencyPairRepositoryInterface.php # add CRUD
app/Repositories/CurrencyPairRepository.php          # add CRUD
app/Livewire/Forms/CurrencyPairForm.php
app/Livewire/ManageCurrencyPairs.php + resources/views/livewire/manage-currency-pairs.blade.php
app/Providers/RepositoryServiceProvider.php  # add Account, Liability bindings
routes/web.php + navigation.blade.php
tests/... (unit repo tests + feature Livewire tests per entity)
```

---

## Task 1: Supporting changes — enum labels + Institution `all()`

**Files:**
- Modify: `app/Enums/AccountType.php`, `app/Enums/FxSource.php`, `app/Repositories/InstitutionRepositoryInterface.php`, `app/Repositories/InstitutionRepository.php`
- Test: `tests/Unit/Repositories/InstitutionRepositoryAllTest.php`

**Interfaces:**
- Produces: `AccountType`/`FxSource` gain `label()` (via `HasLabel`); `InstitutionRepositoryInterface::all(): Collection<int, InstitutionData>` for dropdowns.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/InstitutionRepositoryAllTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\InstitutionData;
use App\Models\Institution;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\InstitutionRepository::class)]
class InstitutionRepositoryAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_returns_collection_of_data_ordered_by_name(): void
    {
        Institution::factory()->create(['name' => 'Zeta']);
        Institution::factory()->create(['name' => 'Alpha']);

        $all = $this->app->make(InstitutionRepositoryInterface::class)->all();

        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(InstitutionData::class, $all);
        $this->assertSame('Alpha', $all->first()->name);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=InstitutionRepositoryAllTest`
Expected: FAIL — `all()` missing.

- [ ] **Step 3: Add `HasLabel` to the two enums**

In `app/Enums/AccountType.php` add `use App\Enums\Concerns\HasLabel;` (top) and `use HasLabel;` inside the enum body. Same for `app/Enums/FxSource.php`.

- [ ] **Step 4: Add `all()` to the Institution repository**

In `app/Repositories/InstitutionRepositoryInterface.php`, add (import `Illuminate\Support\Collection`):
```php
    /** @return Collection<int, InstitutionData> */
    public function all(): Collection;
```
In `app/Repositories/InstitutionRepository.php`, add (import `Collection`):
```php
    /** @return Collection<int, InstitutionData> */
    public function all(): Collection
    {
        return Institution::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Institution $institution): InstitutionData => InstitutionData::fromModel($institution));
    }
```

- [ ] **Step 5: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=InstitutionRepositoryAllTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/AccountType.php app/Enums/FxSource.php app/Repositories/InstitutionRepositoryInterface.php app/Repositories/InstitutionRepository.php tests/Unit/Repositories/InstitutionRepositoryAllTest.php
git commit -m "feat: add HasLabel to AccountType/FxSource and Institution all() for dropdowns"
```

---

## Task 2: Account DTO + repository

**Files:**
- Create: `app/Data/AccountData.php`, `app/Repositories/AccountRepositoryInterface.php`, `app/Repositories/AccountRepository.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/AccountRepositoryTest.php`

**Interfaces:**
- Produces: `AccountData(string $id, string $institutionId, string $institutionName, string $currencyId, string $currencyCode, string $name, AccountType $type, bool $isActive, null|string $note)`; `AccountRepositoryInterface` with `paginate/find/create/update/delete` (mirroring Institution's).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/AccountRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountData;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use App\Repositories\AccountRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\AccountRepository::class)]
class AccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): AccountRepositoryInterface
    {
        return $this->app->make(AccountRepositoryInterface::class);
    }

    public function test_create_persists_and_returns_data_with_relations(): void
    {
        $institution = Institution::factory()->create(['name' => 'Fio banka']);
        $currency = Currency::factory()->create(['code' => 'CZK']);

        $data = $this->repository()->create([
            'institution_id' => $institution->id,
            'currency_id' => $currency->id,
            'name' => 'Fio běžný účet',
            'type' => AccountType::BANK->value,
            'is_active' => true,
            'note' => null,
        ]);

        $this->assertInstanceOf(AccountData::class, $data);
        $this->assertSame('Fio banka', $data->institutionName);
        $this->assertSame('CZK', $data->currencyCode);
        $this->assertSame(AccountType::BANK, $data->type);
        $this->assertTrue($data->isActive);
        $this->assertDatabaseHas('accounts', ['name' => 'Fio běžný účet', 'type' => 'bank']);
    }

    public function test_paginate_returns_data_objects(): void
    {
        Account::factory()->create();
        $page = $this->repository()->paginate('name', 'asc', 15);
        $this->assertContainsOnlyInstancesOf(AccountData::class, $page->items());
    }

    public function test_update_and_delete(): void
    {
        $account = Account::factory()->create(['name' => 'Old']);

        $updated = $this->repository()->update($account->id, [
            'institution_id' => $account->institution_id,
            'currency_id' => $account->currency_id,
            'name' => 'New',
            'type' => $account->type->value,
            'is_active' => false,
            'note' => null,
        ]);
        $this->assertSame('New', $updated->name);
        $this->assertFalse($updated->isActive);

        $this->repository()->delete($account->id);
        $this->assertNull($this->repository()->find($account->id));
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=AccountRepositoryTest`
Expected: FAIL.

- [ ] **Step 3: Create the DTO**

Create `app/Data/AccountData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AccountType;
use App\Models\Account;
use Spatie\LaravelData\Data;

final class AccountData extends Data
{
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $currencyId,
        public string $currencyCode,
        public string $name,
        public AccountType $type,
        public bool $isActive,
        public null|string $note,
    ) {
    }

    public static function fromModel(Account $account): self
    {
        return new self(
            id: $account->id,
            institutionId: $account->institution_id,
            institutionName: $account->institution->name,
            currencyId: $account->currency_id,
            currencyCode: $account->currency->code,
            name: $account->name,
            type: $account->type,
            isActive: $account->is_active,
            note: $account->note,
        );
    }
}
```

- [ ] **Step 4: Create the interface**

Create `app/Repositories/AccountRepositoryInterface.php` (mirror `InstitutionRepositoryInterface`, `AccountData` in place of `InstitutionData`): `paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator` (`@return LengthAwarePaginator<int, AccountData>`), `find(string $id): ?AccountData`, `create(array $attributes): AccountData`, `update(string $id, array $attributes): AccountData`, `delete(string $id): void`.

- [ ] **Step 5: Create the implementation**

Create `app/Repositories/AccountRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class AccountRepository implements AccountRepositoryInterface
{
    private const array SORTABLE = ['name', 'type', 'is_active', 'created_at'];

    /** @return LengthAwarePaginator<int, AccountData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'name';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Account::query()
            ->with(['institution', 'currency'])
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Account $account): AccountData => AccountData::fromModel($account));
    }

    public function find(string $id): ?AccountData
    {
        $account = Account::query()->with(['institution', 'currency'])->find($id);

        return $account === null ? null : AccountData::fromModel($account);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AccountData
    {
        $account = Account::query()->create($attributes);

        return AccountData::fromModel($account->load(['institution', 'currency']));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountData
    {
        $account = Account::query()->findOrFail($id);
        $account->update($attributes);

        return AccountData::fromModel($account->load(['institution', 'currency']));
    }

    public function delete(string $id): void
    {
        Account::query()->where('id', $id)->delete();
    }
}
```

- [ ] **Step 6: Bind + run + commit**

Add `AccountRepositoryInterface::class => AccountRepository::class` to `RepositoryServiceProvider` `$bindings` (with imports). Run `./vendor/bin/sail artisan test --filter=AccountRepositoryTest` → PASS. Commit:
```bash
git add app/Data/AccountData.php app/Repositories/AccountRepositoryInterface.php app/Repositories/AccountRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/AccountRepositoryTest.php
git commit -m "feat: add Account DTO + CRUD repository"
```

---

## Task 3: ManageAccounts Livewire screen

**Files:**
- Create: `app/Livewire/Forms/AccountForm.php`, `app/Livewire/ManageAccounts.php`, `resources/views/livewire/manage-accounts.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageAccountsTest.php`

**Interfaces:**
- Consumes: `AccountRepositoryInterface`, `InstitutionRepositoryInterface::all()`, `CurrencyRepositoryInterface::all()`, `AccountType`.
- Produces: route `accounts` (auth) → `ManageAccounts`; list with institution/currency/type columns + create/edit modal (institution & currency selects, type select, active checkbox) + delete.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageAccountsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\AccountType;
use App\Livewire\ManageAccounts;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageAccounts::class)]
class ManageAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/accounts')->assertRedirect('/login');
    }

    public function test_create_account(): void
    {
        $institution = Institution::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('create')
            ->set('form.institutionId', $institution->id)
            ->set('form.currencyId', $currency->id)
            ->set('form.name', 'eToro USD')
            ->set('form.type', AccountType::INVESTMENT->value)
            ->set('form.isActive', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('accounts', ['name' => 'eToro USD', 'type' => 'investment']);
    }

    public function test_validation_requires_institution_and_currency(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('create')
            ->set('form.name', 'x')
            ->set('form.type', AccountType::BANK->value)
            ->call('save')
            ->assertHasErrors(['form.institutionId', 'form.currencyId']);
    }

    public function test_edit_and_delete(): void
    {
        $account = Account::factory()->create(['name' => 'Old']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('edit', $account->id)
            ->assertSet('form.name', 'Old')
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Renamed']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccounts::class)
            ->call('delete', $account->id);
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }
}
```

- [ ] **Step 2: Confirm failure** — `./vendor/bin/sail artisan test --filter=ManageAccountsTest` → FAIL.

- [ ] **Step 3: Scaffold** — `./vendor/bin/sail artisan livewire:form AccountForm` and `./vendor/bin/sail artisan make:livewire ManageAccounts --class`.

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/AccountForm.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\AccountData;
use App\Enums\AccountType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AccountForm extends Form
{
    public ?string $id = null;

    public ?string $institutionId = null;

    public ?string $currencyId = null;

    public string $name = '';

    public ?string $type = null;

    public bool $isActive = true;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'institutionId' => ['required', 'exists:institutions,id'],
            'currencyId' => ['required', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setAccount(AccountData $data): void
    {
        $this->id = $data->id;
        $this->institutionId = $data->institutionId;
        $this->currencyId = $data->currencyId;
        $this->name = $data->name;
        $this->type = $data->type->value;
        $this->isActive = $data->isActive;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'institution_id' => $this->institutionId,
            'currency_id' => $this->currencyId,
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'note' => $this->note,
        ];
    }
}
```

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageAccounts.php` (mirror `ManageInstitutions` with `AccountForm`/`AccountRepositoryInterface`, flash "Account saved."). `render()` supplies dropdown data + enum cases:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AccountType;
use App\Livewire\Forms\AccountForm;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageAccounts extends Component
{
    use WithPagination;

    public AccountForm $form;

    public bool $showModal = false;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, AccountRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setAccount($data);
        $this->showModal = true;
    }

    public function save(AccountRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Account saved.');
    }

    public function delete(string $id, AccountRepositoryInterface $repository): void
    {
        $repository->delete($id);
    }

    public function cancel(): void
    {
        $this->form->reset();
        $this->showModal = false;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render(
        AccountRepositoryInterface $accounts,
        InstitutionRepositoryInterface $institutions,
        CurrencyRepositoryInterface $currencies,
    ): View {
        return view('livewire.manage-accounts', [
            'accounts' => $accounts->paginate($this->sortField, $this->sortDirection, 15),
            'institutions' => $institutions->all(),
            'currencies' => $currencies->all(),
            'types' => AccountType::cases(),
        ]);
    }
}
```

- [ ] **Step 6: Write the view**

Create `resources/views/livewire/manage-accounts.blade.php` mirroring `manage-institutions.blade.php`, with table columns Name / Institution / Currency / Type / Active, and a modal whose form has: institution `<select wire:model="form.institutionId">` (options from `$institutions`, `$i->id`/`$i->name`), currency `<select wire:model="form.currencyId">` (`$c->id`/`$c->code`), name `x-text-input`, type `<select>` over `$types` using `$type->label()`, active `<input type="checkbox" wire:model="form.isActive">`, note textarea. Each field has `<x-input-error :messages="$errors->get('form.<field>')" />`. Row action buttons use `wire:click="edit(@js($account->id))"` / `delete(@js($account->id))`. Cancel button `wire:click="cancel"`. Include `{{ $accounts->links() }}`. Display `$account->type->label()`, `$account->institutionName`, `$account->currencyCode`, and Active as Yes/No.

- [ ] **Step 7: Route + nav + run + commit**

Add `Route::get('/accounts', \App\Livewire\ManageAccounts::class)->name('accounts');` to the `auth` group; add desktop + responsive nav links for `accounts`. Run `./vendor/bin/sail artisan test --filter=ManageAccountsTest` → PASS. Commit:
```bash
git add app/Livewire/Forms/AccountForm.php app/Livewire/ManageAccounts.php resources/views/livewire/manage-accounts.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ManageAccountsTest.php
git commit -m "feat: add Accounts CRUD Livewire screen"
```

---

## Task 4: Liability DTO + repository

**Files:** `app/Data/LiabilityData.php`, `app/Repositories/LiabilityRepository{Interface}.php`, bind in provider, `tests/Unit/Repositories/LiabilityRepositoryTest.php`.

**Interfaces:**
- Produces: `LiabilityData(string $id, string $institutionId, string $institutionName, string $currencyId, string $currencyCode, string $name, string $principalAmount, string $interestRate, null|string $monthlyPayment, \Carbon\CarbonImmutable $startDate, null|\Carbon\CarbonImmutable $endDate, bool $isActive, null|string $note)`; repository `paginate/find/create/update/delete` mirroring Account's (eager-load institution+currency).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/LiabilityRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityData;
use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\LiabilityRepository::class)]
class LiabilityRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): LiabilityRepositoryInterface
    {
        return $this->app->make(LiabilityRepositoryInterface::class);
    }

    public function test_create_and_read_with_relations_and_dates(): void
    {
        $institution = Institution::factory()->create(['name' => 'KB']);
        $currency = Currency::factory()->create(['code' => 'CZK']);

        $data = $this->repository()->create([
            'institution_id' => $institution->id,
            'currency_id' => $currency->id,
            'name' => 'Hypotéka byt Praha',
            'principal_amount' => '3500000.0000000000',
            'interest_rate' => '4.9000',
            'monthly_payment' => '18000.0000000000',
            'start_date' => '2024-01-01',
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ]);

        $this->assertInstanceOf(LiabilityData::class, $data);
        $this->assertSame('KB', $data->institutionName);
        $this->assertSame('CZK', $data->currencyCode);
        $this->assertSame('2024-01-01', $data->startDate->toDateString());
        $this->assertNull($data->endDate);
        $this->assertDatabaseHas('liabilities', ['name' => 'Hypotéka byt Praha']);
    }

    public function test_update_and_delete(): void
    {
        $liability = Liability::factory()->create(['name' => 'Old']);

        $updated = $this->repository()->update($liability->id, array_merge($liability->only([
            'institution_id', 'currency_id',
        ]), [
            'name' => 'New',
            'principal_amount' => $liability->principal_amount,
            'interest_rate' => $liability->interest_rate,
            'monthly_payment' => $liability->monthly_payment,
            'start_date' => $liability->start_date->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ]));
        $this->assertSame('New', $updated->name);

        $this->repository()->delete($liability->id);
        $this->assertNull($this->repository()->find($liability->id));
    }
}
```

- [ ] **Step 2: Confirm failure**, then create the DTO, interface, impl (mirror `AccountRepository`, eager-load `institution`+`currency`, `SORTABLE = ['name','start_date','is_active','created_at']`). DTO `fromModel` maps dates via `->toImmutable()` and nullable `end_date`/`monthly_payment`:

Create `app/Data/LiabilityData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Liability;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class LiabilityData extends Data
{
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $currencyId,
        public string $currencyCode,
        public string $name,
        public string $principalAmount,
        public string $interestRate,
        public null|string $monthlyPayment,
        public CarbonImmutable $startDate,
        public null|CarbonImmutable $endDate,
        public bool $isActive,
        public null|string $note,
    ) {
    }

    public static function fromModel(Liability $liability): self
    {
        return new self(
            id: $liability->id,
            institutionId: $liability->institution_id,
            institutionName: $liability->institution->name,
            currencyId: $liability->currency_id,
            currencyCode: $liability->currency->code,
            name: $liability->name,
            principalAmount: $liability->principal_amount,
            interestRate: $liability->interest_rate,
            monthlyPayment: $liability->monthly_payment,
            startDate: $liability->start_date->toImmutable(),
            endDate: $liability->end_date?->toImmutable(),
            isActive: $liability->is_active,
            note: $liability->note,
        );
    }
}
```
Interface: `paginate(...): LengthAwarePaginator` (`@return LengthAwarePaginator<int, LiabilityData>`), `find/create/update/delete`. Impl mirrors `AccountRepository` (eager-load institution+currency; `SORTABLE=['name','start_date','is_active','created_at']`). Bind in provider.

- [ ] **Step 3: Run `--filter=LiabilityRepositoryTest` → PASS. Commit** `feat: add Liability DTO + CRUD repository`.

---

## Task 5: ManageLiabilities Livewire screen

**Files:** `app/Livewire/Forms/LiabilityForm.php`, `app/Livewire/ManageLiabilities.php`, `resources/views/livewire/manage-liabilities.blade.php`, routes/nav, `tests/Feature/Livewire/ManageLiabilitiesTest.php`.

- [ ] **Step 1: Write the failing test** `tests/Feature/Livewire/ManageLiabilitiesTest.php` (mirror ManageAccountsTest): guest redirect on `/liabilities`; create (set institutionId, currencyId, name, principalAmount, interestRate, startDate, isActive → save → assertHasNoErrors + DB row); validation requires institution/currency/name/principal/rate/startDate; edit+delete.

- [ ] **Step 2: Confirm failure. Scaffold** `livewire:form LiabilityForm`, `make:livewire ManageLiabilities --class`.

- [ ] **Step 3: Write the form** — `LiabilityForm` props: `?string $id`, `?string $institutionId`, `?string $currencyId`, `string $name=''`, `?string $principalAmount`, `?string $interestRate`, `?string $monthlyPayment`, `?string $startDate`, `?string $endDate`, `bool $isActive=true`, `?string $note`. `rules()`:
```php
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'institutionId' => ['required', 'exists:institutions,id'],
            'currencyId' => ['required', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'principalAmount' => ['required', 'numeric', 'min:0'],
            'interestRate' => ['required', 'numeric', 'min:0'],
            'monthlyPayment' => ['nullable', 'numeric', 'min:0'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
```
`setLiability(LiabilityData $d)` fills props (dates via `->toDateString()`; `monthlyPayment`/`endDate` nullable). `toAttributes()` maps to snake_case DB columns (`institution_id`, `currency_id`, `principal_amount`, `interest_rate`, `monthly_payment`, `start_date`, `end_date`, `is_active`, `name`, `note`).

- [ ] **Step 4: Write the component** — mirror `ManageAccounts` with `LiabilityForm`/`LiabilityRepositoryInterface`; `render()` provides `institutions`, `currencies` (no enum). Flash "Liability saved."

- [ ] **Step 5: Write the view** — mirror `manage-accounts.blade.php`: columns Name / Institution / Currency / Principal / Rate / Active; modal with institution & currency selects, name, principal (`x-text-input` numeric), interest rate, monthly payment (optional), start_date (`<input type="date">`), end_date (optional date), active checkbox, note. `@js` ids, `cancel()`.

- [ ] **Step 6: Route `/liabilities` (auth) + nav + run `--filter=ManageLiabilitiesTest` → PASS. Commit** `feat: add Liabilities CRUD Livewire screen`.

---

## Task 6: CurrencyPair CRUD (repository + DTO note) 

**Files:** `app/Data/CurrencyPairData.php` (add `note`), `app/Repositories/CurrencyPairRepositoryInterface.php` + impl (add CRUD, keep `activePairs()`), `tests/Unit/Repositories/CurrencyPairRepositoryCrudTest.php`.

- [ ] **Step 1: Write the failing test** `CurrencyPairRepositoryCrudTest`: `paginate` returns `CurrencyPairData` items with base/quote codes; `create`/`find`/`update`/`delete`; assert unique-pair handling is left to the form (repo just persists).

- [ ] **Step 2: Confirm failure. Extend the DTO** — add `public null|string $note` to `CurrencyPairData` constructor + `fromModel` (`note: $pair->note`). (Adding a nullable field is safe for the existing sync consumer.)

- [ ] **Step 3: Extend interface + impl** — add to `CurrencyPairRepositoryInterface`: `paginate(...): LengthAwarePaginator` (`@return LengthAwarePaginator<int, CurrencyPairData>`), `find(string $id): ?CurrencyPairData`, `create(array $attributes): CurrencyPairData`, `update(string $id, array $attributes): CurrencyPairData`, `delete(string $id): void` (keep `activePairs()`). Impl mirrors `AccountRepository` but eager-loads `baseCurrency`+`quoteCurrency`; `SORTABLE = ['source','is_active','created_at']`.

- [ ] **Step 4: Run `--filter=CurrencyPairRepositoryCrudTest` → PASS. Commit** `feat: add CurrencyPair CRUD repository methods + note on DTO`.

---

## Task 7: ManageCurrencyPairs Livewire screen (with source auto-prefill)

**Files:** `app/Livewire/Forms/CurrencyPairForm.php`, `app/Livewire/ManageCurrencyPairs.php`, `resources/views/livewire/manage-currency-pairs.blade.php`, routes/nav, `tests/Feature/Livewire/ManageCurrencyPairsTest.php`.

**Spec rule:** `source` is pre-filled by rule — if either currency is CZK → `cnb`, else `frankfurter` — and remains editable.

- [ ] **Step 1: Write the failing test** including the auto-prefill behavior:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Livewire\ManageCurrencyPairs;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageCurrencyPairs::class)]
class ManageCurrencyPairsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/currency-pairs')->assertRedirect('/login');
    }

    public function test_source_prefills_cnb_when_czk_involved(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->assertSet('form.source', FxSource::CNB->value);
    }

    public function test_source_prefills_frankfurter_when_no_czk(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $eur->id)
            ->assertSet('form.source', FxSource::FRANKFURTER->value);
    }

    public function test_create_pair(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->set('form.source', FxSource::CNB->value)
            ->set('form.isActive', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currency_pairs', [
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id, 'source' => 'cnb',
        ]);
    }

    public function test_duplicate_pair_is_rejected(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create(['base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencyPairs::class)
            ->call('create')
            ->set('form.baseCurrencyId', $usd->id)
            ->set('form.quoteCurrencyId', $czk->id)
            ->set('form.source', FxSource::CNB->value)
            ->call('save')
            ->assertHasErrors(['form.baseCurrencyId']);
    }
}
```

- [ ] **Step 2: Confirm failure. Scaffold** `livewire:form CurrencyPairForm`, `make:livewire ManageCurrencyPairs --class`.

- [ ] **Step 3: Write the form** — props `?string $id`, `?string $baseCurrencyId`, `?string $quoteCurrencyId`, `?string $source`, `bool $isActive=true`, `?string $note`. `rules()`:
```php
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'baseCurrencyId' => [
                'required', 'exists:currencies,id', 'different:quoteCurrencyId',
                Rule::unique('currency_pairs', 'base_currency_id')
                    ->where('quote_currency_id', $this->quoteCurrencyId)
                    ->ignore($this->id),
            ],
            'quoteCurrencyId' => ['required', 'exists:currencies,id'],
            'source' => ['required', Rule::enum(FxSource::class)],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
```
`setPair(CurrencyPairData $d)` fills props. `toAttributes()` maps to `base_currency_id`, `quote_currency_id`, `source`, `is_active`, `note`.

- [ ] **Step 4: Write the component** — mirror `ManageAccounts` with `CurrencyPairForm`/`CurrencyPairRepositoryInterface`. Add a Livewire updated hook that applies the source prefill rule when either currency changes:
```php
    public function updatedFormBaseCurrencyId(): void
    {
        $this->applySourceRule();
    }

    public function updatedFormQuoteCurrencyId(): void
    {
        $this->applySourceRule();
    }

    private function applySourceRule(): void
    {
        $currencies = app(CurrencyRepositoryInterface::class)->all()->keyBy('id');
        $baseCode = $currencies->get($this->form->baseCurrencyId)?->code;
        $quoteCode = $currencies->get($this->form->quoteCurrencyId)?->code;

        if ($baseCode === null || $quoteCode === null) {
            return;
        }

        $this->form->source = ($baseCode === 'CZK' || $quoteCode === 'CZK')
            ? FxSource::CNB->value
            : FxSource::FRANKFURTER->value;
    }
```
(`render()` provides `currencies` for the two selects and `sources` = `FxSource::cases()`.) Flash "Currency pair saved."

- [ ] **Step 5: Write the view** — mirror the account view: columns Base / Quote / Source / Active; modal with base & quote currency selects (`$c->id`/`$c->code`), source select over `$sources` using `$source->label()`, active checkbox, note. `@js` ids, `cancel()`. Show `$pair->baseCurrencyCode`, `$pair->quoteCurrencyCode`, `$pair->source->label()`.

- [ ] **Step 6: Route `/currency-pairs` (auth) + nav + run `--filter=ManageCurrencyPairsTest` → PASS. Commit** `feat: add Currency Pairs CRUD Livewire screen with source auto-prefill`.

---

## Task 8: Static analysis, style, full-suite + route smoke

- [ ] **Step 1: PHPStan** → `[OK]`. Fix inline (paginator generics, form property types). No blanket ignores.
- [ ] **Step 2: Pint** `pint` then `--test` → clean (test methods snake_case).
- [ ] **Step 3: Full suite** → all pass, pristine.
- [ ] **Step 4: Route smoke** — `route:list` shows `accounts`, `liabilities`, `currency-pairs`; `npm run build` succeeds.
- [ ] **Step 5: Commit** (only if 1/2 changed files) `chore: satisfy phpstan/pint for accounts/liabilities/currency-pairs CRUD`.

---

## Self-Review

**Spec coverage (spec §6.1 generic CRUD — completes it):**
- Accounts, Liabilities, Currency Pairs CRUD (table + modal, sort + pagination) via repo+DTO → Tasks 2–7 ✅
- FK dropdowns from repositories; enum selects via `label()`; dates via `<input type="date">` → Tasks 3, 5, 7 ✅
- Currency-pair `source` auto-prefill (CZK → cnb, else frankfurter), editable → Task 7 ✅
- Duplicate-pair rejected at the form (unique on base+quote) → Task 7 ✅
- Auth-gated + nav links for all three → Tasks 3, 5, 7 ✅
- **Remaining after this plan:** transactions (priority UX) + liability_payments + snapshots + in-app dashboard widgets (next plan); CSV import (final plan).

**Placeholder scan:** Tasks 2, 3, 7 give complete code for the novel parts (DTOs, forms, repositories, the prefill hook, all tests). Tasks 4–6 specify field lists + mirror an existing, committed reference file (`AccountRepository`/`ManageAccounts`) and give complete code for the non-mechanical parts (LiabilityData, rules, DTO note) — this points to real in-repo code, not other plan text, so an implementer has an exact template. Not placeholders.

**Type consistency:** repository CRUD signatures identical across Account/Liability/CurrencyPair and the established Institution/Currency repos; DTOs denormalize `institutionName`/`currencyCode` (Account/Liability) and `baseCurrencyCode`/`quoteCurrencyCode` (CurrencyPair) via eager load; forms map camelCase props → snake_case DB attributes in `toAttributes()`; dates round-trip via `toDateString()` (form) / `toImmutable()` (DTO). `source` prefill uses `CurrencyRepositoryInterface::all()` keyed by id. ✅

**Notes for the implementer:** read the committed Institution/Currency slice as the working template; keep `@js()` ids, `cancel()`, `label()`, snake_case test methods, and the `SORTABLE` orderBy guard. The currency-pair unique rule scopes `base_currency_id` uniqueness by `quote_currency_id` and ignores self on edit — verify the duplicate test goes red without it.
```
