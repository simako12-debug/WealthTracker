# Data Access (FX slice) & Currency Conversion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the repository + DTO layer for the FX-related entities (Currency, CurrencyPair, FxRate) and a `CurrencyConverter` service that converts an amount in any currency to CZK using the latest stored FX rate on or before a date — the pieces the FX-sync plan (Plan 3) and the transaction live-preview (Plan 4) depend on.

**Architecture:** Per the design spec's layering: each entity gets a `{Entity}RepositoryInterface` + a `final readonly {Entity}Repository` that returns Spatie Laravel Data DTOs (never query builders or raw arrays). Interfaces are bound to implementations in a dedicated `RepositoryServiceProvider`. `CurrencyConverter` is a `final readonly` service depending only on repository interfaces, using bcmath for exact decimal arithmetic. Only the FX slice is built here; the other six entities' repositories/DTOs are built alongside their UI in Plan 4 (per the approved lean-scope decision).

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, PostgreSQL, Spatie Laravel Data 4, PHPUnit, PHPStan (Larastan) level 6, Pint. Run all commands inside the container via `./vendor/bin/sail` (harmless `WWWUSER/WWWGROUP` warnings — ignore).

## Global Constraints

Every task's requirements implicitly include this section.

- **`declare(strict_types=1);`** at the top of every PHP file. Type-hint all parameters and return types.
- **Repositories:** `{Entity}RepositoryInterface` (contract) + `final readonly {Entity}Repository` implementing it. Return Spatie Data DTOs or `Illuminate\Support\Collection` of DTOs — never Eloquent query builders or raw arrays. Bind interface→impl in `RepositoryServiceProvider`.
- **DTOs:** extend `Spatie\LaravelData\Data`, `final`, constructor property promotion, typed properties (enums as `App\Enums\*`, dates as `CarbonImmutable`, money/rate as `string`). Construct DTOs **explicitly** in repositories (e.g. `new FxRateData(id: $m->id, ...)`) — do NOT rely on `Data::from($model)` magic mapping (model attributes are snake_case, DTO props are camelCase).
- **Money/FX precision:** amounts and rates are decimal strings (the models cast `decimal:10`, which returns strings). All arithmetic uses **bcmath at scale 10** (`bcmul($a, $b, 10)`). Never cast money to float.
- **Enums:** `App\Enums\FxSource` (cnb/frankfurter) is the type of both `CurrencyPair::source` and `FxRate::source`.
- **Tests:** every new class has a `{ClassName}Test` in the mirrored `tests/` dir, `#[CoversClass(...)]`, deterministic literal UUIDs in assertions (never `Str::uuid()`), real behavior via `RefreshDatabase` + factories (not mocks). Comparisons `=== false/true/null` (never `!`); array emptiness `empty($x) === true`.
- **PHPDoc:** `@param`/`@return` generics on collection-returning methods (`Collection<int, CurrencyData>`); null-union order `null|string` (null first).
- **Baseline stays green:** after the last task, `phpstan analyse` → `[OK] No errors`, `pint --test` clean, full `artisan test` passing.
- **Existing model APIs (from Plan 1, do not change):** `App\Models\Currency` (`id`,`code`,`name`); `App\Models\CurrencyPair` (`id`,`base_currency_id`,`quote_currency_id`,`source`:FxSource,`is_active`; relations `baseCurrency()`,`quoteCurrency()`); `App\Models\FxRate` (`id`,`currency_from_id`,`currency_to_id`,`rate`:decimal:10 string,`rate_date`:date Carbon,`source`:FxSource; `const UPDATED_AT = null`; relations `currencyFrom()`,`currencyTo()`). All models use `HasUuids` (string ids).

---

## File Structure

```
app/Providers/RepositoryServiceProvider.php     # binds all interfaces → impls
bootstrap/providers.php                          # registers RepositoryServiceProvider
app/Data/CurrencyData.php
app/Data/CurrencyPairData.php
app/Data/FxRateData.php
app/Data/ConversionResult.php
app/Repositories/CurrencyRepositoryInterface.php
app/Repositories/CurrencyRepository.php
app/Repositories/CurrencyPairRepositoryInterface.php
app/Repositories/CurrencyPairRepository.php
app/Repositories/FxRateRepositoryInterface.php
app/Repositories/FxRateRepository.php
app/Services/CurrencyConverter.php
tests/Unit/Data/*Test.php                        # where a DTO has non-trivial construction
tests/Unit/Repositories/*Test.php
tests/Unit/Services/CurrencyConverterTest.php
```

---

## Task 1: Currency DTO + repository + provider

**Files:**
- Create: `app/Data/CurrencyData.php`, `app/Repositories/CurrencyRepositoryInterface.php`, `app/Repositories/CurrencyRepository.php`, `app/Providers/RepositoryServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/Repositories/CurrencyRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Models\Currency` (Plan 1).
- Produces: `App\Data\CurrencyData(string $id, string $code, string $name)`; `CurrencyRepositoryInterface::all(): Collection<int,CurrencyData>` and `findByCode(string $code): ?CurrencyData`; `RepositoryServiceProvider` binding the interface to `CurrencyRepository`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/CurrencyRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\CurrencyRepository::class)]
class CurrencyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyRepositoryInterface
    {
        return $this->app->make(CurrencyRepositoryInterface::class);
    }

    public function testFindByCodeReturnsDataObject(): void
    {
        Currency::factory()->create(['code' => 'USD', 'name' => 'US dollar']);

        $data = $this->repository()->findByCode('USD');

        $this->assertInstanceOf(CurrencyData::class, $data);
        $this->assertSame('USD', $data->code);
        $this->assertSame('US dollar', $data->name);
    }

    public function testFindByCodeReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository()->findByCode('ZZZ'));
    }

    public function testAllReturnsCollectionOfDataObjects(): void
    {
        Currency::factory()->create(['code' => 'CZK']);
        Currency::factory()->create(['code' => 'EUR']);

        $all = $this->repository()->all();

        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(CurrencyData::class, $all);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=CurrencyRepositoryTest`
Expected: FAIL — `CurrencyRepositoryInterface` / binding not found.

- [ ] **Step 3: Create the DTO**

Create `app/Data/CurrencyData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Currency;
use Spatie\LaravelData\Data;

final class CurrencyData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {
    }

    public static function fromModel(Currency $currency): self
    {
        return new self(
            id: $currency->id,
            code: $currency->code,
            name: $currency->name,
        );
    }
}
```

- [ ] **Step 4: Create the interface**

Create `app/Repositories/CurrencyRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface
{
    /** @return Collection<int, CurrencyData> */
    public function all(): Collection;

    public function findByCode(string $code): ?CurrencyData;
}
```

- [ ] **Step 5: Create the implementation**

Create `app/Repositories/CurrencyRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use Illuminate\Support\Collection;

final readonly class CurrencyRepository implements CurrencyRepositoryInterface
{
    /** @return Collection<int, CurrencyData> */
    public function all(): Collection
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): CurrencyData => CurrencyData::fromModel($currency));
    }

    public function findByCode(string $code): ?CurrencyData
    {
        $currency = Currency::query()->where('code', $code)->first();

        return $currency === null ? null : CurrencyData::fromModel($currency);
    }
}
```

- [ ] **Step 6: Create the service provider**

Create `app/Providers/RepositoryServiceProvider.php`:
```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\CurrencyRepository;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CurrencyRepositoryInterface::class => CurrencyRepository::class,
    ];
}
```

- [ ] **Step 7: Register the provider**

In `bootstrap/providers.php`, add `App\Providers\RepositoryServiceProvider::class` to the returned array:
```php
<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
```
(Preserve any providers already listed; add the new one. Keep/add `declare(strict_types=1);`.)

- [ ] **Step 8: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=CurrencyRepositoryTest`
Expected: PASS (3 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Data/CurrencyData.php app/Repositories/CurrencyRepositoryInterface.php app/Repositories/CurrencyRepository.php app/Providers/RepositoryServiceProvider.php bootstrap/providers.php tests/Unit/Repositories/CurrencyRepositoryTest.php
git commit -m "feat: add Currency DTO + repository and RepositoryServiceProvider"
```

---

## Task 2: CurrencyPair DTO + repository

**Files:**
- Create: `app/Data/CurrencyPairData.php`, `app/Repositories/CurrencyPairRepositoryInterface.php`, `app/Repositories/CurrencyPairRepository.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/CurrencyPairRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Models\CurrencyPair`, `App\Enums\FxSource`.
- Produces: `App\Data\CurrencyPairData(string $id, string $baseCurrencyId, string $baseCurrencyCode, string $quoteCurrencyId, string $quoteCurrencyCode, FxSource $source, bool $isActive)`; `CurrencyPairRepositoryInterface::activePairs(): Collection<int,CurrencyPairData>` (only `is_active = true`, base/quote currency codes eager-loaded).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/CurrencyPairRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Repositories\CurrencyPairRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\CurrencyPairRepository::class)]
class CurrencyPairRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyPairRepositoryInterface
    {
        return $this->app->make(CurrencyPairRepositoryInterface::class);
    }

    public function testActivePairsReturnsOnlyActiveWithCurrencyCodes(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
            'is_active' => true,
        ]);
        CurrencyPair::factory()->create([
            'base_currency_id' => $eur->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
            'is_active' => false,
        ]);

        $pairs = $this->repository()->activePairs();

        $this->assertCount(1, $pairs);
        $this->assertContainsOnlyInstancesOf(CurrencyPairData::class, $pairs);

        $pair = $pairs->first();
        $this->assertSame('USD', $pair->baseCurrencyCode);
        $this->assertSame('CZK', $pair->quoteCurrencyCode);
        $this->assertSame(FxSource::CNB, $pair->source);
        $this->assertTrue($pair->isActive);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=CurrencyPairRepositoryTest`
Expected: FAIL — interface/binding missing.

- [ ] **Step 3: Create the DTO**

Create `app/Data/CurrencyPairData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FxSource;
use App\Models\CurrencyPair;
use Spatie\LaravelData\Data;

final class CurrencyPairData extends Data
{
    public function __construct(
        public string $id,
        public string $baseCurrencyId,
        public string $baseCurrencyCode,
        public string $quoteCurrencyId,
        public string $quoteCurrencyCode,
        public FxSource $source,
        public bool $isActive,
    ) {
    }

    public static function fromModel(CurrencyPair $pair): self
    {
        return new self(
            id: $pair->id,
            baseCurrencyId: $pair->base_currency_id,
            baseCurrencyCode: $pair->baseCurrency->code,
            quoteCurrencyId: $pair->quote_currency_id,
            quoteCurrencyCode: $pair->quoteCurrency->code,
            source: $pair->source,
            isActive: $pair->is_active,
        );
    }
}
```

- [ ] **Step 4: Create the interface**

Create `app/Repositories/CurrencyPairRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use Illuminate\Support\Collection;

interface CurrencyPairRepositoryInterface
{
    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection;
}
```

- [ ] **Step 5: Create the implementation**

Create `app/Repositories/CurrencyPairRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use App\Models\CurrencyPair;
use Illuminate\Support\Collection;

final readonly class CurrencyPairRepository implements CurrencyPairRepositoryInterface
{
    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection
    {
        return CurrencyPair::query()
            ->with(['baseCurrency', 'quoteCurrency'])
            ->where('is_active', true)
            ->get()
            ->map(fn (CurrencyPair $pair): CurrencyPairData => CurrencyPairData::fromModel($pair));
    }
}
```

- [ ] **Step 6: Register the binding**

In `app/Providers/RepositoryServiceProvider.php`, add to `$bindings`:
```php
        CurrencyPairRepositoryInterface::class => CurrencyPairRepository::class,
```
(with the matching `use` imports for `CurrencyPairRepository` and `CurrencyPairRepositoryInterface`.)

- [ ] **Step 7: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=CurrencyPairRepositoryTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Data/CurrencyPairData.php app/Repositories/CurrencyPairRepositoryInterface.php app/Repositories/CurrencyPairRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/CurrencyPairRepositoryTest.php
git commit -m "feat: add CurrencyPair DTO + repository (activePairs)"
```

---

## Task 3: FxRate DTO + repository (upsert + latestRate)

**Files:**
- Create: `app/Data/FxRateData.php`, `app/Repositories/FxRateRepositoryInterface.php`, `app/Repositories/FxRateRepository.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/FxRateRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Models\FxRate`, `App\Enums\FxSource`.
- Produces: `App\Data\FxRateData(null|string $id, string $currencyFromId, string $currencyToId, string $rate, CarbonImmutable $rateDate, FxSource $source)`; `FxRateRepositoryInterface::upsert(FxRateData $data): FxRateData` (idempotent on `(currency_from_id, currency_to_id, rate_date, source)`, updates `rate`) and `latestRate(string $currencyFromId, string $currencyToId, CarbonImmutable $onOrBefore): ?FxRateData` (newest `rate_date` ≤ `$onOrBefore`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/FxRateRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\FxRateData;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use App\Repositories\FxRateRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\FxRateRepository::class)]
class FxRateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): FxRateRepositoryInterface
    {
        return $this->app->make(FxRateRepositoryInterface::class);
    }

    public function testUpsertCreatesThenUpdatesSameRow(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $data = new FxRateData(
            id: null,
            currencyFromId: $usd->id,
            currencyToId: $czk->id,
            rate: '23.1000000000',
            rateDate: CarbonImmutable::parse('2026-03-15'),
            source: FxSource::CNB,
        );

        $this->repository()->upsert($data);

        $updated = new FxRateData(
            id: null,
            currencyFromId: $usd->id,
            currencyToId: $czk->id,
            rate: '23.5000000000',
            rateDate: CarbonImmutable::parse('2026-03-15'),
            source: FxSource::CNB,
        );
        $result = $this->repository()->upsert($updated);

        $this->assertSame(1, FxRate::query()->count());
        $this->assertSame('23.5000000000', $result->rate);
    }

    public function testLatestRateReturnsNewestOnOrBeforeDate(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        foreach (['2026-03-01' => '22.0000000000', '2026-03-10' => '23.0000000000', '2026-03-20' => '24.0000000000'] as $date => $rate) {
            FxRate::factory()->create([
                'currency_from_id' => $usd->id,
                'currency_to_id' => $czk->id,
                'rate' => $rate,
                'rate_date' => $date,
                'source' => FxSource::CNB,
            ]);
        }

        $result = $this->repository()->latestRate($usd->id, $czk->id, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(FxRateData::class, $result);
        $this->assertSame('23.0000000000', $result->rate);
        $this->assertSame('2026-03-10', $result->rateDate->toDateString());
    }

    public function testLatestRateReturnsNullWhenNoRateOnOrBefore(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '24.0000000000',
            'rate_date' => '2026-03-20',
            'source' => FxSource::CNB,
        ]);

        $result = $this->repository()->latestRate($usd->id, $czk->id, CarbonImmutable::parse('2026-03-15'));

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=FxRateRepositoryTest`
Expected: FAIL — interface/binding missing.

- [ ] **Step 3: Create the DTO**

Create `app/Data/FxRateData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FxSource;
use App\Models\FxRate;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class FxRateData extends Data
{
    public function __construct(
        public null|string $id,
        public string $currencyFromId,
        public string $currencyToId,
        public string $rate,
        public CarbonImmutable $rateDate,
        public FxSource $source,
    ) {
    }

    public static function fromModel(FxRate $rate): self
    {
        return new self(
            id: $rate->id,
            currencyFromId: $rate->currency_from_id,
            currencyToId: $rate->currency_to_id,
            rate: $rate->rate,
            rateDate: $rate->rate_date->toImmutable(),
            source: $rate->source,
        );
    }
}
```

- [ ] **Step 4: Create the interface**

Create `app/Repositories/FxRateRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\FxRateData;
use Carbon\CarbonImmutable;

interface FxRateRepositoryInterface
{
    public function upsert(FxRateData $data): FxRateData;

    public function latestRate(
        string $currencyFromId,
        string $currencyToId,
        CarbonImmutable $onOrBefore,
    ): ?FxRateData;
}
```

- [ ] **Step 5: Create the implementation**

Create `app/Repositories/FxRateRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\FxRateData;
use App\Models\FxRate;
use Carbon\CarbonImmutable;

final readonly class FxRateRepository implements FxRateRepositoryInterface
{
    public function upsert(FxRateData $data): FxRateData
    {
        $rate = FxRate::query()->updateOrCreate(
            [
                'currency_from_id' => $data->currencyFromId,
                'currency_to_id' => $data->currencyToId,
                'rate_date' => $data->rateDate->toDateString(),
                'source' => $data->source,
            ],
            [
                'rate' => $data->rate,
            ],
        );

        return FxRateData::fromModel($rate);
    }

    public function latestRate(
        string $currencyFromId,
        string $currencyToId,
        CarbonImmutable $onOrBefore,
    ): ?FxRateData {
        $rate = FxRate::query()
            ->where('currency_from_id', $currencyFromId)
            ->where('currency_to_id', $currencyToId)
            ->where('rate_date', '<=', $onOrBefore->toDateString())
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();

        return $rate === null ? null : FxRateData::fromModel($rate);
    }
}
```

- [ ] **Step 6: Register the binding**

In `app/Providers/RepositoryServiceProvider.php`, add to `$bindings`:
```php
        FxRateRepositoryInterface::class => FxRateRepository::class,
```
(with the matching `use` imports.)

- [ ] **Step 7: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=FxRateRepositoryTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Data/FxRateData.php app/Repositories/FxRateRepositoryInterface.php app/Repositories/FxRateRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/FxRateRepositoryTest.php
git commit -m "feat: add FxRate DTO + repository (upsert, latestRate)"
```

---

## Task 4: CurrencyConverter service

**Files:**
- Create: `app/Data/ConversionResult.php`, `app/Services/CurrencyConverter.php`
- Test: `tests/Unit/Services/CurrencyConverterTest.php`

**Interfaces:**
- Consumes: `CurrencyRepositoryInterface` (Task 1), `FxRateRepositoryInterface` (Task 3), `App\Models\Currency`.
- Produces: `App\Data\ConversionResult(string $amount, string $rate, CarbonImmutable $rateDate)`; `CurrencyConverter::toCzk(string $amount, Currency $from, CarbonImmutable $date): ?ConversionResult`.

Behavior:
- If `$from->code === 'CZK'`: return `ConversionResult($amount, '1', $date)` (identity, no lookup).
- Else: resolve the CZK currency via `CurrencyRepositoryInterface::findByCode('CZK')`; if missing → `null`. Look up `latestRate($from->id, czkId, $date)`; if missing → `null`. Otherwise converted amount = `bcmul($amount, $rate->rate, 10)`; return `ConversionResult(converted, $rate->rate, $rate->rateDate)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/CurrencyConverterTest.php`:
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
class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    private function converter(): CurrencyConverter
    {
        return $this->app->make(CurrencyConverter::class);
    }

    public function testCzkToCzkIsIdentity(): void
    {
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzk('1500.0000000000', $czk, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('1500.0000000000', $result->amount);
        $this->assertSame('1', $result->rate);
    }

    public function testConvertsUsingLatestRateOnOrBeforeDate(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '23.0000000000',
            'rate_date' => '2026-03-10',
            'source' => FxSource::CNB,
        ]);

        $result = $this->converter()->toCzk('10.0000000000', $usd, CarbonImmutable::parse('2026-03-15'));

        $this->assertInstanceOf(ConversionResult::class, $result);
        $this->assertSame('230.0000000000', $result->amount);
        $this->assertSame('23.0000000000', $result->rate);
        $this->assertSame('2026-03-10', $result->rateDate->toDateString());
    }

    public function testReturnsNullWhenNoRateAvailable(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        Currency::factory()->create(['code' => 'CZK']);

        $result = $this->converter()->toCzk('10.0000000000', $usd, CarbonImmutable::parse('2026-03-15'));

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=CurrencyConverterTest`
Expected: FAIL — `CurrencyConverter` / `ConversionResult` missing.

- [ ] **Step 3: Create the ConversionResult DTO**

Create `app/Data/ConversionResult.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ConversionResult extends Data
{
    public function __construct(
        public string $amount,
        public string $rate,
        public CarbonImmutable $rateDate,
    ) {
    }
}
```

- [ ] **Step 4: Create the CurrencyConverter**

Create `app/Services/CurrencyConverter.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ConversionResult;
use App\Models\Currency;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\FxRateRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class CurrencyConverter
{
    private const string CZK = 'CZK';

    public function __construct(
        private CurrencyRepositoryInterface $currencies,
        private FxRateRepositoryInterface $rates,
    ) {
    }

    public function toCzk(string $amount, Currency $from, CarbonImmutable $date): ?ConversionResult
    {
        if ($from->code === self::CZK) {
            return new ConversionResult(amount: $amount, rate: '1', rateDate: $date);
        }

        $czk = $this->currencies->findByCode(self::CZK);

        if ($czk === null) {
            return null;
        }

        $rate = $this->rates->latestRate($from->id, $czk->id, $date);

        if ($rate === null) {
            return null;
        }

        return new ConversionResult(
            amount: bcmul($amount, $rate->rate, 10),
            rate: $rate->rate,
            rateDate: $rate->rateDate,
        );
    }
}
```

- [ ] **Step 5: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=CurrencyConverterTest`
Expected: PASS (3 tests). If bcmath is unavailable in the container, `bcmul` will error — bcmath ships with the Sail PHP image; if it is somehow missing, STOP and report (do not silently switch to float).

- [ ] **Step 6: Commit**

```bash
git add app/Data/ConversionResult.php app/Services/CurrencyConverter.php tests/Unit/Services/CurrencyConverterTest.php
git commit -m "feat: add CurrencyConverter service (amount to CZK via latest fx rate)"
```

---

## Task 5: Static analysis, style, and full-suite verification

**Files:** none (verification + any inline fixes).

**Interfaces:** Consumes all of Tasks 1–4.

- [ ] **Step 1: PHPStan**

Run: `./vendor/bin/sail php ./vendor/bin/phpstan analyse --no-progress`
Expected: `[OK] No errors`. If errors appear (e.g. a generic on a `Collection` return, a nullable mismatch, or Spatie Data property typing), fix them INLINE in the offending file — correct the PHPDoc/types, do not add blanket ignores. Re-run until green.

- [ ] **Step 2: Pint**

Run: `./vendor/bin/sail php ./vendor/bin/pint` then `./vendor/bin/sail php ./vendor/bin/pint --test`
Expected: `--test` reports no style issues.

- [ ] **Step 3: Full suite**

Run: `./vendor/bin/sail artisan test`
Expected: all pass, pristine (the 27 Plan-1 tests plus the new repository/converter tests).

- [ ] **Step 4: Commit (only if Step 1/2 changed files)**

```bash
git add -A
git commit -m "chore: satisfy phpstan/pint for data-access + converter layer"
```

---

## Self-Review

**Spec coverage (Plan 2 lean scope):**
- `CurrencyConverter` toCzk with nearest-older rate, CZK identity, missing-rate → null → Task 4 ✅ (spec §5.3)
- FX-slice repositories returning DTOs (Currency, CurrencyPair, FxRate) → Tasks 1–3 ✅ (spec §3.2)
- `FxRateRepository::upsert` idempotent on the unique key (for Plan 3 sync) + `latestRate` (for converter) → Task 3 ✅ (spec §5.2/§5.3)
- `CurrencyPairRepository::activePairs` (for Plan 3 sync to know which pairs to fetch) → Task 2 ✅ (spec §5.2)
- Repository interfaces bound in a provider; `final readonly` impls returning DTOs → all tasks ✅
- **Deferred by design:** repositories/DTOs for the other 6 entities (Institution, Account, Transaction, Liability, LiabilityPayment, AccountBalanceSnapshot) → Plan 4, built with their UI.

**Placeholder scan:** No TBD/TODO; every code step is complete; every command has an expected result. ✅

**Type consistency:** Repo method names (`all`, `findByCode`, `activePairs`, `upsert`, `latestRate`) and DTO property names are used identically across interfaces, impls, tests, and the converter. `latestRate(fromId, toId, onOrBefore)` signature matches the converter's call. `FxRateData.rateDate` is `CarbonImmutable`; `FxRate.rate_date` (Carbon) is converted via `->toImmutable()`. `rate`/`amount` are decimal strings throughout; arithmetic is `bcmul(..., 10)`. `FxSource` enum used consistently. ✅

**Note for Plan 3 (FX sync):** it will consume `CurrencyPairRepositoryInterface::activePairs()` to know what to fetch, and `FxRateRepositoryInterface::upsert(FxRateData)` to store results (idempotent). `FxRateData.source` and `CurrencyPair.source` are the `FxSource` enum. `latestRate` orders by `rate_date` desc then `id` desc, so if multiple sources exist for the same date it deterministically returns one — acceptable for CZK conversion where a pair has a single source.
