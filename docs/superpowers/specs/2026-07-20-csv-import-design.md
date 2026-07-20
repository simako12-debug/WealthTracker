# CSV Import historických dat — návrh (design spec)

- **Datum:** 2026-07-20
- **Stav:** schváleno k implementaci
- **Autor:** petr + Claude (brainstorming)
- **Navazuje na:** `2026-07-18-osobni-financni-evidence-design.md` §6.5 (poslední zbývající feature zápisové vrstvy)

## 1. Cíl a rozsah

Hromadný import historických dat z Excelu (přeloženého do CSV) pro tři entity:

- **transakce** (`transactions`)
- **hodnoty účtu k datu** (`account_balance_snapshots`)
- **splátky závazků** (`liability_payments`)

Číselníky (instituce, účty, měny, závazky) se **zakládají ručně přes CRUD před importem** — import je nezakládá, jen dohledává.

### Klíčová rozhodnutí z brainstormingu

1. **Pevná šablona per entita** (ne flexibilní mapování sloupců). Každá entita má přesně dané názvy sloupců; uživatel přizpůsobí Excel šabloně. Ke každé entitě jde stáhnout vzorové CSV. (Flexibilní mapování a importy exportů z brokerů/bank jsou mimo rozsah — viz §8.)
2. **ISO formáty:** desetinná tečka (`1234.56`), datum `YYYY-MM-DD`.
3. **Dohledání FK podle přirozených klíčů:** účet = (název instituce + název účtu), závazek = název. Nenalezený FK → chyba řádku (žádné auto-vytváření).
4. **Chybné řádky:** validuje se vše; náhled ukáže každý řádek se stavem; import založí **jen platné řádky**, chybné přeskočí a vypíše s důvodem.
5. **Idempotence (přeskakování duplicit), default zapnuto:**
   - snapshoty → **upsert** na unikátní klíč `(account_id, snapshot_date)`
   - transakce → přeskoč, pokud existuje řádek se shodou na `(account_id, transaction_date, type, amount, counterparty)`
   - splátky → přeskoč, pokud existuje řádek se shodou na `(liability_id, payment_date, total_amount)`

## 2. Architektura

Kopíruje zavedené vrstvení appky (service = testovatelná logika mimo Livewire; Livewire = prezentace; repository = přístup k datům, vrací DTO).

- **`App\Enums\ImportTarget`** — backed string enum: `TRANSACTIONS` (`transactions`), `ACCOUNT_SNAPSHOTS` (`account_snapshots`), `LIABILITY_PAYMENTS` (`liability_payments`). Nese `label()` (přes `HasLabel`) a je jediným zdrojem pravdy o tom, které cíle existují.
- **`App\Services\CsvImport\ImportTargetDefinition`** — per cíl popisuje: seznam sloupců šablony (název + required/optional), validační pravidla řádku, jak z řádku složit atributy modelu, klíč pro detekci duplicit, a jednu ukázkovou řádku pro vzorové CSV. Tři konkrétní definice (jedna per `ImportTarget`), vybírané přes `ImportTargetDefinition::for(ImportTarget)`.
- **`App\Services\CsvImportService`** — čistá logika:
  - `parse(string $csvContents): array<int, array<string,string>>` — naparsuje CSV na asociativní řádky dle hlavičky (oddělovač `,`, UTF-8, ořez BOM).
  - `preview(ImportTarget $target, string $csvContents, bool $skipDuplicates): ImportPreview` — zvaliduje **všechny** řádky, dohledá FK, označí duplicity; vrátí `ImportPreview` (viz DTO níže) BEZ zápisu do DB.
  - `import(ImportTarget $target, string $csvContents, bool $skipDuplicates): ImportResult` — provede import platných řádků v jedné `DB::transaction()`; vrátí souhrn.
- **DTO (Spatie Data):**
  - `App\Data\Import\ImportRowResult(int $line, array<string,string> $raw, string $status, ?string $error)` — `status` ∈ `valid` | `duplicate` | `error`.
  - `App\Data\Import\ImportPreview(int $total, Collection<int,ImportRowResult> $rows, int $validCount, int $duplicateCount, int $errorCount)`.
  - `App\Data\Import\ImportResult(int $imported, int $skipped, int $failed, Collection<int,ImportRowResult> $rows)`.
- **`App\Livewire\ImportData`** — full-page `#[Layout('layouts.app')]`, `use WithFileUploads`. Stav: `?string $target` (hodnota `ImportTarget`), `$csv` (nahraný soubor přes `wire:model="csv"`), `bool $skipDuplicates = true`. Metody: `updatedCsv()` (po nahrání spočítá náhled), `import()` (`CsvImportService::import` → flash souhrn, `$this->reset('csv')`). Náhled se počítá v `render()` z aktuálního `$csv` (jako u ostatních obrazovek se odvozená data počítají v `render()`), takže není potřeba cachovaná property.
- **View** `resources/views/livewire/import-data.blade.php` — Breeze styl:
  - select entity (`ImportTarget::cases()`, `->label()`), odkaz „Stáhnout vzorové CSV" (na `import/sample/{target}`),
  - `<input type="file" wire:model="csv" accept=".csv">`,
  - po nahrání náhledová tabulka: řádek # / klíčové hodnoty / stav (OK / duplicita–přeskočí / chyba + hláška),
  - přepínač „Přeskočit duplicitní řádky" (`wire:model.live="skipDuplicates"`),
  - tlačítko Import (disabled, dokud není `validCount > 0`),
  - souhrn po importu (`imported / skipped / failed`) + `session('status')` flash.
- **Vzorové CSV ke stažení** — `App\Http\Controllers\ImportSampleController` (invokable) na routě `import/sample/{target}`; vrátí `Response::streamDownload` s hlavičkami + jednou ukázkovou řádkou z `ImportTargetDefinition`.

### Repozitářové doplňky (dohledání + duplicity)

- `AccountRepositoryInterface::findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData` — join na instituci; case-sensitive přesná shoda; `null` když nenalezeno.
- `LiabilityRepositoryInterface::findByName(string $name): ?LiabilityData`.
- `CurrencyRepositoryInterface::findByCode(string $code): ?CurrencyData` — **už existuje** (používá `CurrencyConverter`), reuse.
- `TransactionRepositoryInterface::existsMatching(array<string,mixed> $key): bool` — shoda na `account_id, transaction_date, type, amount, counterparty` (null-safe).
- `LiabilityPaymentRepositoryInterface::existsMatching(array<string,mixed> $key): bool` — shoda na `liability_id, payment_date, total_amount`.
- **Zápis reuse:** `TransactionRepository::create`, `LiabilityPaymentRepository::create`, `AccountBalanceSnapshotRepository::upsert` (existující).

## 3. Šablony CSV (ISO formáty)

Hlavička přesně těmito názvy (pořadí nerozhoduje, dohledává se dle názvu; volitelné sloupce smí chybět nebo být prázdné):

`note` se **neimportuje** (u všech entit zůstane `null`). Šablony ho neobsahují.

### `transactions`
`institution, account, type, amount, transaction_date, counterparty`
- `type` = hodnota enumu `TransactionType` (`deposit, withdrawal, dividend, interest, capital_gain, capital_loss, fee, bond_income, other`)
- `amount` decimal (tečka), `transaction_date` `YYYY-MM-DD`, `counterparty` volitelné.
- Ukázka: `Fio banka,Fio běžný účet,dividend,120.50,2026-01-15,AAPL`

### `account_snapshots`
`institution, account, balance, snapshot_date`
- `balance` decimal, `snapshot_date` `YYYY-MM-DD`.
- Ukázka: `Degiro,Broker USD,15000.00,2026-03-31`

### `liability_payments`
`liability, payment_date, total_amount, principal_portion, interest_portion`
- `total_amount` decimal (povinné), `principal_portion`/`interest_portion` volitelné.
- Ukázka: `Hypotéka byt Praha,2026-01-31,12500.00,10000.00,2500.00`

## 4. Validace řádku

Přes Laravel `Validator` (per cíl), chyby se sbírají po řádcích (jde o první chybu na řádku → `error`):
- povinné sloupce přítomné a neprázdné (dle §3),
- `*_date` = platné ISO datum,
- částky = `numeric` (tečka), povinné částky neprázdné,
- `type` (transactions) ∈ `TransactionType`,
- **FK resolution:** instituce+účet / závazek existují (jinak `error` s hláškou „účet 'X' u instituce 'Y' nenalezen" / „závazek 'Z' nenalezen").
- Prázdná buňka u volitelného sloupce → `null`.

## 5. Idempotence a import

- Řádek, který projde validací, se otestuje na duplicitu (jen když `skipDuplicates == true`):
  - snapshot: vždy `upsert` (unikátní klíč (account, date) → přepis/založení; nikdy nezdvojí, `skipDuplicates` u snapshotů nemá efekt, upsert je z principu idempotentní),
  - transakce/splátka: `existsMatching(klíč)` → pokud existuje, status `duplicate` (přeskočí se).
- `import()`: v jedné `DB::transaction()` založí/upsertne platné, nefduplikované řádky. Vrátí `ImportResult(imported, skipped, failed, rows)`.
- Náhled (`preview`) nikdy nezapisuje.

## 6. Routy a navigace

- `GET /import` (auth) → `ImportData`, název `import`.
- `GET /import/sample/{target}` (auth) → `ImportSampleController`, název `import.sample`; `{target}` validován proti `ImportTarget`.
- Nav odkaz „Import" (desktop + responsive), umístěný za „Transactions".

## 7. Testy

- **`CsvImportServiceTest`** (unit, `RefreshDatabase`): parse (hlavička, BOM, prázdné buňky → null); validace (platný / chybějící povinné / špatné datum / nečíselná částka / neplatný `type`); FK resolution (účet dle instituce+název nalezen/nenalezen, závazek nalezen/nenalezen); idempotence pro všechny 3 (snapshot upsert nezdvojí; transakce/splátka `duplicate` při shodě; při `skipDuplicates=false` se vloží); ISO parsing; per-row chybové hlášky; `import()` souhrn (imported/skipped/failed).
- **`ImportTargetDefinitionTest`** (unit): pro každý cíl sedí sloupce, ukázková řádka a klíč duplicit.
- **`ImportDataTest`** (feature, Livewire): guest redirect; `UploadedFile::fake()` CSV → `preview` ukáže valid/duplicate/error počty; `import()` reálně založí řádky (assertDatabaseHas); re-import stejného CSV → `skipped` (assert počty, žádné zdvojení); souhrn se zobrazí.
- **`ImportSampleControllerTest`** (feature): stažení vzorového CSV per cíl vrátí 200 + správné hlavičky.
- Konvence: `#[CoversClass]` přes `use`, snake_case test metody, `===`, reálné DB efekty. Baseline (aktuálně 129 testů) zůstává zelená; Pint + PHPStan level 6 čisté.

## 8. Mimo rozsah (později)

- Flexibilní mapování sloupců (dropdowny sloupec→pole).
- Import syrových exportů z eToro/Binance/banky (vlastní parsery per zdroj).
- Import číselníků (instituce/účty/měny/závazky) — zakládají se ručně přes CRUD.
- Zpětné vrácení importu (undo) / historie importů.
- Nadměrně velké soubory (streamované parsování) — backfill soubory jsou řádově tisíce řádků, drží se v paměti.
