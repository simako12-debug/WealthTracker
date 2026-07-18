# CRUD UI Foundation (Livewire 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the authenticated Livewire 4 write-UI shell and the generic CRUD pattern (repository + Spatie Data DTO + Livewire Form + full-page component with datatable & modal), demonstrated on two entities — **Institution** and **Currency** — plus a **FX sync button** on the dashboard. This is the first vertical slice of the write UI; the remaining CRUD entities (accounts, liabilities, currency_pairs) follow the same pattern in the next plan.

**Architecture:** Every CRUD screen is a full-page Livewire 4 class component (`#[Layout('layouts.app')]`, routed via `Route::get('/x', Component::class)`), using `WithPagination`. Reads and writes go through a `final readonly {Entity}Repository` (bound via interface) that returns Spatie Data DTOs (`paginate` returns a `LengthAwarePaginator` of DTOs). Form input is held in a `Livewire\Form` object with validation; on save the component passes the form's validated attributes to the repository. UI uses the Breeze/Tailwind blade components already present (`x-modal`, `x-primary-button`, `x-text-input`, `x-input-label`, `x-input-error`, `x-danger-button`). The app is deliberately plain — no extra component framework, minimal styling consistent with Breeze defaults.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4.3, Breeze (Blade + Tailwind), Spatie Laravel Data 4, PostgreSQL, PHPUnit, PHPStan (Larastan) level 6, Pint. All commands run inside the container via `./vendor/bin/sail` (ignore `WWWUSER/WWWGROUP` warnings).

## Global Constraints

- **`declare(strict_types=1);`** every PHP file; type-hint everything.
- **Livewire 4 conventions (verified in this repo — see `.superpowers/sdd/livewire4-conventions.md`):**
  - Create components with `./vendor/bin/sail artisan make:livewire <Name> --class` → class at `app/Livewire/<Name>.php`, view at `resources/views/livewire/<kebab-name>.blade.php`.
  - Full-page: `#[\Livewire\Attributes\Layout('layouts.app')]` on the component class; route `Route::get('/path', <Name>::class)->name('...')` inside the `auth` middleware group.
  - Pagination: `use Livewire\WithPagination;` + `{{ $items->links() }}`.
  - Forms: a `Livewire\Form` subclass in `app/Livewire/Forms/`, created via `./vendor/bin/sail artisan make:livewire:form <Name>Form`; validation via a `rules()` method (needed for enum rules) and/or `#[Validate]`.
  - Tests: `Livewire\Livewire::actingAs($user)->test(<Name>::class)->set(...)->call(...)->assertHasNoErrors()/->assertSee()/->assertDispatched()`.
- **Repository pattern for CRUD (user decision — repo+DTO everywhere):** `{Entity}RepositoryInterface` + `final readonly {Entity}Repository`; reads return Spatie Data DTOs (`paginate` → `LengthAwarePaginator` whose items are DTOs via `->through()`); writes accept a validated `array<string,mixed>` and return the entity DTO; `delete` returns `void`. Bind interface→impl in `RepositoryServiceProvider`. Wrap multi-statement writes in `DB::transaction()` (single writes need not).
- **DTOs:** `final extends Spatie\LaravelData\Data`, constructed explicitly via a `fromModel()` static; enums typed as `App\Enums\*`, `null|string` union order.
- **Dependency injection into components:** inject repositories/services as method parameters on `render()` and action methods (Livewire resolves them from the container) — do NOT constructor-inject Livewire components.
- **Auth:** every new route sits behind the `auth` middleware. Tests authenticate via `Livewire::actingAs(User::factory()->create())`.
- **Comparisons** `=== false/true/null`; array emptiness `empty($x) === true`.
- **Baseline stays green:** phpstan `[OK]`, pint `--test` clean, full suite passing.
- **Reuse Plan-2 code unchanged where it exists:** `App\Data\CurrencyData(id, code, name)` and `CurrencyRepository` (which already has `all()`/`findByCode()` used by the converter — EXTEND it, do not break those).

## File Structure

```
app/Data/InstitutionData.php
app/Repositories/InstitutionRepositoryInterface.php
app/Repositories/InstitutionRepository.php
app/Livewire/Forms/InstitutionForm.php
app/Livewire/ManageInstitutions.php
resources/views/livewire/manage-institutions.blade.php
app/Livewire/Forms/CurrencyForm.php
app/Livewire/ManageCurrencies.php
resources/views/livewire/manage-currencies.blade.php
app/Livewire/FxSyncButton.php
resources/views/livewire/fx-sync-button.blade.php
app/Repositories/CurrencyRepositoryInterface.php   # EXTEND (add CRUD methods)
app/Repositories/CurrencyRepository.php            # EXTEND
app/Providers/RepositoryServiceProvider.php        # add Institution binding
routes/web.php                                      # add auth routes
resources/views/layouts/navigation.blade.php        # add nav links
resources/views/dashboard.blade.php                 # embed FxSyncButton
tests/Feature/Livewire/ManageInstitutionsTest.php
tests/Feature/Livewire/ManageCurrenciesTest.php
tests/Feature/Livewire/FxSyncButtonTest.php
tests/Unit/Repositories/InstitutionRepositoryTest.php
tests/Unit/Repositories/CurrencyRepositoryCrudTest.php
```

---

## Task 1: Institution DTO + CRUD repository

**Files:**
- Create: `app/Data/InstitutionData.php`, `app/Repositories/InstitutionRepositoryInterface.php`, `app/Repositories/InstitutionRepository.php`
- Modify: `app/Providers/RepositoryServiceProvider.php`
- Test: `tests/Unit/Repositories/InstitutionRepositoryTest.php`

**Interfaces:**
- Consumes: `App\Models\Institution`, `App\Enums\InstitutionType`.
- Produces: `App\Data\InstitutionData(string $id, string $name, InstitutionType $type, null|string $note)`; `InstitutionRepositoryInterface` with `paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator`, `find(string $id): ?InstitutionData`, `create(array $attributes): InstitutionData`, `update(string $id, array $attributes): InstitutionData`, `delete(string $id): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/InstitutionRepositoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\InstitutionData;
use App\Enums\InstitutionType;
use App\Models\Institution;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\InstitutionRepository::class)]
class InstitutionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): InstitutionRepositoryInterface
    {
        return $this->app->make(InstitutionRepositoryInterface::class);
    }

    public function testPaginateReturnsDataObjects(): void
    {
        Institution::factory()->create(['name' => 'Alpha']);
        Institution::factory()->create(['name' => 'Beta']);

        $page = $this->repository()->paginate('name', 'asc', 15);

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertCount(2, $page->items());
        $this->assertContainsOnlyInstancesOf(InstitutionData::class, $page->items());
        $this->assertSame('Alpha', $page->items()[0]->name);
    }

    public function testCreatePersistsAndReturnsData(): void
    {
        $data = $this->repository()->create([
            'name' => 'Fio banka',
            'type' => InstitutionType::BANK->value,
            'note' => null,
        ]);

        $this->assertInstanceOf(InstitutionData::class, $data);
        $this->assertSame('Fio banka', $data->name);
        $this->assertSame(InstitutionType::BANK, $data->type);
        $this->assertDatabaseHas('institutions', ['name' => 'Fio banka', 'type' => 'bank']);
    }

    public function testUpdateChangesRow(): void
    {
        $institution = Institution::factory()->create(['name' => 'Old', 'type' => InstitutionType::BANK]);

        $data = $this->repository()->update($institution->id, [
            'name' => 'New',
            'type' => InstitutionType::BROKER->value,
            'note' => 'moved',
        ]);

        $this->assertSame('New', $data->name);
        $this->assertSame(InstitutionType::BROKER, $data->type);
        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'name' => 'New', 'type' => 'broker']);
    }

    public function testFindAndDelete(): void
    {
        $institution = Institution::factory()->create();

        $this->assertInstanceOf(InstitutionData::class, $this->repository()->find($institution->id));

        $this->repository()->delete($institution->id);

        $this->assertNull($this->repository()->find($institution->id));
        $this->assertDatabaseMissing('institutions', ['id' => $institution->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=InstitutionRepositoryTest`
Expected: FAIL — interface/binding missing.

- [ ] **Step 3: Create the DTO**

Create `app/Data/InstitutionData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Spatie\LaravelData\Data;

final class InstitutionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public InstitutionType $type,
        public null|string $note,
    ) {
    }

    public static function fromModel(Institution $institution): self
    {
        return new self(
            id: $institution->id,
            name: $institution->name,
            type: $institution->type,
            note: $institution->note,
        );
    }
}
```

- [ ] **Step 4: Create the interface**

Create `app/Repositories/InstitutionRepositoryInterface.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\InstitutionData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InstitutionRepositoryInterface
{
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): InstitutionData;

    public function delete(string $id): void;
}
```

- [ ] **Step 5: Create the implementation**

Create `app/Repositories/InstitutionRepository.php`:
```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\InstitutionData;
use App\Models\Institution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class InstitutionRepository implements InstitutionRepositoryInterface
{
    private const array SORTABLE = ['name', 'type', 'created_at'];

    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'name';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Institution::query()
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Institution $institution): InstitutionData => InstitutionData::fromModel($institution));
    }

    public function find(string $id): ?InstitutionData
    {
        $institution = Institution::query()->find($id);

        return $institution === null ? null : InstitutionData::fromModel($institution);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InstitutionData
    {
        return InstitutionData::fromModel(Institution::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): InstitutionData
    {
        $institution = Institution::query()->findOrFail($id);
        $institution->update($attributes);

        return InstitutionData::fromModel($institution);
    }

    public function delete(string $id): void
    {
        Institution::query()->where('id', $id)->delete();
    }
}
```

- [ ] **Step 6: Register the binding**

In `app/Providers/RepositoryServiceProvider.php`, add to `$bindings` (with the `use` imports):
```php
        InstitutionRepositoryInterface::class => InstitutionRepository::class,
```

- [ ] **Step 7: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=InstitutionRepositoryTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Data/InstitutionData.php app/Repositories/InstitutionRepositoryInterface.php app/Repositories/InstitutionRepository.php app/Providers/RepositoryServiceProvider.php tests/Unit/Repositories/InstitutionRepositoryTest.php
git commit -m "feat: add Institution DTO + CRUD repository"
```

---

## Task 2: ManageInstitutions Livewire component + form + route + nav

**Files:**
- Create: `app/Livewire/Forms/InstitutionForm.php`, `app/Livewire/ManageInstitutions.php`, `resources/views/livewire/manage-institutions.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageInstitutionsTest.php`

**Interfaces:**
- Consumes: `InstitutionRepositoryInterface` (Task 1), `InstitutionType`, Breeze layout/components.
- Produces: route `institutions` (GET `/institutions`, auth) rendering `App\Livewire\ManageInstitutions`; a working list + create/edit modal + delete.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageInstitutionsTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\InstitutionType;
use App\Livewire\ManageInstitutions;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageInstitutions::class)]
class ManageInstitutionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function testGuestCannotAccessRoute(): void
    {
        $this->get('/institutions')->assertRedirect('/login');
    }

    public function testListsInstitutions(): void
    {
        Institution::factory()->create(['name' => 'Fio banka']);

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->assertOk()
            ->assertSee('Fio banka');
    }

    public function testCreateInstitution(): void
    {
        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('create')
            ->set('form.name', 'eToro')
            ->set('form.type', InstitutionType::BROKER->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('institutions', ['name' => 'eToro', 'type' => 'broker']);
    }

    public function testValidationFailsWithoutName(): void
    {
        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('create')
            ->set('form.name', '')
            ->set('form.type', InstitutionType::BANK->value)
            ->call('save')
            ->assertHasErrors(['form.name']);
    }

    public function testEditInstitution(): void
    {
        $institution = Institution::factory()->create(['name' => 'Old', 'type' => InstitutionType::BANK]);

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('edit', $institution->id)
            ->assertSet('form.name', 'Old')
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'name' => 'Renamed']);
    }

    public function testDeleteInstitution(): void
    {
        $institution = Institution::factory()->create();

        Livewire::actingAs($this->actingUser())
            ->test(ManageInstitutions::class)
            ->call('delete', $institution->id);

        $this->assertDatabaseMissing('institutions', ['id' => $institution->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=ManageInstitutionsTest`
Expected: FAIL — component/route missing.

- [ ] **Step 3: Scaffold the form and component**

Run:
```bash
./vendor/bin/sail artisan make:livewire:form InstitutionForm
./vendor/bin/sail artisan make:livewire ManageInstitutions --class
```
(These generate the correct Livewire-4 skeletons — then replace their contents in the next steps.)

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/InstitutionForm.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\InstitutionData;
use App\Enums\InstitutionType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class InstitutionForm extends Form
{
    public null|string $id = null;

    public string $name = '';

    public null|string $type = null;

    public null|string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstitutionType::class)],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setInstitution(InstitutionData $data): void
    {
        $this->id = $data->id;
        $this->name = $data->name;
        $this->type = $data->type->value;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'note' => $this->note,
        ];
    }
}
```

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageInstitutions.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\InstitutionType;
use App\Livewire\Forms\InstitutionForm;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageInstitutions extends Component
{
    use WithPagination;

    public InstitutionForm $form;

    public bool $showModal = false;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, InstitutionRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setInstitution($data);
        $this->showModal = true;
    }

    public function save(InstitutionRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Institution saved.');
    }

    public function delete(string $id, InstitutionRepositoryInterface $repository): void
    {
        $repository->delete($id);
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

    public function render(InstitutionRepositoryInterface $repository): View
    {
        return view('livewire.manage-institutions', [
            'institutions' => $repository->paginate($this->sortField, $this->sortDirection, 15),
            'types' => InstitutionType::cases(),
        ]);
    }
}
```

- [ ] **Step 6: Write the view**

Replace `resources/views/livewire/manage-institutions.blade.php` with:
```blade
<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Institutions</h1>
            <x-primary-button wire:click="create">New institution</x-primary-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('name')">Name</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('type')">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Note</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($institutions as $institution)
                        <tr wire:key="institution-{{ $institution->id }}">
                            <td class="px-4 py-3 text-sm">{{ $institution->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ ucfirst($institution->type->value) }}</td>
                            <td class="px-4 py-3 text-sm">{{ $institution->note }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit('{{ $institution->id }}')" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete('{{ $institution->id }}')" wire:confirm="Delete this institution?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $institutions->links() }}</div>

        <x-modal name="institution-modal" :show="$showModal" focusable>
            <form wire:submit="save" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $form->id === null ? 'New institution' : 'Edit institution' }}
                </h2>

                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" wire:model="form.name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('form.name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="type" value="Type" />
                    <select id="type" wire:model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="note" value="Note" />
                    <textarea id="note" wire:model="form.note" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"></textarea>
                    <x-input-error :messages="$errors->get('form.note')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" wire:click="$set('showModal', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</div>
```

- [ ] **Step 7: Add the route**

In `routes/web.php`, inside the existing `auth` middleware group (the one containing `/dashboard`), add:
```php
    Route::get('/institutions', \App\Livewire\ManageInstitutions::class)->name('institutions');
```
(Keep `declare(strict_types=1);` at the top of the file if present; add it if not.)

- [ ] **Step 8: Add the nav links**

In `resources/views/layouts/navigation.blade.php`, add a desktop link next to the existing Dashboard `<x-nav-link>`:
```blade
                <x-nav-link :href="route('institutions')" :active="request()->routeIs('institutions')">
                    {{ __('Institutions') }}
                </x-nav-link>
```
and a responsive link next to the existing Dashboard `<x-responsive-nav-link>`:
```blade
                <x-responsive-nav-link :href="route('institutions')" :active="request()->routeIs('institutions')">
                    {{ __('Institutions') }}
                </x-responsive-nav-link>
```

- [ ] **Step 9: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=ManageInstitutionsTest`
Expected: PASS (6 tests). If the `x-modal`'s `:show` binding or a Breeze component differs from what's used here, adapt to the actual Breeze component API (consult `.superpowers/sdd/livewire4-conventions.md`) — the tests are the behavioral contract.

- [ ] **Step 10: Commit**

```bash
git add app/Livewire/Forms/InstitutionForm.php app/Livewire/ManageInstitutions.php resources/views/livewire/manage-institutions.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ManageInstitutionsTest.php
git commit -m "feat: add Institutions CRUD Livewire screen (list/create/edit/delete)"
```

---

## Task 3: Extend CurrencyRepository with CRUD

**Files:**
- Modify: `app/Repositories/CurrencyRepositoryInterface.php`, `app/Repositories/CurrencyRepository.php`
- Test: `tests/Unit/Repositories/CurrencyRepositoryCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Currency`, existing `App\Data\CurrencyData`.
- Produces: adds `paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator`, `find(string $id): ?CurrencyData`, `create(array $attributes): CurrencyData`, `update(string $id, array $attributes): CurrencyData`, `delete(string $id): void` to the existing interface/impl (keeping `all()` and `findByCode()`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Repositories/CurrencyRepositoryCrudTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Repositories\CurrencyRepository::class)]
class CurrencyRepositoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyRepositoryInterface
    {
        return $this->app->make(CurrencyRepositoryInterface::class);
    }

    public function testPaginateFindCreateUpdateDelete(): void
    {
        Currency::factory()->create(['code' => 'AAA']);
        $page = $this->repository()->paginate('code', 'asc', 15);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertContainsOnlyInstancesOf(CurrencyData::class, $page->items());

        $created = $this->repository()->create(['code' => 'CZK', 'name' => 'Czech koruna']);
        $this->assertSame('CZK', $created->code);
        $this->assertDatabaseHas('currencies', ['code' => 'CZK']);

        $found = $this->repository()->find($created->id);
        $this->assertInstanceOf(CurrencyData::class, $found);

        $updated = $this->repository()->update($created->id, ['code' => 'CZK', 'name' => 'Koruna']);
        $this->assertSame('Koruna', $updated->name);

        $this->repository()->delete($created->id);
        $this->assertNull($this->repository()->find($created->id));
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=CurrencyRepositoryCrudTest`
Expected: FAIL — methods missing.

- [ ] **Step 3: Extend the interface**

In `app/Repositories/CurrencyRepositoryInterface.php`, add (keeping `all()` and `findByCode()`, and importing `LengthAwarePaginator`):
```php
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?CurrencyData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyData;

    public function delete(string $id): void;
```

- [ ] **Step 4: Extend the implementation**

In `app/Repositories/CurrencyRepository.php`, add the methods (keep the existing `all()`/`findByCode()`; import `LengthAwarePaginator`):
```php
    private const array SORTABLE = ['code', 'name', 'created_at'];

    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'code';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Currency::query()
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Currency $currency): CurrencyData => CurrencyData::fromModel($currency));
    }

    public function find(string $id): ?CurrencyData
    {
        $currency = Currency::query()->find($id);

        return $currency === null ? null : CurrencyData::fromModel($currency);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyData
    {
        return CurrencyData::fromModel(Currency::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyData
    {
        $currency = Currency::query()->findOrFail($id);
        $currency->update($attributes);

        return CurrencyData::fromModel($currency);
    }

    public function delete(string $id): void
    {
        Currency::query()->where('id', $id)->delete();
    }
```
(Ensure `Currency` has `code`, `name` in `$fillable` — it does from Plan 1.)

- [ ] **Step 5: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=CurrencyRepositoryCrudTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Repositories/CurrencyRepositoryInterface.php app/Repositories/CurrencyRepository.php tests/Unit/Repositories/CurrencyRepositoryCrudTest.php
git commit -m "feat: extend CurrencyRepository with CRUD methods"
```

---

## Task 4: ManageCurrencies Livewire component + form + route + nav

**Files:**
- Create: `app/Livewire/Forms/CurrencyForm.php`, `app/Livewire/ManageCurrencies.php`, `resources/views/livewire/manage-currencies.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/Livewire/ManageCurrenciesTest.php`

**Interfaces:**
- Consumes: `CurrencyRepositoryInterface` (Task 3).
- Produces: route `currencies` (GET `/currencies`, auth) rendering `App\Livewire\ManageCurrencies`; list + create/edit modal + delete. `code` is uppercased and unique.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/ManageCurrenciesTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\ManageCurrencies;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ManageCurrencies::class)]
class ManageCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotAccessRoute(): void
    {
        $this->get('/currencies')->assertRedirect('/login');
    }

    public function testCreateCurrencyUppercasesCode(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('create')
            ->set('form.code', 'usd')
            ->set('form.name', 'US dollar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'name' => 'US dollar']);
    }

    public function testCodeMustBeUnique(): void
    {
        Currency::factory()->create(['code' => 'EUR']);

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('create')
            ->set('form.code', 'EUR')
            ->set('form.name', 'Euro')
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function testDeleteCurrency(): void
    {
        $currency = Currency::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(ManageCurrencies::class)
            ->call('delete', $currency->id);

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=ManageCurrenciesTest`
Expected: FAIL.

- [ ] **Step 3: Scaffold**

```bash
./vendor/bin/sail artisan make:livewire:form CurrencyForm
./vendor/bin/sail artisan make:livewire ManageCurrencies --class
```

- [ ] **Step 4: Write the form**

Replace `app/Livewire/Forms/CurrencyForm.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\CurrencyData;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CurrencyForm extends Form
{
    public null|string $id = null;

    public string $code = '';

    public string $name = '';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('currencies', 'code')->ignore($this->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function setCurrency(CurrencyData $data): void
    {
        $this->id = $data->id;
        $this->code = $data->code;
        $this->name = $data->name;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'code' => strtoupper($this->code),
            'name' => $this->name,
        ];
    }
}
```
Note: `toAttributes()` uppercases `code`; the unique rule validates the raw input, so also normalise before validation by uppercasing in the component's `save()` (see Step 5) OR rely on case-sensitive DB uniqueness. To keep the `testCodeMustBeUnique` test meaningful (input `'EUR'` equals stored `'EUR'`), the unique rule on the raw value is sufficient here.

- [ ] **Step 5: Write the component**

Replace `app/Livewire/ManageCurrencies.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Forms\CurrencyForm;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageCurrencies extends Component
{
    use WithPagination;

    public CurrencyForm $form;

    public bool $showModal = false;

    public string $sortField = 'code';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, CurrencyRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setCurrency($data);
        $this->showModal = true;
    }

    public function save(CurrencyRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Currency saved.');
    }

    public function delete(string $id, CurrencyRepositoryInterface $repository): void
    {
        $repository->delete($id);
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

    public function render(CurrencyRepositoryInterface $repository): View
    {
        return view('livewire.manage-currencies', [
            'currencies' => $repository->paginate($this->sortField, $this->sortDirection, 15),
        ]);
    }
}
```

- [ ] **Step 6: Write the view**

Replace `resources/views/livewire/manage-currencies.blade.php` with:
```blade
<div class="py-8">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Currencies</h1>
            <x-primary-button wire:click="create">New currency</x-primary-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('code')">Code</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('name')">Name</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($currencies as $currency)
                        <tr wire:key="currency-{{ $currency->id }}">
                            <td class="px-4 py-3 text-sm font-mono">{{ $currency->code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $currency->name }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit('{{ $currency->id }}')" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete('{{ $currency->id }}')" wire:confirm="Delete this currency?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $currencies->links() }}</div>

        <x-modal name="currency-modal" :show="$showModal" focusable>
            <form wire:submit="save" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $form->id === null ? 'New currency' : 'Edit currency' }}
                </h2>

                <div>
                    <x-input-label for="code" value="Code (ISO 4217)" />
                    <x-text-input id="code" wire:model="form.code" class="mt-1 block w-full uppercase" maxlength="10" />
                    <x-input-error :messages="$errors->get('form.code')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" wire:model="form.name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('form.name')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" wire:click="$set('showModal', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</div>
```

- [ ] **Step 7: Route + nav**

In `routes/web.php` (auth group) add:
```php
    Route::get('/currencies', \App\Livewire\ManageCurrencies::class)->name('currencies');
```
In `resources/views/layouts/navigation.blade.php` add desktop + responsive nav links for `currencies` (mirroring the Institutions links from Task 2).

- [ ] **Step 8: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=ManageCurrenciesTest`
Expected: PASS (3 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Forms/CurrencyForm.php app/Livewire/ManageCurrencies.php resources/views/livewire/manage-currencies.blade.php routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/Livewire/ManageCurrenciesTest.php
git commit -m "feat: add Currencies CRUD Livewire screen"
```

---

## Task 5: FX Sync button on the dashboard

**Files:**
- Create: `app/Livewire/FxSyncButton.php`, `resources/views/livewire/fx-sync-button.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Livewire/FxSyncButtonTest.php`

**Interfaces:**
- Consumes: `App\Services\Fx\FxSyncService` (Plan 3).
- Produces: `App\Livewire\FxSyncButton` — a button that runs `FxSyncService::sync()` and shows the `synced`/`skipped` summary; embedded on the dashboard.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Livewire/FxSyncButtonTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\FxSource;
use App\Livewire\FxSyncButton;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Models\FxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FxSyncButton::class)]
class FxSyncButtonTest extends TestCase
{
    use RefreshDatabase;

    public function testSyncFetchesAndReportsSummary(): void
    {
        Http::fake(['*cnb.cz*' => Http::response("18.07.2026 #137\nzemě|měna|množství|kód|kurz\nUSA|dolar|1|USD|23,100\n", 200)]);

        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create([
            'base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id,
            'source' => FxSource::CNB, 'is_active' => true,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(FxSyncButton::class)
            ->call('sync')
            ->assertSee('1 synced');

        $this->assertSame(1, FxRate::query()->count());
    }
}
```

- [ ] **Step 2: Run to confirm failure**

Run: `./vendor/bin/sail artisan test --filter=FxSyncButtonTest`
Expected: FAIL.

- [ ] **Step 3: Scaffold + write the component**

Run: `./vendor/bin/sail artisan make:livewire FxSyncButton --class`
Replace `app/Livewire/FxSyncButton.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Fx\FxSyncService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FxSyncButton extends Component
{
    public null|string $result = null;

    public function sync(FxSyncService $service): void
    {
        $outcome = $service->sync();

        $this->result = "{$outcome->synced} synced, {$outcome->skipped} skipped.";
    }

    public function render(): View
    {
        return view('livewire.fx-sync-button');
    }
}
```

- [ ] **Step 4: Write the view**

Replace `resources/views/livewire/fx-sync-button.blade.php` with:
```blade
<div class="flex items-center gap-3">
    <x-primary-button wire:click="sync" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="sync">Sync FX rates</span>
        <span wire:loading wire:target="sync">Syncing…</span>
    </x-primary-button>

    @if ($result !== null)
        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $result }}</span>
    @endif
</div>
```

- [ ] **Step 5: Embed on the dashboard**

In `resources/views/dashboard.blade.php`, inside the main content card (below the existing "You're logged in!" text), add:
```blade
                <div class="mt-4">
                    <livewire:fx-sync-button />
                </div>
```

- [ ] **Step 6: Run to confirm pass**

Run: `./vendor/bin/sail artisan test --filter=FxSyncButtonTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/FxSyncButton.php resources/views/livewire/fx-sync-button.blade.php resources/views/dashboard.blade.php tests/Feature/Livewire/FxSyncButtonTest.php
git commit -m "feat: add FX sync button to dashboard"
```

---

## Task 6: Static analysis, style, full-suite + route smoke

**Files:** none (verification + any inline fixes).

- [ ] **Step 1: PHPStan** — `./vendor/bin/sail php ./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`. Livewire components may need PHPDoc/type touch-ups (e.g. the `$form` property, paginator generics). Fix inline; no blanket ignores. If Larastan needs the Livewire extension or a genuine false positive blocks you, STOP and report NEEDS_CONTEXT.
- [ ] **Step 2: Pint** — `./vendor/bin/sail php ./vendor/bin/pint` then `./vendor/bin/sail php ./vendor/bin/pint --test` → clean.
- [ ] **Step 3: Full suite** — `./vendor/bin/sail artisan test` → all pass, pristine.
- [ ] **Step 4: Route smoke** — `./vendor/bin/sail artisan route:list | grep -E 'institutions|currencies'` shows both routes bound to the Livewire components. `./vendor/bin/sail npm run build` succeeds (assets compile).
- [ ] **Step 5: Commit (only if Step 1/2 changed files)**

```bash
git add -A
git commit -m "chore: satisfy phpstan/pint for CRUD UI foundation"
```

---

## Self-Review

**Spec coverage (spec §6.1 generic CRUD — first slice):**
- Generic CRUD (table + modal, sort + pagination, no complex filters) → Institutions (Tasks 1–2) + Currencies (Tasks 3–4) ✅
- Repo+DTO for CRUD (user decision) → all CRUD via `{Entity}RepositoryInterface` returning DTOs ✅
- Livewire 4 full-page components with Breeze layout + nav → Tasks 2, 4 ✅
- FX sync button that runs the sync and reports result → Task 5 (spec §5 "tlačítko v appce") ✅
- Auth-gated → all routes in the `auth` group; guest-redirect tested ✅
- **Deferred to the next plan(s):** CRUD for accounts, liabilities, currency_pairs (same pattern, FK selects); transactions/liability_payments/snapshots/dashboard widgets; CSV import.

**Placeholder scan:** no TBD/TODO; complete code in every step; commands have expected outputs. The one adaptive note (Breeze `x-modal` API) points to the verified conventions file and makes the tests the contract — not a placeholder. ✅

**Type consistency:** repository CRUD signatures identical across Institution and (extended) Currency (`paginate/find/create/update/delete`); components use identical action shape (`create/edit/save/delete/sortBy`) and method-injected repositories; forms expose `setX()`/`toAttributes()`; `FxSyncService::sync()` returns `FxSyncResult{synced, skipped}` as consumed by the button. `paginate(...)->through(...)` yields DTO-item paginators consumed by `$items->links()` in views. ✅

**Notes for the implementer:**
- Consult `.superpowers/sdd/livewire4-conventions.md` for exact Livewire-4 idioms; if a Breeze component's props differ from the view code here, adapt the view and keep the tests green (tests are the contract).
- If PHPStan lacks Livewire property understanding for `public InstitutionForm $form;`, add a precise `@property`/type or the Livewire PHPStan handling — do not weaken types.
- `make:livewire ... --class` and `make:livewire:form ...` must be run before replacing file contents so the framework registers them correctly.
```
