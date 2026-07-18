# Foundation & Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Laravel + Livewire + PostgreSQL + Grafana skeleton via Docker (Laravel Sail), with single-user auth, the full database schema (enums, migrations, UUID models, factories), and seed data — a running, loggable-in app with Grafana connected to the same database.

**Architecture:** Laravel Sail provides the `laravel.test` (app) and `pgsql` containers; a third `grafana` service is added to the same Docker network with a provisioned Postgres datasource. Domain layer uses UUID-keyed Eloquent models with backed-string enums cast in the model, one factory per model, and a seeder producing sample data. Auth is Laravel Breeze (Blade) reduced to a single seeded user (registration and password-reset removed).

**Tech Stack:** Laravel 12 (latest stable), Livewire 3, PHP 8.4, PostgreSQL 17, Grafana (latest), Laravel Breeze (Blade + Tailwind), Spatie Laravel Data, PHPUnit.

## Global Constraints

Every task's requirements implicitly include this section.

- **`declare(strict_types=1);`** at the top of every PHP file. Type-hint all parameters and return types.
- **UUID primary keys** via Laravel's `HasUuids` trait (string UUIDs). NOTE: the global guidelines show a `getKey()` override returning `$this->id->toString()` backed by a project-internal `EloquentUuidCast`; that cast is not available in this greenfield repo, so we use the framework-standard `HasUuids` string-UUID approach. `id` is a `string`.
- **Foreign keys:** always add `->index()` alongside the FK — PostgreSQL does NOT auto-create FK indexes.
- **Money and FX precision:** all monetary amounts and FX rates are `decimal(20, 10)`.
- **Enums:** backed string enums, UPPER_CASE case names, lowercase string values, registered in the model's `casts()` method.
- **PHPDoc:** document model properties with `@property`; relationships as `HasMany<Model, $this>` / `BelongsTo<Model, $this>`; factory generics `@use HasFactory<XFactory>` and `@extends Factory<XModel>`; null-union order `null|string` (null first).
- **Tests:** every new class has a `{ClassName}Test` in the mirrored `tests/` directory, using the `#[CoversClass(...)]` attribute. Deterministic UUIDs via literal strings, never `Str::uuid()` in assertions.
- **Comparisons:** `=== false` / `=== true` / `=== null`, never `!`. Array emptiness: `empty($x) === true/false`.
- **Line length:** max 120 chars.
- **All runtime commands run inside the container** via `./vendor/bin/sail`. Only the initial scaffold and package installation (Task 1) use host `composer`/`php` because the containers do not exist yet.
- **`users` table** keeps Breeze's default auto-increment `id` (framework/auth table, not a domain model).

---

## File Structure

```
docker-compose.yml                       # Sail-generated, extended with grafana
docker/grafana/provisioning/datasources/datasource.yml
.env / .env.example                      # DB + seed user config
routes/auth.php                          # trimmed to login/logout only
app/Enums/InstitutionType.php
app/Enums/AccountType.php
app/Enums/TransactionType.php
app/Enums/FxSource.php
app/Models/{Currency,Institution,Account,CurrencyPair,FxRate,Transaction,Liability,LiabilityPayment,AccountBalanceSnapshot}.php
database/factories/{...}Factory.php
database/migrations/*                     # one per domain table
database/seeders/{DatabaseSeeder,UserSeeder}.php
tests/Unit/Enums/*Test.php
tests/Unit/Models/*Test.php
tests/Feature/AuthTest.php
```

---

## Task 1: Scaffold Laravel + Sail (pgsql) + packages

**Files:**
- Create: entire Laravel skeleton in `C:/Software/WealthTracker` (preserving existing `.git` and `docs/`)
- Modify: `.env`, `.env.example`

**Interfaces:**
- Consumes: nothing (first task)
- Produces: a booting app served by Sail on `http://localhost:8000`, `pgsql` service reachable as host `pgsql`, DB credentials `sail` / `password` / database `wealthtracker`. Sail binary at `./vendor/bin/sail`. Docker network named `sail`.

- [ ] **Step 1: Scaffold Laravel into a temp dir and merge into the repo**

Run (host, Git Bash):
```bash
composer create-project laravel/laravel "C:/Software/wt-scaffold"
cp -r "C:/Software/wt-scaffold/." "C:/Software/WealthTracker/"
rm -rf "C:/Software/wt-scaffold"
```
This preserves the existing `.git/` and `docs/` (cp merges, does not delete) and adds Laravel's `.gitignore` (ignores `vendor/`, `node_modules/`, `.env`).

- [ ] **Step 2: Require runtime and dev packages (host composer)**

Run from `C:/Software/WealthTracker`:
```bash
composer require livewire/livewire spatie/laravel-data
composer require laravel/sail laravel/breeze --dev
```
Expected: all four packages resolve and install; `composer.json` lists them.

- [ ] **Step 3: Install Sail with the pgsql service**

Run:
```bash
php artisan sail:install --with=pgsql
```
Expected: `docker-compose.yml` created with `laravel.test` and `pgsql` services; `.env` updated to `DB_CONNECTION=pgsql`.

- [ ] **Step 4: Install Breeze (Blade stack), non-interactive**

Run:
```bash
php artisan breeze:install blade --no-interaction
```
Expected: auth views/routes/controllers generated under `resources/views/auth`, `routes/auth.php`, `app/Http/Controllers/Auth/*`; `npm install` + `npm run build` run automatically (Node present on host). Tailwind + Vite configured.

- [ ] **Step 5: Configure `.env` for Postgres, port, and seed user**

Edit `.env` so these keys read exactly:
```dotenv
APP_NAME=WealthTracker
APP_URL=http://localhost:8000
APP_PORT=8000

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=wealthtracker
DB_USERNAME=sail
DB_PASSWORD=password

SEED_USER_NAME="Petr"
SEED_USER_EMAIL=petr.sima@sharry.tech
SEED_USER_PASSWORD=changeme
```
Mirror the same keys (with placeholder values, no real password) into `.env.example`, adding:
```dotenv
SEED_USER_NAME="Owner"
SEED_USER_EMAIL=owner@example.test
SEED_USER_PASSWORD=changeme
```

- [ ] **Step 6: Boot containers and verify DB connectivity**

Run:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```
Expected: containers `wealthtracker-laravel.test-1` and `wealthtracker-pgsql-1` are `Up`; `migrate` runs the default framework migrations against Postgres with no connection error.

- [ ] **Step 7: Verify the app responds**

Run:
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000
```
Expected: `200`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel + Sail (pgsql) + Livewire + Breeze + Spatie Data"
```

---

## Task 2: Add Grafana service with provisioned Postgres datasource

**Files:**
- Modify: `docker-compose.yml`
- Create: `docker/grafana/provisioning/datasources/datasource.yml`

**Interfaces:**
- Consumes: `pgsql` service and DB credentials from Task 1.
- Produces: Grafana at `http://localhost:3000` with a `WealthTracker` Postgres datasource pre-connected (no manual setup), persisted via the `grafana-data` volume.

- [ ] **Step 1: Create the datasource provisioning file**

Create `docker/grafana/provisioning/datasources/datasource.yml`:
```yaml
apiVersion: 1

datasources:
  - name: WealthTracker
    type: postgres
    access: proxy
    url: pgsql:5432
    user: sail
    isDefault: true
    jsonData:
      database: wealthtracker
      sslmode: disable
      postgresVersion: 1700
    secureJsonData:
      password: password
    editable: true
```

- [ ] **Step 2: Add the grafana service to `docker-compose.yml`**

Under `services:` (sibling of `laravel.test` and `pgsql`), add:
```yaml
    grafana:
        image: grafana/grafana:latest
        ports:
            - '3000:3000'
        environment:
            GF_SECURITY_ADMIN_USER: '${GRAFANA_ADMIN_USER:-admin}'
            GF_SECURITY_ADMIN_PASSWORD: '${GRAFANA_ADMIN_PASSWORD:-admin}'
            GF_USERS_ALLOW_SIGN_UP: 'false'
        volumes:
            - 'grafana-data:/var/lib/grafana'
            - './docker/grafana/provisioning:/etc/grafana/provisioning'
        networks:
            - sail
        depends_on:
            - pgsql
```
In the `volumes:` block at the bottom of the file (next to `sail-pgsql`), add:
```yaml
    grafana-data:
        driver: local
```

- [ ] **Step 3: Recreate containers and verify Grafana health**

Run:
```bash
./vendor/bin/sail up -d
sleep 5
curl -s http://localhost:3000/api/health
```
Expected: JSON containing `"database": "ok"`.

- [ ] **Step 4: Verify the datasource provisioned and connects to Postgres**

Run:
```bash
curl -s -u admin:admin http://localhost:3000/api/datasources
```
Expected: JSON array containing one datasource named `WealthTracker` of type `postgres`.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml docker/grafana
git commit -m "feat: add Grafana service with provisioned Postgres datasource"
```

---

## Task 3: Reduce auth to a single seeded user

**Files:**
- Modify: `routes/auth.php`
- Create: `database/seeders/UserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/AuthTest.php`

**Interfaces:**
- Consumes: Breeze routes from Task 1, `SEED_USER_*` env from Task 1.
- Produces: only `login` (GET/POST) and `logout` routes exist; `register`, `password.request`, `password.reset` routes are absent. `UserSeeder` upserts one user by email.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/AuthTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserSeeder::class)]
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function testRegisterRouteIsRemoved(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function testPasswordResetRouteIsRemoved(): void
    {
        $this->get('/forgot-password')->assertNotFound();
    }

    public function testSeededUserCanLogIn(): void
    {
        config()->set('app.seed_user', [
            'name' => 'Test Owner',
            'email' => 'owner@example.test',
            'password' => 'secret-pw',
        ]);
        $this->seed(UserSeeder::class);

        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'secret-pw',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertSame(1, User::query()->count());
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run:
```bash
./vendor/bin/sail artisan test --filter=AuthTest
```
Expected: FAIL — `UserSeeder` class not found and `/register` still returns 200.

- [ ] **Step 3: Add seed-user config**

In `config/app.php`, add a `seed_user` entry near the top of the returned array:
```php
    'seed_user' => [
        'name' => env('SEED_USER_NAME', 'Owner'),
        'email' => env('SEED_USER_EMAIL', 'owner@example.test'),
        'password' => env('SEED_USER_PASSWORD', 'changeme'),
    ],
```

- [ ] **Step 4: Create `UserSeeder`**

Create `database/seeders/UserSeeder.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name:string,email:string,password:string} $config */
        $config = config('app.seed_user');

        User::query()->updateOrCreate(
            ['email' => $config['email']],
            [
                'name' => $config['name'],
                'password' => Hash::make($config['password']),
                'email_verified_at' => now(),
            ],
        );
    }
}
```

- [ ] **Step 5: Trim `routes/auth.php` to login/logout only**

Replace the contents of `routes/auth.php` with:
```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
```

- [ ] **Step 6: Register `UserSeeder` in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, replace the `run()` body with:
```php
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
```
Remove any default `User::factory()` call. Ensure `use Database\Seeders\UserSeeder;` is not needed (same namespace).

- [ ] **Step 7: Run the test to confirm it passes**

Run:
```bash
./vendor/bin/sail artisan test --filter=AuthTest
```
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add routes/auth.php config/app.php database/seeders tests/Feature/AuthTest.php
git commit -m "feat: reduce auth to single seeded user, remove registration/reset"
```

---

## Task 4: Domain enums

**Files:**
- Create: `app/Enums/InstitutionType.php`, `app/Enums/AccountType.php`, `app/Enums/TransactionType.php`, `app/Enums/FxSource.php`
- Create: `tests/Unit/Enums/InstitutionTypeTest.php`, `tests/Unit/Enums/AccountTypeTest.php`, `tests/Unit/Enums/TransactionTypeTest.php`, `tests/Unit/Enums/FxSourceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\InstitutionType` (bank/broker/exchange/lender/other), `App\Enums\AccountType` (bank/investment/savings/wallet), `App\Enums\TransactionType` (deposit/withdrawal/dividend/interest/capital_gain/capital_loss/fee/bond_income/other), `App\Enums\FxSource` (cnb/frankfurter). All backed string enums.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Enums/InstitutionTypeTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\InstitutionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstitutionType::class)]
class InstitutionTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('bank', InstitutionType::BANK->value);
        $this->assertSame('lender', InstitutionType::LENDER->value);
        $this->assertCount(5, InstitutionType::cases());
    }
}
```
Create `tests/Unit/Enums/AccountTypeTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AccountType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountType::class)]
class AccountTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('investment', AccountType::INVESTMENT->value);
        $this->assertCount(4, AccountType::cases());
    }
}
```
Create `tests/Unit/Enums/TransactionTypeTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TransactionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionType::class)]
class TransactionTypeTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('capital_gain', TransactionType::CAPITAL_GAIN->value);
        $this->assertSame('bond_income', TransactionType::BOND_INCOME->value);
        $this->assertCount(9, TransactionType::cases());
    }
}
```
Create `tests/Unit/Enums/FxSourceTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FxSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FxSource::class)]
class FxSourceTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('cnb', FxSource::CNB->value);
        $this->assertSame('frankfurter', FxSource::FRANKFURTER->value);
        $this->assertCount(2, FxSource::cases());
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=Enums
```
Expected: FAIL — enum classes not found.

- [ ] **Step 3: Create the enums**

Create `app/Enums/InstitutionType.php`:
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InstitutionType: string
{
    case BANK = 'bank';
    case BROKER = 'broker';
    case EXCHANGE = 'exchange';
    case LENDER = 'lender';
    case OTHER = 'other';
}
```
Create `app/Enums/AccountType.php`:
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case BANK = 'bank';
    case INVESTMENT = 'investment';
    case SAVINGS = 'savings';
    case WALLET = 'wallet';
}
```
Create `app/Enums/TransactionType.php`:
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case DIVIDEND = 'dividend';
    case INTEREST = 'interest';
    case CAPITAL_GAIN = 'capital_gain';
    case CAPITAL_LOSS = 'capital_loss';
    case FEE = 'fee';
    case BOND_INCOME = 'bond_income';
    case OTHER = 'other';
}
```
Create `app/Enums/FxSource.php`:
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum FxSource: string
{
    case CNB = 'cnb';
    case FRANKFURTER = 'frankfurter';
}
```

- [ ] **Step 4: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=Enums
```
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Enums tests/Unit/Enums
git commit -m "feat: add domain enums (institution/account/transaction types, fx source)"
```

---

## Task 5: Currency model

**Files:**
- Create: `database/migrations/2026_07_18_000100_create_currencies_table.php`
- Create: `app/Models/Currency.php`
- Create: `database/factories/CurrencyFactory.php`
- Create: `tests/Unit/Models/CurrencyTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Models\Currency` with `code`, `name`; `hasMany` accounts and liabilities; `CurrencyFactory`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/CurrencyTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Currency::class)]
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function testFactoryCreatesCurrencyWithUuid(): void
    {
        $currency = Currency::factory()->create(['code' => 'CZK', 'name' => 'Czech koruna']);

        $this->assertSame('CZK', $currency->code);
        $this->assertIsString($currency->id);
        $this->assertSame(36, strlen($currency->id));
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=CurrencyTest
```
Expected: FAIL — `Currency` class / table missing.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000100_create_currencies_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Currency.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['code', 'name'];

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(related: Account::class);
    }

    /** @return HasMany<Liability, $this> */
    public function liabilities(): HasMany
    {
        return $this->hasMany(related: Liability::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/CurrencyFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->words(2, true),
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=CurrencyTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Currency.php database/factories/CurrencyFactory.php tests/Unit/Models/CurrencyTest.php
git commit -m "feat: add Currency model, migration, factory"
```

---

## Task 6: Institution model

**Files:**
- Create: `database/migrations/2026_07_18_000110_create_institutions_table.php`
- Create: `app/Models/Institution.php`
- Create: `database/factories/InstitutionFactory.php`
- Create: `tests/Unit/Models/InstitutionTest.php`

**Interfaces:**
- Consumes: `InstitutionType` enum.
- Produces: `App\Models\Institution` with `name`, `type` (cast to `InstitutionType`), `note`; `hasMany` accounts and liabilities.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/InstitutionTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Institution::class)]
class InstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function testFactoryCreatesInstitutionWithEnumCast(): void
    {
        $institution = Institution::factory()->create(['type' => InstitutionType::BROKER]);

        $this->assertInstanceOf(InstitutionType::class, $institution->type);
        $this->assertSame(InstitutionType::BROKER, $institution->type);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=InstitutionTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000110_create_institutions_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Institution.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstitutionType;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property InstitutionType $type
 * @property null|string $note
 */
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['name', 'type', 'note'];

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(related: Account::class);
    }

    /** @return HasMany<Liability, $this> */
    public function liabilities(): HasMany
    {
        return $this->hasMany(related: Liability::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/InstitutionFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Institution> */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(InstitutionType::cases()),
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=InstitutionTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Institution.php database/factories/InstitutionFactory.php tests/Unit/Models/InstitutionTest.php
git commit -m "feat: add Institution model, migration, factory"
```

---

## Task 7: Account model

**Files:**
- Create: `database/migrations/2026_07_18_000120_create_accounts_table.php`
- Create: `app/Models/Account.php`
- Create: `database/factories/AccountFactory.php`
- Create: `tests/Unit/Models/AccountTest.php`

**Interfaces:**
- Consumes: `Institution`, `Currency`, `AccountType`.
- Produces: `App\Models\Account` with `institution_id`, `currency_id`, `name`, `type` (cast `AccountType`), `is_active` (bool), `note`; `belongsTo` institution/currency, `hasMany` transactions/balanceSnapshots.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/AccountTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Account::class)]
class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function testAccountBelongsToInstitutionAndCurrency(): void
    {
        $institution = Institution::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);

        $account = Account::factory()->create([
            'institution_id' => $institution->id,
            'currency_id' => $currency->id,
            'type' => AccountType::INVESTMENT,
        ]);

        $this->assertSame($institution->id, $account->institution->id);
        $this->assertSame('USD', $account->currency->code);
        $this->assertSame(AccountType::INVESTMENT, $account->type);
        $this->assertTrue($account->is_active);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=AccountTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000120_create_accounts_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('currency_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Account.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $currency_id
 * @property string $name
 * @property AccountType $type
 * @property bool $is_active
 * @property null|string $note
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['institution_id', 'currency_id', 'name', 'type', 'is_active', 'note'];

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(related: Institution::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(related: Transaction::class);
    }

    /** @return HasMany<AccountBalanceSnapshot, $this> */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(related: AccountBalanceSnapshot::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/AccountFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'currency_id' => Currency::factory(),
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(AccountType::cases()),
            'is_active' => true,
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=AccountTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Account.php database/factories/AccountFactory.php tests/Unit/Models/AccountTest.php
git commit -m "feat: add Account model, migration, factory"
```

---

## Task 8: CurrencyPair model

**Files:**
- Create: `database/migrations/2026_07_18_000130_create_currency_pairs_table.php`
- Create: `app/Models/CurrencyPair.php`
- Create: `database/factories/CurrencyPairFactory.php`
- Create: `tests/Unit/Models/CurrencyPairTest.php`

**Interfaces:**
- Consumes: `Currency`, `FxSource`.
- Produces: `App\Models\CurrencyPair` with `base_currency_id`, `quote_currency_id`, `source` (cast `FxSource`), `is_active`, `note`; `belongsTo` baseCurrency/quoteCurrency; unique `(base_currency_id, quote_currency_id)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/CurrencyPairTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyPair::class)]
class CurrencyPairTest extends TestCase
{
    use RefreshDatabase;

    public function testPairRelatesToBaseAndQuoteCurrencies(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $pair = CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB,
        ]);

        $this->assertSame('USD', $pair->baseCurrency->code);
        $this->assertSame('CZK', $pair->quoteCurrency->code);
        $this->assertSame(FxSource::CNB, $pair->source);
        $this->assertTrue($pair->is_active);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=CurrencyPairTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000130_create_currency_pairs_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_pairs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('base_currency_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->foreignUuid('quote_currency_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->string('source');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['base_currency_id', 'quote_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_pairs');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/CurrencyPair.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FxSource;
use Database\Factories\CurrencyPairFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $base_currency_id
 * @property string $quote_currency_id
 * @property FxSource $source
 * @property bool $is_active
 * @property null|string $note
 */
class CurrencyPair extends Model
{
    /** @use HasFactory<CurrencyPairFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['base_currency_id', 'quote_currency_id', 'source', 'is_active', 'note'];

    /** @return BelongsTo<Currency, $this> */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'base_currency_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'quote_currency_id');
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'source' => FxSource::class,
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/CurrencyPairFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CurrencyPair> */
class CurrencyPairFactory extends Factory
{
    protected $model = CurrencyPair::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'base_currency_id' => Currency::factory(),
            'quote_currency_id' => Currency::factory(),
            'source' => FxSource::CNB,
            'is_active' => true,
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=CurrencyPairTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/CurrencyPair.php database/factories/CurrencyPairFactory.php tests/Unit/Models/CurrencyPairTest.php
git commit -m "feat: add CurrencyPair model, migration, factory"
```

---

## Task 9: FxRate model

**Files:**
- Create: `database/migrations/2026_07_18_000140_create_fx_rates_table.php`
- Create: `app/Models/FxRate.php`
- Create: `database/factories/FxRateFactory.php`
- Create: `tests/Unit/Models/FxRateTest.php`

**Interfaces:**
- Consumes: `Currency`.
- Produces: `App\Models\FxRate` with `currency_from_id`, `currency_to_id`, `rate` (string decimal), `rate_date` (cast date), `source` (string); `belongsTo` currencyFrom/currencyTo; unique `(currency_from_id, currency_to_id, rate_date, source)`; only `created_at` timestamp.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/FxRateTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxRate::class)]
class FxRateTest extends TestCase
{
    use RefreshDatabase;

    public function testFxRateStoresHighPrecisionRateAndRelations(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $rate = FxRate::factory()->create([
            'currency_from_id' => $usd->id,
            'currency_to_id' => $czk->id,
            'rate' => '23.1234567890',
            'rate_date' => '2026-03-15',
            'source' => 'cnb',
        ]);

        $this->assertSame('USD', $rate->currencyFrom->code);
        $this->assertSame('CZK', $rate->currencyTo->code);
        $this->assertSame('23.1234567890', $rate->rate);
        $this->assertSame('2026-03-15', $rate->rate_date->toDateString());
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=FxRateTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000140_create_fx_rates_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('currency_from_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->foreignUuid('currency_to_id')->index()->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 20, 10);
            $table->date('rate_date');
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['currency_from_id', 'currency_to_id', 'rate_date', 'source']);
            $table->index(['currency_from_id', 'currency_to_id', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/FxRate.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FxRateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $currency_from_id
 * @property string $currency_to_id
 * @property string $rate
 * @property \Illuminate\Support\Carbon $rate_date
 * @property string $source
 */
class FxRate extends Model
{
    /** @use HasFactory<FxRateFactory> */
    use HasFactory;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['currency_from_id', 'currency_to_id', 'rate', 'rate_date', 'source'];

    /** @return BelongsTo<Currency, $this> */
    public function currencyFrom(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'currency_from_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currencyTo(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'currency_to_id');
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'decimal:10',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/FxRateFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FxRate> */
class FxRateFactory extends Factory
{
    protected $model = FxRate::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'currency_from_id' => Currency::factory(),
            'currency_to_id' => Currency::factory(),
            'rate' => $this->faker->randomFloat(6, 1, 30),
            'rate_date' => $this->faker->date(),
            'source' => 'cnb',
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=FxRateTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/FxRate.php database/factories/FxRateFactory.php tests/Unit/Models/FxRateTest.php
git commit -m "feat: add FxRate model, migration, factory"
```

---

## Task 10: Transaction model

**Files:**
- Create: `database/migrations/2026_07_18_000150_create_transactions_table.php`
- Create: `app/Models/Transaction.php`
- Create: `database/factories/TransactionFactory.php`
- Create: `tests/Unit/Models/TransactionTest.php`

**Interfaces:**
- Consumes: `Account`, `TransactionType`.
- Produces: `App\Models\Transaction` with `account_id`, `type` (cast `TransactionType`), `amount` (decimal string), `transaction_date` (cast date), `note`, `counterparty`; `belongsTo` account; full timestamps.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/TransactionTest.php`:
```php
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

    public function testTransactionBelongsToAccountWithEnumAndDateCasts(): void
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
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=TransactionTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000150_create_transactions_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->index()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 20, 10);
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->string('counterparty')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Transaction.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property TransactionType $type
 * @property string $amount
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property null|string $note
 * @property null|string $counterparty
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['account_id', 'type', 'amount', 'transaction_date', 'note', 'counterparty'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transaction_date' => 'date',
            'amount' => 'decimal:10',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/TransactionFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'type' => $this->faker->randomElement(TransactionType::cases()),
            'amount' => $this->faker->randomFloat(2, 1, 10000),
            'transaction_date' => $this->faker->date(),
            'note' => null,
            'counterparty' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=TransactionTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Transaction.php database/factories/TransactionFactory.php tests/Unit/Models/TransactionTest.php
git commit -m "feat: add Transaction model, migration, factory"
```

---

## Task 11: Liability model

**Files:**
- Create: `database/migrations/2026_07_18_000160_create_liabilities_table.php`
- Create: `app/Models/Liability.php`
- Create: `database/factories/LiabilityFactory.php`
- Create: `tests/Unit/Models/LiabilityTest.php`

**Interfaces:**
- Consumes: `Institution`, `Currency`.
- Produces: `App\Models\Liability` with `institution_id`, `name`, `principal_amount`, `currency_id`, `interest_rate`, `monthly_payment` (nullable), `start_date`, `end_date` (nullable), `is_active`, `note`; `belongsTo` institution/currency; `hasMany` payments.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/LiabilityTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Liability::class)]
class LiabilityTest extends TestCase
{
    use RefreshDatabase;

    public function testLiabilityRelationsAndCasts(): void
    {
        $institution = Institution::factory()->create();
        $czk = Currency::factory()->create(['code' => 'CZK']);

        $liability = Liability::factory()->create([
            'institution_id' => $institution->id,
            'currency_id' => $czk->id,
            'start_date' => '2020-01-01',
        ]);

        $this->assertSame($institution->id, $liability->institution->id);
        $this->assertSame('CZK', $liability->currency->code);
        $this->assertSame('2020-01-01', $liability->start_date->toDateString());
        $this->assertTrue($liability->is_active);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=LiabilityTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000160_create_liabilities_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('principal_amount', 20, 10);
            $table->foreignUuid('currency_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('interest_rate', 8, 4);
            $table->decimal('monthly_payment', 20, 10)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Liability.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiabilityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $name
 * @property string $principal_amount
 * @property string $currency_id
 * @property string $interest_rate
 * @property null|string $monthly_payment
 * @property \Illuminate\Support\Carbon $start_date
 * @property null|\Illuminate\Support\Carbon $end_date
 * @property bool $is_active
 * @property null|string $note
 */
class Liability extends Model
{
    /** @use HasFactory<LiabilityFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'institution_id',
        'name',
        'principal_amount',
        'currency_id',
        'interest_rate',
        'monthly_payment',
        'start_date',
        'end_date',
        'is_active',
        'note',
    ];

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(related: Institution::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class);
    }

    /** @return HasMany<LiabilityPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(related: LiabilityPayment::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'principal_amount' => 'decimal:10',
            'monthly_payment' => 'decimal:10',
            'interest_rate' => 'decimal:4',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/LiabilityFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Liability> */
class LiabilityFactory extends Factory
{
    protected $model = Liability::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => $this->faker->words(3, true),
            'principal_amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'currency_id' => Currency::factory(),
            'interest_rate' => $this->faker->randomFloat(4, 1, 9),
            'monthly_payment' => $this->faker->randomFloat(2, 5000, 40000),
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=LiabilityTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Liability.php database/factories/LiabilityFactory.php tests/Unit/Models/LiabilityTest.php
git commit -m "feat: add Liability model, migration, factory"
```

---

## Task 12: LiabilityPayment model

**Files:**
- Create: `database/migrations/2026_07_18_000170_create_liability_payments_table.php`
- Create: `app/Models/LiabilityPayment.php`
- Create: `database/factories/LiabilityPaymentFactory.php`
- Create: `tests/Unit/Models/LiabilityPaymentTest.php`

**Interfaces:**
- Consumes: `Liability`.
- Produces: `App\Models\LiabilityPayment` with `liability_id`, `payment_date` (cast date), `total_amount`, `principal_portion` (nullable), `interest_portion` (nullable), `note`; `belongsTo` liability.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/LiabilityPaymentTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\LiabilityPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiabilityPayment::class)]
class LiabilityPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function testPaymentBelongsToLiabilityWithDateCast(): void
    {
        $payment = LiabilityPayment::factory()->create([
            'payment_date' => '2026-05-01',
            'total_amount' => '18000.0000000000',
        ]);

        $this->assertNotNull($payment->liability);
        $this->assertSame('2026-05-01', $payment->payment_date->toDateString());
        $this->assertSame('18000.0000000000', $payment->total_amount);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=LiabilityPaymentTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000170_create_liability_payments_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('liability_id')->index()->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('total_amount', 20, 10);
            $table->decimal('principal_portion', 20, 10)->nullable();
            $table->decimal('interest_portion', 20, 10)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['liability_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_payments');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/LiabilityPayment.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiabilityPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $liability_id
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string $total_amount
 * @property null|string $principal_portion
 * @property null|string $interest_portion
 * @property null|string $note
 */
class LiabilityPayment extends Model
{
    /** @use HasFactory<LiabilityPaymentFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'liability_id',
        'payment_date',
        'total_amount',
        'principal_portion',
        'interest_portion',
        'note',
    ];

    /** @return BelongsTo<Liability, $this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(related: Liability::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'total_amount' => 'decimal:10',
            'principal_portion' => 'decimal:10',
            'interest_portion' => 'decimal:10',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/LiabilityPaymentFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Liability;
use App\Models\LiabilityPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiabilityPayment> */
class LiabilityPaymentFactory extends Factory
{
    protected $model = LiabilityPayment::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'liability_id' => Liability::factory(),
            'payment_date' => $this->faker->date(),
            'total_amount' => $this->faker->randomFloat(2, 5000, 40000),
            'principal_portion' => null,
            'interest_portion' => null,
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=LiabilityPaymentTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/LiabilityPayment.php database/factories/LiabilityPaymentFactory.php tests/Unit/Models/LiabilityPaymentTest.php
git commit -m "feat: add LiabilityPayment model, migration, factory"
```

---

## Task 13: AccountBalanceSnapshot model

**Files:**
- Create: `database/migrations/2026_07_18_000180_create_account_balance_snapshots_table.php`
- Create: `app/Models/AccountBalanceSnapshot.php`
- Create: `database/factories/AccountBalanceSnapshotFactory.php`
- Create: `tests/Unit/Models/AccountBalanceSnapshotTest.php`

**Interfaces:**
- Consumes: `Account`.
- Produces: `App\Models\AccountBalanceSnapshot` with `account_id`, `balance`, `snapshot_date` (cast date), `note`; `belongsTo` account; unique `(account_id, snapshot_date)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/AccountBalanceSnapshotTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AccountBalanceSnapshot::class)]
class AccountBalanceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function testSnapshotBelongsToAccountWithDateCast(): void
    {
        $account = Account::factory()->create();

        $snapshot = AccountBalanceSnapshot::factory()->create([
            'account_id' => $account->id,
            'snapshot_date' => '2026-06-30',
            'balance' => '125000.0000000000',
        ]);

        $this->assertSame($account->id, $snapshot->account->id);
        $this->assertSame('2026-06-30', $snapshot->snapshot_date->toDateString());
        $this->assertSame('125000.0000000000', $snapshot->balance);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=AccountBalanceSnapshotTest
```
Expected: FAIL.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_18_000180_create_account_balance_snapshots_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balance_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 20, 10);
            $table->date('snapshot_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balance_snapshots');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/AccountBalanceSnapshot.php`:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountBalanceSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $balance
 * @property \Illuminate\Support\Carbon $snapshot_date
 * @property null|string $note
 */
class AccountBalanceSnapshot extends Model
{
    /** @use HasFactory<AccountBalanceSnapshotFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['account_id', 'balance', 'snapshot_date', 'note'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'balance' => 'decimal:10',
        ];
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/AccountBalanceSnapshotFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountBalanceSnapshot> */
class AccountBalanceSnapshotFactory extends Factory
{
    protected $model = AccountBalanceSnapshot::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 500000),
            'snapshot_date' => $this->faker->date(),
            'note' => null,
        ];
    }
}
```

- [ ] **Step 6: Run to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=AccountBalanceSnapshotTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/AccountBalanceSnapshot.php database/factories/AccountBalanceSnapshotFactory.php tests/Unit/Models/AccountBalanceSnapshotTest.php
git commit -m "feat: add AccountBalanceSnapshot model, migration, factory"
```

---

## Task 14: Sample data seeder

**Files:**
- Create: `database/seeders/SampleDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/SampleDataSeederTest.php`

**Interfaces:**
- Consumes: all models/factories from Tasks 5–13, `UserSeeder` from Task 3, `InstitutionType`/`AccountType`/`FxSource` enums.
- Produces: `SampleDataSeeder` creating currencies CZK/EUR/USD/GBP, ≥2 institutions, ≥3 accounts, currency pairs USD→CZK / EUR→CZK / USD→EUR, and a few transactions — so the app is usable immediately after `migrate --seed`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SampleDataSeederTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\Institution;
use App\Models\Transaction;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SampleDataSeeder::class)]
class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function testSeederCreatesUsableSampleData(): void
    {
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(4, Currency::query()->count());
        $this->assertNotNull(Currency::query()->where('code', 'CZK')->first());
        $this->assertGreaterThanOrEqual(2, Institution::query()->count());
        $this->assertGreaterThanOrEqual(3, Account::query()->count());
        $this->assertSame(3, CurrencyPair::query()->count());
        $this->assertGreaterThanOrEqual(1, Transaction::query()->count());
    }

    public function testSeederIsIdempotentForCurrencies(): void
    {
        $this->seed(SampleDataSeeder::class);
        $this->seed(SampleDataSeeder::class);

        $this->assertSame(4, Currency::query()->count());
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run:
```bash
./vendor/bin/sail artisan test --filter=SampleDataSeederTest
```
Expected: FAIL — `SampleDataSeeder` not found.

- [ ] **Step 3: Create `SampleDataSeeder`**

Create `database/seeders/SampleDataSeeder.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\FxSource;
use App\Enums\InstitutionType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\Institution;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [];
        foreach ([['CZK', 'Czech koruna'], ['EUR', 'Euro'], ['USD', 'US dollar'], ['GBP', 'Pound sterling']] as [$code, $name]) {
            $currencies[$code] = Currency::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $fio = Institution::query()->updateOrCreate(
            ['name' => 'Fio banka'],
            ['type' => InstitutionType::BANK],
        );
        $etoro = Institution::query()->updateOrCreate(
            ['name' => 'eToro'],
            ['type' => InstitutionType::BROKER],
        );

        $fioAccount = Account::query()->updateOrCreate(
            ['institution_id' => $fio->id, 'name' => 'Fio běžný účet'],
            ['currency_id' => $currencies['CZK']->id, 'type' => AccountType::BANK, 'is_active' => true],
        );
        Account::query()->updateOrCreate(
            ['institution_id' => $fio->id, 'name' => 'Fio spořicí účet'],
            ['currency_id' => $currencies['CZK']->id, 'type' => AccountType::SAVINGS, 'is_active' => true],
        );
        Account::query()->updateOrCreate(
            ['institution_id' => $etoro->id, 'name' => 'eToro USD'],
            ['currency_id' => $currencies['USD']->id, 'type' => AccountType::INVESTMENT, 'is_active' => true],
        );

        $pairs = [
            ['USD', 'CZK', FxSource::CNB],
            ['EUR', 'CZK', FxSource::CNB],
            ['USD', 'EUR', FxSource::FRANKFURTER],
        ];
        foreach ($pairs as [$from, $to, $source]) {
            CurrencyPair::query()->updateOrCreate(
                [
                    'base_currency_id' => $currencies[$from]->id,
                    'quote_currency_id' => $currencies[$to]->id,
                ],
                ['source' => $source, 'is_active' => true],
            );
        }

        if (Transaction::query()->where('account_id', $fioAccount->id)->doesntExist()) {
            Transaction::factory()->count(3)->create([
                'account_id' => $fioAccount->id,
                'type' => TransactionType::DEPOSIT,
            ]);
        }
    }
}
```

- [ ] **Step 4: Register `SampleDataSeeder` in `DatabaseSeeder`**

Update `database/seeders/DatabaseSeeder.php` `run()`:
```php
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
```

- [ ] **Step 5: Run the test to confirm pass**

Run:
```bash
./vendor/bin/sail artisan test --filter=SampleDataSeederTest
```
Expected: PASS (2 tests).

- [ ] **Step 6: Verify end-to-end migrate + seed on the real database**

Run:
```bash
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test
```
Expected: migration + seed complete without error; full test suite passes.

- [ ] **Step 7: Commit**

```bash
git add database/seeders tests/Feature/SampleDataSeederTest.php
git commit -m "feat: add sample data seeder for immediate app usability"
```

---

## Task 15: Static analysis and code style baseline

**Files:**
- Create/Modify: `phpstan.neon` (if using Larastan), `.php-cs-fixer or phpcs config` per project preference

**Interfaces:**
- Consumes: all code from Tasks 1–14.
- Produces: a green static-analysis + style baseline the later plans build on.

- [ ] **Step 1: Install Larastan and PHP CS tooling (dev)**

Run:
```bash
composer require --dev larastan/larastan
```
Create `phpstan.neon`:
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 6
    paths:
        - app
        - database
    checkModelProperties: true
```

- [ ] **Step 2: Run PHPStan**

Run:
```bash
./vendor/bin/sail php ./vendor/bin/phpstan analyse
```
Expected: `[OK] No errors`. If property-access errors appear on models, confirm the `@property` PHPDoc blocks match the columns; fix inline.

- [ ] **Step 3: Verify code style (Laravel Pint ships with Laravel)**

Run:
```bash
./vendor/bin/sail php ./vendor/bin/pint --test
```
Expected: no style violations. If any, run `./vendor/bin/sail php ./vendor/bin/pint` and re-check.

- [ ] **Step 4: Commit**

```bash
git add phpstan.neon composer.json composer.lock
git commit -m "chore: add PHPStan (Larastan) baseline and confirm Pint style"
```

---

## Self-Review

**Spec coverage (Foundation scope only — later plans cover the rest):**
- Docker Compose app/postgres/grafana + provisioning datasource → Tasks 1, 2 ✅
- PHP latest / Postgres latest / Laravel latest → Task 1 (Sail php84 image, `postgres:17`, Laravel 12) ✅
- Auth: single seeded user, registration/reset off → Task 3 ✅
- All 9 domain tables + `currency_pairs`, no crypto/`is_crypto` → Tasks 5–13 ✅
- UUID PK, FK indexes, decimal(20,10), enum casts → Global Constraints + Tasks 5–13 ✅
- Seed data (currencies, institutions, accounts, sample pairs, transactions) → Task 14 ✅
- `{Class}Test` per class → every model/enum/seeder task includes a test ✅
- **Deferred to later plans (out of scope here, by design):** repositories + DTOs + `CurrencyConverter` (Plan 2), FX sync providers/service/command + Livewire button (Plan 3), Livewire CRUD/transactions/payments/snapshots/dashboard (Plan 4), CSV import (Plan 5). Grafana *dashboards* remain out of scope per spec.

**Placeholder scan:** No TBD/TODO; every code step contains complete code; every command has an expected result. ✅

**Type consistency:** Relationship method names (`baseCurrency`/`quoteCurrency`, `currencyFrom`/`currencyTo`, `balanceSnapshots`, `payments`) are used identically in models and tests. Enum values match the spec and the enum tests. `config('app.seed_user')` shape matches `UserSeeder` usage. `decimal:10` casts return strings, matching the `assertSame('...', $model->amount)` string assertions in tests. ✅

**Note for the implementer:** `foreignUuid(...)->constrained()` adds the FK constraint; the explicit `->index()` satisfies the PostgreSQL FK-index rule. `public const UPDATED_AT = null;` on `FxRate` disables the `updated_at` column (created_at only), matching the spec.
