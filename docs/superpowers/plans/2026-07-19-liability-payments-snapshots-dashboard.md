# Liability Payments + Balance Snapshots + Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the three remaining write-layer screens from the design spec — `liability_payments` (§6.3: pick liability → see context → record a payment), `account_balance_snapshots` (§6.4: pick account → balance → date, idempotent by account+date), and the minimalist post-login dashboard (§6.6: account count, last N transactions, active liabilities with their last payment date) — leaving only CSV import (§6.5) for the final plan.

**Architecture:** Same layered pattern as the committed CRUD/transactions screens — a `{Entity}Data` DTO (denormalizing related names/currency for display), a `final readonly {Entity}Repository` returning DTOs, a `Livewire\Form`, and a full-page `#[Layout('layouts.app')]` Livewire component. The payments and snapshots screens follow the **transactions** shape (entry card that stays on-page for rapid repeated entry + a recent table below), not the modal-CRUD shape. Two shared repository additions feed the selects/dashboard: `LiabilityRepository::active()` and `AccountRepository::active()`/`count()`. The dashboard is a plain embedded Livewire widget (`<livewire:dashboard-summary />`), mirroring how `fx-sync-button` is already embedded in `dashboard.blade.php` — no charts (Grafana owns statistics).

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4.3, Breeze, Spatie Data 4, PostgreSQL, bcmath, PHPUnit, PHPStan (Larastan) level 6, Pint. All commands inside the container via `./vendor/bin/sail` (ignore `WWWUSER/WWWGROUP` warnings).

## Global Constraints

- **`declare(strict_types=1);`** every PHP file; type everything.
- **Mirror the established refined pattern** (reference committed files: `app/Repositories/LiabilityRepository.php`, `app/Livewire/ManageTransactions.php`, `app/Livewire/Forms/TransactionForm.php`, `resources/views/livewire/manage-transactions.blade.php`, `resources/views/livewire/manage-liabilities.blade.php`): `final readonly` repos returning DTOs; eager-load relations in every DTO-returning read; `delete()` idempotent; components `#[Layout('layouts.app')]`, repos METHOD-injected into `render()`/actions; row ids via `@js($id)`; forms `Livewire\Form` with `rules()`/`toAttributes()`; nullable props `?string`; DTOs `final class ... extends Data` with single-line empty constructor body `) {}` and a static `fromModel()`.
- **Money:** amounts are decimal strings (`decimal:10`). Never float. No FX conversion on these two screens (payments are in the liability's currency, snapshots in the account's currency; live CZK preview is a transactions-only feature per spec §6.2).
- **Dumb-app principle (spec §1):** the app records data; statistics/derivations belong to Grafana. The payments context panel therefore surfaces the liability's **raw** `monthly_payment` / `end_date` plus the recorded last-payment and payment count — it does **not** compute remaining-installment estimates.
- **Reuse existing code unchanged unless noted:** models + factories + migrations for `liability_payments` and `account_balance_snapshots` already exist (Plan 1); `TransactionRepositoryInterface::recent()`, `InstitutionRepositoryInterface::all()`, `LiabilityData`, `AccountData` are reused as-is.
- **Tests:** `#[CoversClass]` via `use` import; **snake_case test method names** (repo Pint convention); `Livewire::actingAs(User::factory()->create())->test(...)`; assert real DB effects; `===` comparisons. **Run `./vendor/bin/sail php ./vendor/bin/pint` before the final commit of each task** (enforces `?T` nullable syntax, single-line empty bodies, ordered imports, snake_case test methods — do not skip it).
- **Baseline stays green:** phpstan `[OK]`, pint `--test` clean, full suite passing.
- **Existing model API (Plan 1):**
  - `LiabilityPayment(liability_id, payment_date:date, total_amount:decimal:10 string, principal_portion?:string, interest_portion?:string, note?; belongsTo liability)`; `Liability belongsTo institution/currency, hasMany payments`.
  - `AccountBalanceSnapshot(account_id, balance:decimal:10 string, snapshot_date:date, note?; belongsTo account)`; unique `(account_id, snapshot_date)`.
  - `Account belongsTo institution/currency`.

## File Structure

```
app/Data/LiabilityPaymentData.php                                        # new DTO
app/Data/AccountBalanceSnapshotData.php                                  # new DTO
app/Repositories/LiabilityPaymentRepositoryInterface.php + ...Repository.php   # new
app/Repositories/AccountBalanceSnapshotRepositoryInterface.php + ...Repository.php  # new
app/Repositories/LiabilityRepositoryInterface.php + LiabilityRepository.php    # add active()
app/Repositories/AccountRepositoryInterface.php + AccountRepository.php        # add active() + count()
app/Livewire/Forms/LiabilityPaymentForm.php                             # new
app/Livewire/Forms/AccountBalanceSnapshotForm.php                       # new
app/Livewire/ManageLiabilityPayments.php + resources/views/livewire/manage-liability-payments.blade.php
app/Livewire/ManageAccountBalanceSnapshots.php + resources/views/livewire/manage-account-balance-snapshots.blade.php
app/Livewire/DashboardSummary.php + resources/views/livewire/dashboard-summary.blade.php   # new widget
resources/views/dashboard.blade.php                                     # embed <livewire:dashboard-summary />
app/Providers/RepositoryServiceProvider.php                            # bind 2 new repos
routes/web.php + resources/views/layouts/navigation.blade.php          # 2 new routes + nav links
tests/Unit/Repositories/LiabilityPaymentRepositoryTest.php
tests/Unit/Repositories/LiabilityRepositoryActiveTest.php
tests/Unit/Repositories/AccountBalanceSnapshotRepositoryTest.php
tests/Unit/Repositories/AccountRepositoryActiveCountTest.php
tests/Feature/Livewire/ManageLiabilityPaymentsTest.php
tests/Feature/Livewire/ManageAccountBalanceSnapshotsTest.php
tests/Feature/Livewire/DashboardSummaryTest.php
```

---

## Task 1: LiabilityPayment DTO + repository, Liability `active()`

**Files:**
- Create: `app/Data/LiabilityPaymentData.php`, `app/Repositories/LiabilityPaymentRepositoryInterface.php`, `app/Repositories/LiabilityPaymentRepository.php`
- Modify: `app/Repositories/LiabilityRepositoryInterface.php`, `app/Repositories/LiabilityRepository.php`, `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/LiabilityPaymentRepositoryTest.php`, `tests/Unit/Repositories/LiabilityRepositoryActiveTest.php`

**Interfaces:**
- Produces:
  - `LiabilityPaymentData(string $id, string $liabilityId, string $liabilityName, string $currencyCode, CarbonImmutable $paymentDate, string $totalAmount, ?string $principalPortion, ?string $interestPortion, ?string $note)`.
  - `LiabilityPaymentRepositoryInterface`: `recentForLiability(string $liabilityId, int $limit): Collection<int,LiabilityPaymentData>`, `countForLiability(string $liabilityId): int`, `latestDateByLiability(): Collection<string,string>` (liability_id ⇒ `Y-m-d` of the newest payment), `find(string $id): ?LiabilityPaymentData`, `create(array): LiabilityPaymentData`, `update(string $id, array): LiabilityPaymentData`, `delete(string $id): void`.
  - `LiabilityRepositoryInterface::active(): Collection<int,LiabilityData>` (active liabilities, ordered by name, `institution`+`currency` eager-loaded).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Repositories/LiabilityPaymentRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityPaymentData;
use App\Models\Currency;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\LiabilityPaymentRepository::class)]
class LiabilityPaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): LiabilityPaymentRepositoryInterface
    {
        return $this->app->make(LiabilityPaymentRepositoryInterface::class);
    }

    public function test_create_returns_denormalized_data(): void
    {
        $currency = Currency::factory()->create(['code' => 'EUR']);
        $liability = Liability::factory()->create(['name' => 'Mortgage', 'currency_id' => $currency->id]);

        $data = $this->repository()->create([
            'liability_id' => $liability->id,
            'payment_date' => '2026-03-15',
            'total_amount' => '12500.0000000000',
            'principal_portion' => '10000.0000000000',
            'interest_portion' => '2500.0000000000',
            'note' => 'March',
        ]);

        $this->assertInstanceOf(LiabilityPaymentData::class, $data);
        $this->assertSame('Mortgage', $data->liabilityName);
        $this->assertSame('EUR', $data->currencyCode);
        $this->assertSame('12500.0000000000', $data->totalAmount);
        $this->assertDatabaseHas('liability_payments', ['liability_id' => $liability->id, 'note' => 'March']);
    }

    public function test_recent_for_liability_newest_first_limited(): void
    {
        $liability = Liability::factory()->create();
        $other = Liability::factory()->create();
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-01-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-03-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-02-01']);
        LiabilityPayment::factory()->create(['liability_id' => $other->id, 'payment_date' => '2026-04-01']);

        $recent = $this->repository()->recentForLiability($liability->id, 2);

        $this->assertCount(2, $recent);
        $this->assertContainsOnlyInstancesOf(LiabilityPaymentData::class, $recent);
        $this->assertSame('2026-03-01', $recent->first()->paymentDate->toDateString());
    }

    public function test_count_for_liability(): void
    {
        $liability = Liability::factory()->create();
        LiabilityPayment::factory()->count(3)->create(['liability_id' => $liability->id]);
        LiabilityPayment::factory()->create(); // different liability

        $this->assertSame(3, $this->repository()->countForLiability($liability->id));
    }

    public function test_latest_date_by_liability(): void
    {
        $liability = Liability::factory()->create();
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-01-01']);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-05-01']);

        $map = $this->repository()->latestDateByLiability();

        $this->assertSame('2026-05-01', $map->get($liability->id));
    }

    public function test_update_and_delete(): void
    {
        $payment = LiabilityPayment::factory()->create(['total_amount' => '100.0000000000']);

        $updated = $this->repository()->update($payment->id, [
            'liability_id' => $payment->liability_id,
            'payment_date' => $payment->payment_date->toDateString(),
            'total_amount' => '200.0000000000',
            'principal_portion' => null,
            'interest_portion' => null,
            'note' => 'edited',
        ]);
        $this->assertSame('200.0000000000', $updated->totalAmount);

        $this->repository()->delete($payment->id);
        $this->assertNull($this->repository()->find($payment->id));
    }
}
```

Create `tests/Unit/Repositories/LiabilityRepositoryActiveTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\LiabilityData;
use App\Models\Liability;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\LiabilityRepository::class)]
class LiabilityRepositoryActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_returns_only_active_liabilities_ordered_by_name(): void
    {
        Liability::factory()->create(['name' => 'Zeta loan', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Alpha loan', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Closed loan', 'is_active' => false]);

        $result = $this->app->make(LiabilityRepositoryInterface::class)->active();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(LiabilityData::class, $result);
        $this->assertSame('Alpha loan', $result->first()->name);
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=LiabilityPaymentRepositoryTest` and `--filter=LiabilityRepositoryActiveTest` → FAIL (missing interface/method).

- [ ] **Step 3: Create `LiabilityPaymentData`**

Create `app/Data/LiabilityPaymentData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\LiabilityPayment;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class LiabilityPaymentData extends Data
{
    public function __construct(
        public string $id,
        public string $liabilityId,
        public string $liabilityName,
        public string $currencyCode,
        public CarbonImmutable $paymentDate,
        public string $totalAmount,
        public ?string $principalPortion,
        public ?string $interestPortion,
        public ?string $note,
    ) {}

    public static function fromModel(LiabilityPayment $payment): self
    {
        return new self(
            id: $payment->id,
            liabilityId: $payment->liability_id,
            liabilityName: $payment->liability->name,
            currencyCode: $payment->liability->currency->code,
            paymentDate: $payment->payment_date->toImmutable(),
            totalAmount: $payment->total_amount,
            principalPortion: $payment->principal_portion,
            interestPortion: $payment->interest_portion,
            note: $payment->note,
        );
    }
}
```

- [ ] **Step 4: Create the repository interface + impl**

Create `app/Repositories/LiabilityPaymentRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityPaymentData;
use Illuminate\Support\Collection;

interface LiabilityPaymentRepositoryInterface
{
    /** @return Collection<int, LiabilityPaymentData> */
    public function recentForLiability(string $liabilityId, int $limit): Collection;

    public function countForLiability(string $liabilityId): int;

    /** @return Collection<string, string> */
    public function latestDateByLiability(): Collection;

    public function find(string $id): ?LiabilityPaymentData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityPaymentData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityPaymentData;

    public function delete(string $id): void;
}
```

Create `app/Repositories/LiabilityPaymentRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityPaymentData;
use App\Models\LiabilityPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class LiabilityPaymentRepository implements LiabilityPaymentRepositoryInterface
{
    private const array WITH = ['liability.currency'];

    /** @return Collection<int, LiabilityPaymentData> */
    public function recentForLiability(string $liabilityId, int $limit): Collection
    {
        return LiabilityPayment::query()
            ->with(self::WITH)
            ->where('liability_id', $liabilityId)
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LiabilityPayment $payment): LiabilityPaymentData => LiabilityPaymentData::fromModel($payment));
    }

    public function countForLiability(string $liabilityId): int
    {
        return LiabilityPayment::query()->where('liability_id', $liabilityId)->count();
    }

    /** @return Collection<string, string> */
    public function latestDateByLiability(): Collection
    {
        return LiabilityPayment::query()
            ->selectRaw('liability_id, max(payment_date) as latest')
            ->groupBy('liability_id')
            ->pluck('latest', 'liability_id')
            ->map(fn (mixed $date): string => CarbonImmutable::parse((string) $date)->toDateString());
    }

    public function find(string $id): ?LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->with(self::WITH)->find($id);

        return $payment === null ? null : LiabilityPaymentData::fromModel($payment);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->create($attributes);

        return LiabilityPaymentData::fromModel($payment->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->findOrFail($id);
        $payment->update($attributes);

        return LiabilityPaymentData::fromModel($payment->load(self::WITH));
    }

    public function delete(string $id): void
    {
        LiabilityPayment::query()->where('id', $id)->delete();
    }
}
```

- [ ] **Step 5: Add `active()` to the Liability repository**

In `app/Repositories/LiabilityRepositoryInterface.php` add `use Illuminate\Support\Collection;` and the method:
```php
    /** @return Collection<int, LiabilityData> */
    public function active(): Collection;
```
In `app/Repositories/LiabilityRepository.php` add `use Illuminate\Support\Collection;` and the method:
```php
    /** @return Collection<int, LiabilityData> */
    public function active(): Collection
    {
        return Liability::query()
            ->with(['institution', 'currency'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Liability $liability): LiabilityData => LiabilityData::fromModel($liability));
    }
```

- [ ] **Step 6: Bind + run + pint + commit**

Add `LiabilityPaymentRepositoryInterface::class => LiabilityPaymentRepository::class` to `RepositoryServiceProvider::$bindings` (with the two `use` imports). Run both filters → PASS. Run `./vendor/bin/sail php ./vendor/bin/pint`. Commit:
```bash
git add app/Data/LiabilityPaymentData.php app/Repositories/LiabilityPaymentRepositoryInterface.php app/Repositories/LiabilityPaymentRepository.php app/Repositories/LiabilityRepositoryInterface.php app/Repositories/LiabilityRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/LiabilityPaymentRepositoryTest.php tests/Unit/Repositories/LiabilityRepositoryActiveTest.php
git commit -m "feat: add LiabilityPayment DTO+repository and Liability active()"
```

---

## Task 2: ManageLiabilityPayments screen (select → context → form → recent table)

**Files:**
- Create: `app/Livewire/Forms/LiabilityPaymentForm.php`, `app/Livewire/ManageLiabilityPayments.php`, `resources/views/livewire/manage-liability-payments.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageLiabilityPaymentsTest.php`

**Interfaces:**
- Consumes: `LiabilityPaymentRepositoryInterface` (`recentForLiability`, `countForLiability`, `find`, `create`, `update`, `delete`), `LiabilityRepositoryInterface` (`active`, `find`).
- Produces: route `liability-payments` (auth) → `ManageLiabilityPayments`. Behaviors: liability select (active only); on selection show a context panel (currency, raw monthly payment, raw end date, last recorded payment, payment count); a payment form (date default today, total amount, optional principal/interest portions, note); save persists then keeps liability+date selected (clears amounts/portions/note) for rapid entry; a recent-payments table (for the selected liability) supports edit (loads into form) and delete.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageLiabilityPaymentsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageLiabilityPayments;
use App\Models\Currency;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageLiabilityPayments::class)]
class ManageLiabilityPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/liability-payments')->assertRedirect('/login');
    }

    public function test_only_active_liabilities_are_selectable(): void
    {
        Liability::factory()->create(['name' => 'Active Mortgage', 'is_active' => true]);
        Liability::factory()->create(['name' => 'Closed Loan', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->assertSee('Active Mortgage')
            ->assertDontSee('Closed Loan');
    }

    public function test_selecting_liability_shows_context(): void
    {
        $currency = Currency::factory()->create(['code' => 'CZK']);
        $liability = Liability::factory()->create([
            'name' => 'Flat Mortgage', 'currency_id' => $currency->id,
        ]);
        LiabilityPayment::factory()->create([
            'liability_id' => $liability->id, 'payment_date' => '2026-02-01', 'total_amount' => '9000.0000000000',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.liabilityId', $liability->id)
            ->assertSee('2026-02-01')   // last payment date in context
            ->assertSee('Payments recorded');
    }

    public function test_create_payment_and_keep_liability_selected(): void
    {
        $liability = Liability::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.liabilityId', $liability->id)
            ->set('form.totalAmount', '5000')
            ->set('form.paymentDate', '2026-03-15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('liability_payments', ['liability_id' => $liability->id, 'total_amount' => '5000.0000000000']);
        $component->assertSet('form.liabilityId', $liability->id)
            ->assertSet('form.totalAmount', null);
    }

    public function test_validation_requires_liability_amount_date(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->set('form.paymentDate', null)
            ->call('save')
            ->assertHasErrors(['form.liabilityId', 'form.totalAmount', 'form.paymentDate']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $payment = LiabilityPayment::factory()->create(['total_amount' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->call('edit', $payment->id)
            ->assertSet('form.liabilityId', $payment->liability_id)
            ->set('form.totalAmount', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('liability_payments', ['id' => $payment->id, 'total_amount' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageLiabilityPayments::class)
            ->call('delete', $payment->id);
        $this->assertDatabaseMissing('liability_payments', ['id' => $payment->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ManageLiabilityPaymentsTest` → FAIL.

- [ ] **Step 3: Scaffold** — `./vendor/bin/sail artisan livewire:form LiabilityPaymentForm` and `./vendor/bin/sail artisan make:livewire ManageLiabilityPayments --class`, then replace the generated stubs per below.

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/LiabilityPaymentForm.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\LiabilityPaymentData;
use Livewire\Form;

class LiabilityPaymentForm extends Form
{
    public ?string $id = null;

    public ?string $liabilityId = null;

    public ?string $paymentDate = null;

    public ?string $totalAmount = null;

    public ?string $principalPortion = null;

    public ?string $interestPortion = null;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'liabilityId' => ['required', 'exists:liabilities,id'],
            'paymentDate' => ['required', 'date'],
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'principalPortion' => ['nullable', 'numeric', 'min:0'],
            'interestPortion' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setPayment(LiabilityPaymentData $data): void
    {
        $this->id = $data->id;
        $this->liabilityId = $data->liabilityId;
        $this->paymentDate = $data->paymentDate->toDateString();
        $this->totalAmount = $data->totalAmount;
        $this->principalPortion = $data->principalPortion;
        $this->interestPortion = $data->interestPortion;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'liability_id' => $this->liabilityId,
            'payment_date' => $this->paymentDate,
            'total_amount' => $this->totalAmount,
            'principal_portion' => $this->principalPortion,
            'interest_portion' => $this->interestPortion,
            'note' => $this->note,
        ];
    }
}
```

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageLiabilityPayments.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\LiabilityData;
use App\Livewire\Forms\LiabilityPaymentForm;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageLiabilityPayments extends Component
{
    public LiabilityPaymentForm $form;

    public function mount(): void
    {
        $this->form->paymentDate = CarbonImmutable::now()->toDateString();
    }

    public function edit(string $id, LiabilityPaymentRepositoryInterface $payments): void
    {
        $data = $payments->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setPayment($data);
    }

    public function save(LiabilityPaymentRepositoryInterface $payments): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $payments->create($this->form->toAttributes());
        } else {
            $payments->update($this->form->id, $this->form->toAttributes());
        }

        // Keep liability/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->totalAmount = null;
        $this->form->principalPortion = null;
        $this->form->interestPortion = null;
        $this->form->note = null;

        session()->flash('status', 'Payment saved.');
    }

    public function delete(string $id, LiabilityPaymentRepositoryInterface $payments): void
    {
        $payments->delete($id);
    }

    public function render(
        LiabilityRepositoryInterface $liabilities,
        LiabilityPaymentRepositoryInterface $payments,
    ): View {
        $selectedLiability = $this->form->liabilityId === null
            ? null
            : $liabilities->find($this->form->liabilityId);

        /** @var Collection<int, \App\Data\LiabilityPaymentData> $recent */
        $recent = $this->form->liabilityId === null
            ? new Collection
            : $payments->recentForLiability($this->form->liabilityId, 15);

        $paymentCount = $this->form->liabilityId === null
            ? 0
            : $payments->countForLiability($this->form->liabilityId);

        return view('livewire.manage-liability-payments', [
            'liabilities' => $liabilities->active(),
            'selectedLiability' => $selectedLiability,
            'lastPayment' => $recent->first(),
            'paymentCount' => $paymentCount,
            'recent' => $recent,
        ]);
    }
}
```
(`$selectedLiability` is a `?LiabilityData`; `$lastPayment` is a `?LiabilityPaymentData` — the newest of `recent`, reused so the context panel costs no extra query.)

- [ ] **Step 6: Write the view**

Create `resources/views/livewire/manage-liability-payments.blade.php`, mirroring the Breeze styling of `resources/views/livewire/manage-transactions.blade.php` (same outer `<div class="py-8">` / `max-w-5xl` wrapper, `<form wire:submit="save">` card, `<x-input-label>` / `<x-text-input>` / `<x-input-error>` / `<x-primary-button>`, and `session('status')` green flash). Required elements:
- Heading `Liability payments`.
- Liability `<select wire:model.live="form.liabilityId">` over `$liabilities` (`$l->id` / `$l->name`), blank first option `—`; `<x-input-error :messages="$errors->get('form.liabilityId')" />`.
- **Context panel** — only `@if ($selectedLiability !== null)`: show `{{ $selectedLiability->currencyCode }}`, `Monthly payment: {{ $selectedLiability->monthlyPayment ?? '—' }}`, `End date: {{ $selectedLiability->endDate?->toDateString() ?? '—' }}`, `Payments recorded: {{ $paymentCount }}`, and `@if ($lastPayment !== null) Last payment: {{ $lastPayment->paymentDate->toDateString() }} — {{ $lastPayment->totalAmount }} {{ $lastPayment->currencyCode }} @else No payments yet @endif`.
- Date `<input type="date" wire:model="form.paymentDate">`; error.
- Total amount `<x-text-input type="text" inputmode="decimal" wire:model="form.totalAmount">` (with `{{ $selectedLiability?->currencyCode }}` suffix badge when a liability is selected); error.
- Principal portion, Interest portion (`<x-text-input inputmode="decimal">`, optional); errors.
- Note `<textarea wire:model="form.note">`; error.
- Submit `<x-primary-button>{{ $form->id === null ? 'Save' : 'Update' }}</x-primary-button>`.
- Below: `Recent payments` table (same table styling as manage-transactions). Columns Date / Total / Principal / Interest / Note / actions. Iterate `$recent` with `wire:key="payment-{{ $p->id }}"`; show `$p->paymentDate->toDateString()`, `{{ $p->totalAmount }} {{ $p->currencyCode }}`, `$p->principalPortion`, `$p->interestPortion`, `$p->note`; Edit `wire:click="edit(@js($p->id))"`; Delete `wire:click="delete(@js($p->id))" wire:confirm="Delete this payment?"`. (When no liability selected, `$recent` is empty and the table body renders no rows.)

- [ ] **Step 7: Route + nav + run + pint + commit**

Add to `routes/web.php` — import `use App\Livewire\ManageLiabilityPayments;` and, in the `auth` group, `Route::get('/liability-payments', ManageLiabilityPayments::class)->name('liability-payments');` (place directly after the `liabilities` route). In `resources/views/layouts/navigation.blade.php` add a desktop `<x-nav-link :href="route('liability-payments')" :active="request()->routeIs('liability-payments')">{{ __('Liability Payments') }}</x-nav-link>` and the matching `<x-responsive-nav-link>` (place both directly after the `liabilities` links). Run `--filter=ManageLiabilityPaymentsTest` → PASS. `pint`. Commit:
```bash
git add app/Livewire/Forms/LiabilityPaymentForm.php app/Livewire/ManageLiabilityPayments.php resources/views/livewire/manage-liability-payments.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ManageLiabilityPaymentsTest.php
git commit -m "feat: add liability payments entry screen (select, context, recent table)"
```

---

## Task 3: AccountBalanceSnapshot DTO + repository (upsert), Account `active()`/`count()`

**Files:**
- Create: `app/Data/AccountBalanceSnapshotData.php`, `app/Repositories/AccountBalanceSnapshotRepositoryInterface.php`, `app/Repositories/AccountBalanceSnapshotRepository.php`
- Modify: `app/Repositories/AccountRepositoryInterface.php`, `app/Repositories/AccountRepository.php`, `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/AccountBalanceSnapshotRepositoryTest.php`, `tests/Unit/Repositories/AccountRepositoryActiveCountTest.php`

**Interfaces:**
- Produces:
  - `AccountBalanceSnapshotData(string $id, string $accountId, string $accountName, string $institutionName, string $currencyCode, string $balance, CarbonImmutable $snapshotDate, ?string $note)`.
  - `AccountBalanceSnapshotRepositoryInterface`: `recent(int $limit): Collection<int,AccountBalanceSnapshotData>`, `find(string $id): ?AccountBalanceSnapshotData`, `upsert(array): AccountBalanceSnapshotData` (updateOrCreate on `(account_id, snapshot_date)` — idempotent per spec §6.5), `update(string $id, array): AccountBalanceSnapshotData`, `delete(string $id): void`.
  - `AccountRepositoryInterface::active(): Collection<int,AccountData>` (active accounts, ordered by name, `institution`+`currency` eager-loaded), `AccountRepositoryInterface::count(): int` (total account rows).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Repositories/AccountBalanceSnapshotRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountBalanceSnapshotData;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Currency;
use App\Models\Institution;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\AccountBalanceSnapshotRepository::class)]
class AccountBalanceSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): AccountBalanceSnapshotRepositoryInterface
    {
        return $this->app->make(AccountBalanceSnapshotRepositoryInterface::class);
    }

    public function test_upsert_returns_denormalized_data(): void
    {
        $institution = Institution::factory()->create(['name' => 'Degiro']);
        $currency = Currency::factory()->create(['code' => 'USD']);
        $account = Account::factory()->create([
            'institution_id' => $institution->id, 'currency_id' => $currency->id, 'name' => 'Broker USD',
        ]);

        $data = $this->repository()->upsert([
            'account_id' => $account->id,
            'balance' => '15000.0000000000',
            'snapshot_date' => '2026-03-31',
            'note' => 'Q1',
        ]);

        $this->assertInstanceOf(AccountBalanceSnapshotData::class, $data);
        $this->assertSame('Broker USD', $data->accountName);
        $this->assertSame('Degiro', $data->institutionName);
        $this->assertSame('USD', $data->currencyCode);
        $this->assertSame('15000.0000000000', $data->balance);
    }

    public function test_upsert_is_idempotent_on_account_and_date(): void
    {
        $account = Account::factory()->create();

        $this->repository()->upsert([
            'account_id' => $account->id, 'balance' => '100.0000000000', 'snapshot_date' => '2026-03-31', 'note' => null,
        ]);
        $this->repository()->upsert([
            'account_id' => $account->id, 'balance' => '250.0000000000', 'snapshot_date' => '2026-03-31', 'note' => null,
        ]);

        $this->assertSame(1, AccountBalanceSnapshot::query()->count());
        $this->assertDatabaseHas('account_balance_snapshots', [
            'account_id' => $account->id, 'snapshot_date' => '2026-03-31', 'balance' => '250.0000000000',
        ]);
    }

    public function test_recent_returns_newest_first_limited(): void
    {
        $account = Account::factory()->create();
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-01-31']);
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-03-31']);
        AccountBalanceSnapshot::factory()->create(['account_id' => $account->id, 'snapshot_date' => '2026-02-28']);

        $recent = $this->repository()->recent(2);

        $this->assertCount(2, $recent);
        $this->assertContainsOnlyInstancesOf(AccountBalanceSnapshotData::class, $recent);
        $this->assertSame('2026-03-31', $recent->first()->snapshotDate->toDateString());
    }

    public function test_update_and_delete(): void
    {
        $snapshot = AccountBalanceSnapshot::factory()->create(['balance' => '10.0000000000']);

        $updated = $this->repository()->update($snapshot->id, [
            'account_id' => $snapshot->account_id,
            'balance' => '20.0000000000',
            'snapshot_date' => $snapshot->snapshot_date->toDateString(),
            'note' => 'edited',
        ]);
        $this->assertSame('20.0000000000', $updated->balance);

        $this->repository()->delete($snapshot->id);
        $this->assertNull($this->repository()->find($snapshot->id));
    }
}
```

Create `tests/Unit/Repositories/AccountRepositoryActiveCountTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use App\Repositories\AccountRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\AccountRepository::class)]
class AccountRepositoryActiveCountTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): AccountRepositoryInterface
    {
        return $this->app->make(AccountRepositoryInterface::class);
    }

    public function test_active_returns_only_active_accounts_ordered_by_name(): void
    {
        Account::factory()->create(['name' => 'Zeta', 'is_active' => true]);
        Account::factory()->create(['name' => 'Alpha', 'is_active' => true]);
        Account::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $result = $this->repository()->active();

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(AccountData::class, $result);
        $this->assertSame('Alpha', $result->first()->name);
    }

    public function test_count_returns_total_accounts(): void
    {
        Account::factory()->count(3)->create();

        $this->assertSame(3, $this->repository()->count());
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=AccountBalanceSnapshotRepositoryTest` and `--filter=AccountRepositoryActiveCountTest` → FAIL.

- [ ] **Step 3: Create `AccountBalanceSnapshotData`**

Create `app/Data/AccountBalanceSnapshotData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\AccountBalanceSnapshot;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class AccountBalanceSnapshotData extends Data
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $accountName,
        public string $institutionName,
        public string $currencyCode,
        public string $balance,
        public CarbonImmutable $snapshotDate,
        public ?string $note,
    ) {}

    public static function fromModel(AccountBalanceSnapshot $snapshot): self
    {
        return new self(
            id: $snapshot->id,
            accountId: $snapshot->account_id,
            accountName: $snapshot->account->name,
            institutionName: $snapshot->account->institution->name,
            currencyCode: $snapshot->account->currency->code,
            balance: $snapshot->balance,
            snapshotDate: $snapshot->snapshot_date->toImmutable(),
            note: $snapshot->note,
        );
    }
}
```

- [ ] **Step 4: Create the repository interface + impl**

Create `app/Repositories/AccountBalanceSnapshotRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountBalanceSnapshotData;
use Illuminate\Support\Collection;

interface AccountBalanceSnapshotRepositoryInterface
{
    /** @return Collection<int, AccountBalanceSnapshotData> */
    public function recent(int $limit): Collection;

    public function find(string $id): ?AccountBalanceSnapshotData;

    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): AccountBalanceSnapshotData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountBalanceSnapshotData;

    public function delete(string $id): void;
}
```

Create `app/Repositories/AccountBalanceSnapshotRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountBalanceSnapshotData;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Support\Collection;

final readonly class AccountBalanceSnapshotRepository implements AccountBalanceSnapshotRepositoryInterface
{
    private const array WITH = ['account.institution', 'account.currency'];

    /** @return Collection<int, AccountBalanceSnapshotData> */
    public function recent(int $limit): Collection
    {
        return AccountBalanceSnapshot::query()
            ->with(self::WITH)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AccountBalanceSnapshot $snapshot): AccountBalanceSnapshotData => AccountBalanceSnapshotData::fromModel($snapshot));
    }

    public function find(string $id): ?AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->with(self::WITH)->find($id);

        return $snapshot === null ? null : AccountBalanceSnapshotData::fromModel($snapshot);
    }

    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->updateOrCreate(
            ['account_id' => $attributes['account_id'], 'snapshot_date' => $attributes['snapshot_date']],
            ['balance' => $attributes['balance'], 'note' => $attributes['note']],
        );

        return AccountBalanceSnapshotData::fromModel($snapshot->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->findOrFail($id);
        $snapshot->update($attributes);

        return AccountBalanceSnapshotData::fromModel($snapshot->load(self::WITH));
    }

    public function delete(string $id): void
    {
        AccountBalanceSnapshot::query()->where('id', $id)->delete();
    }
}
```

- [ ] **Step 5: Add `active()` + `count()` to the Account repository**

In `app/Repositories/AccountRepositoryInterface.php` add (Collection is already imported):
```php
    /** @return Collection<int, AccountData> */
    public function active(): Collection;

    public function count(): int;
```
In `app/Repositories/AccountRepository.php` add:
```php
    /** @return Collection<int, AccountData> */
    public function active(): Collection
    {
        return Account::query()
            ->with(['institution', 'currency'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): AccountData => AccountData::fromModel($account));
    }

    public function count(): int
    {
        return Account::query()->count();
    }
```

- [ ] **Step 6: Bind + run + pint + commit**

Add `AccountBalanceSnapshotRepositoryInterface::class => AccountBalanceSnapshotRepository::class` to `RepositoryServiceProvider::$bindings` (with the two `use` imports). Run both filters → PASS. `pint`. Commit:
```bash
git add app/Data/AccountBalanceSnapshotData.php app/Repositories/AccountBalanceSnapshotRepositoryInterface.php app/Repositories/AccountBalanceSnapshotRepository.php app/Repositories/AccountRepositoryInterface.php app/Repositories/AccountRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/AccountBalanceSnapshotRepositoryTest.php tests/Unit/Repositories/AccountRepositoryActiveCountTest.php
git commit -m "feat: add AccountBalanceSnapshot DTO+repository (upsert) and Account active()/count()"
```

---

## Task 4: ManageAccountBalanceSnapshots screen (account → balance → date, idempotent)

**Files:**
- Create: `app/Livewire/Forms/AccountBalanceSnapshotForm.php`, `app/Livewire/ManageAccountBalanceSnapshots.php`, `resources/views/livewire/manage-account-balance-snapshots.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageAccountBalanceSnapshotsTest.php`

**Interfaces:**
- Consumes: `AccountBalanceSnapshotRepositoryInterface` (`recent`, `find`, `upsert`, `update`, `delete`), `AccountRepositoryInterface` (`active`, `find`).
- Produces: route `account-snapshots` (auth) → `ManageAccountBalanceSnapshots`. Behaviors: account select (active only) with a readonly currency badge; balance + date (default today); save via `upsert` for new entries (idempotent on account+date) or `update` when editing an existing row; keeps account+date selected (clears balance/note) for rapid entry; a recent-snapshots table supports edit (loads into form) and delete.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageAccountBalanceSnapshotsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageAccountBalanceSnapshots;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageAccountBalanceSnapshots::class)]
class ManageAccountBalanceSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_route(): void
    {
        $this->get('/account-snapshots')->assertRedirect('/login');
    }

    public function test_only_active_accounts_are_selectable(): void
    {
        Account::factory()->create(['name' => 'Active Broker', 'is_active' => true]);
        Account::factory()->create(['name' => 'Closed Account', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->assertSee('Active Broker')
            ->assertDontSee('Closed Account');
    }

    public function test_selecting_account_shows_currency_badge(): void
    {
        $currency = Currency::factory()->create(['code' => 'GBP']);
        $account = Account::factory()->create(['currency_id' => $currency->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)
            ->assertSee('GBP');
    }

    public function test_create_snapshot_and_keep_account_selected(): void
    {
        $account = Account::factory()->create();

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)
            ->set('form.balance', '15000')
            ->set('form.snapshotDate', '2026-03-31')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('account_balance_snapshots', ['account_id' => $account->id, 'balance' => '15000.0000000000']);
        $component->assertSet('form.accountId', $account->id)
            ->assertSet('form.balance', null);
    }

    public function test_saving_same_account_and_date_is_idempotent(): void
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)->set('form.balance', '100')->set('form.snapshotDate', '2026-03-31')
            ->call('save')->assertHasNoErrors();
        Livewire::actingAs($user)->test(ManageAccountBalanceSnapshots::class)
            ->set('form.accountId', $account->id)->set('form.balance', '250')->set('form.snapshotDate', '2026-03-31')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, AccountBalanceSnapshot::query()->count());
        $this->assertDatabaseHas('account_balance_snapshots', [
            'account_id' => $account->id, 'snapshot_date' => '2026-03-31', 'balance' => '250.0000000000',
        ]);
    }

    public function test_validation_requires_account_balance_date(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->set('form.snapshotDate', null)
            ->call('save')
            ->assertHasErrors(['form.accountId', 'form.balance', 'form.snapshotDate']);
    }

    public function test_edit_and_delete_recent(): void
    {
        $snapshot = AccountBalanceSnapshot::factory()->create(['balance' => '10.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->call('edit', $snapshot->id)
            ->assertSet('form.accountId', $snapshot->account_id)
            ->set('form.balance', '99')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('account_balance_snapshots', ['id' => $snapshot->id, 'balance' => '99.0000000000']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageAccountBalanceSnapshots::class)
            ->call('delete', $snapshot->id);
        $this->assertDatabaseMissing('account_balance_snapshots', ['id' => $snapshot->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=ManageAccountBalanceSnapshotsTest` → FAIL.

- [ ] **Step 3: Scaffold** — `./vendor/bin/sail artisan livewire:form AccountBalanceSnapshotForm` and `./vendor/bin/sail artisan make:livewire ManageAccountBalanceSnapshots --class`, then replace the generated stubs per below.

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/AccountBalanceSnapshotForm.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\AccountBalanceSnapshotData;
use Livewire\Form;

class AccountBalanceSnapshotForm extends Form
{
    public ?string $id = null;

    public ?string $accountId = null;

    public ?string $balance = null;

    public ?string $snapshotDate = null;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accountId' => ['required', 'exists:accounts,id'],
            'balance' => ['required', 'numeric'],
            'snapshotDate' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setSnapshot(AccountBalanceSnapshotData $data): void
    {
        $this->id = $data->id;
        $this->accountId = $data->accountId;
        $this->balance = $data->balance;
        $this->snapshotDate = $data->snapshotDate->toDateString();
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'account_id' => $this->accountId,
            'balance' => $this->balance,
            'snapshot_date' => $this->snapshotDate,
            'note' => $this->note,
        ];
    }
}
```

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageAccountBalanceSnapshots.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Forms\AccountBalanceSnapshotForm;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use App\Repositories\AccountRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageAccountBalanceSnapshots extends Component
{
    public AccountBalanceSnapshotForm $form;

    public function mount(): void
    {
        $this->form->snapshotDate = CarbonImmutable::now()->toDateString();
    }

    public function edit(string $id, AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $data = $snapshots->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setSnapshot($data);
    }

    public function save(AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $snapshots->upsert($this->form->toAttributes());
        } else {
            $snapshots->update($this->form->id, $this->form->toAttributes());
        }

        // Keep account/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->balance = null;
        $this->form->note = null;

        session()->flash('status', 'Snapshot saved.');
    }

    public function delete(string $id, AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $snapshots->delete($id);
    }

    public function render(
        AccountRepositoryInterface $accounts,
        AccountBalanceSnapshotRepositoryInterface $snapshots,
    ): View {
        $selectedAccount = $this->form->accountId === null ? null : $accounts->find($this->form->accountId);

        return view('livewire.manage-account-balance-snapshots', [
            'accounts' => $accounts->active(),
            'selectedAccount' => $selectedAccount,
            'recent' => $snapshots->recent(15),
        ]);
    }
}
```

- [ ] **Step 6: Write the view**

Create `resources/views/livewire/manage-account-balance-snapshots.blade.php`, mirroring `resources/views/livewire/manage-transactions.blade.php` styling. Required elements:
- Heading `Account balance snapshots`; `session('status')` green flash.
- `<form wire:submit="save">` card:
  - Account `<select wire:model.live="form.accountId">` over `$accounts` (`$a->id` / `$a->name`), blank first option `—`; error `form.accountId`. When `$selectedAccount !== null` show a readonly currency badge `{{ $selectedAccount->currencyCode }}` (same badge markup as manage-transactions).
  - Balance `<x-text-input type="text" inputmode="decimal" wire:model="form.balance">`; error `form.balance`.
  - Date `<input type="date" wire:model="form.snapshotDate">`; error `form.snapshotDate`.
  - Note `<textarea wire:model="form.note">`; error `form.note`.
  - `<x-primary-button>{{ $form->id === null ? 'Save' : 'Update' }}</x-primary-button>`.
- `Recent snapshots` table (same styling): columns Date / Account / Balance / Note / actions. Iterate `$recent` with `wire:key="snapshot-{{ $s->id }}"`; show `$s->snapshotDate->toDateString()`, `$s->accountName` (+ `$s->institutionName` sub-text), `{{ $s->balance }} {{ $s->currencyCode }}`, `$s->note`; Edit `wire:click="edit(@js($s->id))"`; Delete `wire:click="delete(@js($s->id))" wire:confirm="Delete this snapshot?"`.

- [ ] **Step 7: Route + nav + run + pint + commit**

Add to `routes/web.php` — import `use App\Livewire\ManageAccountBalanceSnapshots;` and, in the `auth` group, `Route::get('/account-snapshots', ManageAccountBalanceSnapshots::class)->name('account-snapshots');` (place after `account-snapshots`' logical sibling — directly after the `accounts` route). Add desktop + responsive nav links labelled `{{ __('Balance Snapshots') }}` (place after the `accounts` links). Run `--filter=ManageAccountBalanceSnapshotsTest` → PASS. `pint`. Commit:
```bash
git add app/Livewire/Forms/AccountBalanceSnapshotForm.php app/Livewire/ManageAccountBalanceSnapshots.php resources/views/livewire/manage-account-balance-snapshots.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ManageAccountBalanceSnapshotsTest.php
git commit -m "feat: add account balance snapshots entry screen (upsert, recent table)"
```

---

## Task 5: Dashboard summary widget (§6.6)

**Files:**
- Create: `app/Livewire/DashboardSummary.php`, `resources/views/livewire/dashboard-summary.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Livewire/DashboardSummaryTest.php`

**Interfaces:**
- Consumes: `AccountRepositoryInterface::count()`, `TransactionRepositoryInterface::recent(int)`, `LiabilityRepositoryInterface::active()`, `LiabilityPaymentRepositoryInterface::latestDateByLiability()`.
- Produces: an embeddable `<livewire:dashboard-summary />` (no `#[Layout]` — it renders inside the existing `dashboard.blade.php`, exactly like `<livewire:fx-sync-button />`). Renders: account count, last 5 transactions, and active liabilities each with their last payment date (`—` when none). No charts.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/DashboardSummaryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\TransactionType;
use App\Livewire\DashboardSummary;
use App\Models\Account;
use App\Models\Liability;
use App\Models\LiabilityPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DashboardSummary::class)]
class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_account_count(): void
    {
        Account::factory()->count(2)->create();

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('2');
    }

    public function test_shows_recent_transactions(): void
    {
        $account = Account::factory()->create(['name' => 'Fio CZK']);
        Transaction::factory()->create([
            'account_id' => $account->id, 'type' => TransactionType::DEPOSIT, 'transaction_date' => '2026-03-15',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('Fio CZK')
            ->assertSee('2026-03-15');
    }

    public function test_shows_active_liabilities_with_last_payment_date(): void
    {
        $liability = Liability::factory()->create(['name' => 'Mortgage', 'is_active' => true]);
        LiabilityPayment::factory()->create(['liability_id' => $liability->id, 'payment_date' => '2026-05-01']);
        Liability::factory()->create(['name' => 'Closed', 'is_active' => false]);

        Livewire::actingAs(User::factory()->create())
            ->test(DashboardSummary::class)
            ->assertSee('Mortgage')
            ->assertSee('2026-05-01')
            ->assertDontSee('Closed');
    }
}
```

- [ ] **Step 2: Run to confirm failure** — `./vendor/bin/sail artisan test --filter=DashboardSummaryTest` → FAIL.

- [ ] **Step 3: Scaffold** — `./vendor/bin/sail artisan make:livewire DashboardSummary --class`, then replace the generated class per below.

- [ ] **Step 4: Write the component**

Replace `app/Livewire/DashboardSummary.php`:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardSummary extends Component
{
    public function render(
        AccountRepositoryInterface $accounts,
        TransactionRepositoryInterface $transactions,
        LiabilityRepositoryInterface $liabilities,
        LiabilityPaymentRepositoryInterface $payments,
    ): View {
        return view('livewire.dashboard-summary', [
            'accountCount' => $accounts->count(),
            'recentTransactions' => $transactions->recent(5),
            'activeLiabilities' => $liabilities->active(),
            'lastPaymentDates' => $payments->latestDateByLiability(),
        ]);
    }
}
```

- [ ] **Step 5: Write the view**

Create `resources/views/livewire/dashboard-summary.blade.php`. A single `<div>` containing three sections styled with the Breeze card look (`bg-white ... shadow-sm sm:rounded-lg dark:bg-gray-800`, matching the other screens). No charts. Required content:
- **Accounts** card: label `Accounts` and `{{ $accountCount }}`.
- **Recent transactions** card: list/table over `$recentTransactions` (`wire:key="dash-tx-{{ $t->id }}"`) showing `{{ $t->transactionDate->toDateString() }}`, `{{ $t->accountName }}`, `{{ $t->type->label() }}`, `{{ $t->amount }} {{ $t->accountCurrencyCode }}`. If empty, show `No transactions yet`.
- **Active liabilities** card: list over `$activeLiabilities` (`wire:key="dash-liab-{{ $l->id }}"`) showing `{{ $l->name }}` and `Last payment: {{ $lastPaymentDates->get($l->id) ?? '—' }}`. If empty, show `No active liabilities`.

- [ ] **Step 6: Embed in the dashboard page**

Edit `resources/views/dashboard.blade.php` — add `<livewire:dashboard-summary />` inside the `max-w-7xl` container (above or below the existing `fx-sync-button` card). Keep the existing `<livewire:fx-sync-button />` intact. Example insertion (add a wrapping block, do not remove existing markup):
```blade
                <div class="mt-4 px-6 pb-6">
                    <livewire:fx-sync-button />
                </div>
            </div>

            <div class="mt-6">
                <livewire:dashboard-summary />
            </div>
```

- [ ] **Step 7: Run + pint + commit**

Run `--filter=DashboardSummaryTest` → PASS. `pint`. Commit:
```bash
git add app/Livewire/DashboardSummary.php resources/views/livewire/dashboard-summary.blade.php resources/views/dashboard.blade.php tests/Feature/Livewire/DashboardSummaryTest.php
git commit -m "feat: add minimalist dashboard summary widget (accounts, recent tx, active liabilities)"
```

---

## Task 6: Static analysis, style, full-suite + route smoke

- [ ] **Step 1: PHPStan** `./vendor/bin/sail php ./vendor/bin/phpstan analyse --no-progress` → `[OK]`. Fix inline (Collection generics — including the `Collection<string,string>` on `latestDateByLiability`, the `?LiabilityData`/`?AccountBalanceSnapshotData` nullable view vars, `updateOrCreate` array shapes). No blanket ignores.
- [ ] **Step 2: Pint** `./vendor/bin/sail php ./vendor/bin/pint` then `./vendor/bin/sail php ./vendor/bin/pint --test` → clean.
- [ ] **Step 3: Full suite** `./vendor/bin/sail artisan test` → all pass, pristine (no skipped/failed).
- [ ] **Step 4: Route smoke** `./vendor/bin/sail artisan route:list | grep -E 'liability-payments|account-snapshots'` shows both routes; `npm run build` (or `./vendor/bin/sail npm run build`) succeeds.
- [ ] **Step 5: Commit** (only if Step 1/2 changed files) `chore: satisfy phpstan/pint for payments/snapshots/dashboard`.

---

## Self-Review

**Spec coverage:**
- §6.3 `liability_payments` — select liability → context (last payment + count + raw monthly_payment/end_date) → payment form (date default today, total, optional principal/interest portions, note) → save keeps liability/date for rapid entry → recent-payments table with edit/delete → Tasks 1/2 ✅ (remaining-installment estimation intentionally deferred to Grafana per the dumb-app principle; raw fields surfaced instead)
- §6.4 `account_balance_snapshots` — select account → currency badge → balance → date (default today) → **idempotent upsert on (account_id, snapshot_date)** → keeps account/date for rapid entry → recent-snapshots table with edit/delete → Tasks 3/4 ✅
- §6.6 Dashboard — account count + last N transactions + active liabilities with last payment date, no charts, embedded widget → Task 5 ✅
- **Deferred to final plan:** §6.5 CSV import.

**Placeholder scan:** complete code for both DTOs, both new repositories, the two repo extensions (`active`/`count`), both forms, all three components, and every test file; the three views are specified element-by-element with exact bindings/fields mirroring the committed `manage-transactions`/`manage-liabilities` views — concrete, not placeholders. ✅

**Type consistency:**
- `LiabilityPaymentData` fields (`liabilityId`, `liabilityName`, `currencyCode`, `paymentDate:CarbonImmutable`, `totalAmount`, `principalPortion?`, `interestPortion?`, `note?`) match `fromModel` (eager `liability.currency`), the form's `setPayment()`/`toAttributes()` (`liability_id`/`payment_date`/`total_amount`/`principal_portion`/`interest_portion`), and the component's `recentForLiability`/`countForLiability`/`find`/`create`/`update`/`delete` calls. `latestDateByLiability(): Collection<string,string>` matches the dashboard's `->get($l->id)` lookup.
- `AccountBalanceSnapshotData` fields (`accountId`, `accountName`, `institutionName`, `currencyCode`, `balance`, `snapshotDate:CarbonImmutable`, `note?`) match `fromModel` (eager `account.institution`,`account.currency`), the form's `setSnapshot()`/`toAttributes()` (`account_id`/`balance`/`snapshot_date`/`note`), and the component's `recent`/`find`/`upsert`/`update`/`delete` calls. `upsert()` keys on `(account_id, snapshot_date)`.
- `LiabilityRepositoryInterface::active()` and `AccountRepositoryInterface::active()`/`count()` return types match their consumers (`->active()` iterated in views; `->count()` echoed as int). Reused `TransactionRepositoryInterface::recent(int)` returns `Collection<int,TransactionData>` with `accountName`/`type->label()`/`amount`/`accountCurrencyCode` already used by the dashboard view. ✅

**Notes for the implementer:** keep `@js()` ids, `->label()`, snake_case test methods, single-line empty DTO bodies, and `?T` nullable syntax; run Pint before committing each task. The snapshots `save()` deliberately splits `upsert` (new) vs `update` (editing an existing id) so editing a row's balance never orphans the original (account,date) row. The payments/snapshots screens do NOT convert to CZK — that is a transactions-only feature (spec §6.2).
