# Osobní finanční evidence — návrh (design spec)

- **Datum:** 2026-07-18
- **Stav:** schváleno k implementaci
- **Autor:** petr + Claude (brainstorming)

## 1. Cíl a rozsah

Lokální aplikace pro evidenci a statistiky osobních financí jednoho uživatele:

- bankovní a investiční účty (různé instituce, různé měny)
- transakce (vklady, výběry, dividendy, úroky, kapitálové zisky/ztráty, poplatky…)
- závazky (hypotéka, půjčky) a jejich splátky
- kurzy měn (jen fiat) s historií, aktualizace na tlačítko i cronem
- import historických dat z CSV

Architektura je rozdělená na dvě vrstvy:

- **Zápisová vrstva** — Laravel + Livewire (vlastní CRUD, žádný Filament ani jiný admin/CRUD generátor).
- **Statistická/vizualizační vrstva** — Grafana nad stejnou PostgreSQL DB (SQL dotazy, dashboardy). Žádný grafový kód v appce; appka je záměrně „hloupá".

Vše běží lokálně přes Docker Compose. Bez cloudu, bez externích SaaS na evidenci dat.

### Klíčová rozhodnutí z brainstormingu

1. **Plné guidelines** — platí globální PHP/Laravel guidelines: UUID PK, Spatie Laravel Data DTO, repository pattern, service třídy, `{Class}Test` pro každou třídu. Guidelines jsou psané na REST API; tady REST endpointy nejsou — roli „controlleru" hrají Livewire komponenty, které delegují na service/repository vrstvu.
2. **Bez krypta** — žádné krypto měny, žádný `is_crypto` flag, žádný CoinGecko. Všechny účty jsou v reálných (fiat) měnách.
3. **Kurzy: ČNB + frankfurter** — sledované páry se spravují v UI (`currency_pairs`). Zdroj se určí podle páru (pár obsahuje CZK → ČNB, jinak → frankfurter.app), s možností ručního přepisu. Žádný pivot přes CZK, žádné dopočítávání cross-kurzů — každý pár se tahá od zdroje přímo a ukládá jako čistá časová řada.
4. **Aktiva vs. dluhy** — dvě oddělené tabulky. `accounts` se do čistého jmění přičítají (mohou být i záporné — kontokorent), `liabilities` se odečítají. Výpočet čistého jmění dělá Grafana v SQL, ne appka.
5. **Auth** — Breeze, jeden seedovaný uživatel z `.env`, registrace/reset vypnuté.
6. **CSV import** — Livewire upload UI pro historická data (transakce, balance snapshoty, splátky závazků).

## 2. Tech stack

- **Laravel** — nejnovější stabilní (fakticky nainstalováno 13)
- **Livewire 4** (Blade komponenty; ne Inertia/React) — potvrzeno uživatelem (původně zmíněno 3; nainstalováno a schváleno 4)
- **PostgreSQL** — nejnovější stabilní (`postgres:17`+)
- **PHP** — nejnovější stabilní (8.4, resp. 8.5 pokud vyšla; v Dockerfile nejnovější stable tag)
- **Grafana** — poslední stable, oficiální docker image
- **Laravel Breeze** (Blade + Tailwind) pro auth
- **Tailwind** (výchozí s Breeze), žádný komponentový framework navíc
- **Spatie Laravel Data** pro DTO

## 3. Architektura

### 3.1 Docker Compose (3 služby, jedna síť)

- **`app`** — PHP-CLI image (nejnovější stable), běží `php artisan serve --host=0.0.0.0 --port=8000`. Dostupné na `localhost:8000` i po LAN (mobil). Vite/Tailwind assety se buildnou při stavbě image (`npm run build`) — appka je statická, stačí build jednou.
- **`postgres`** — `postgres:17`+, volume pro perzistenci dat, DB name/user/pass z `.env`.
- **`grafana`** — `grafana/grafana:latest`, volume pro perzistenci dashboardů, **provisioning** datasource napojený na službu `postgres` přes docker network (žádné ruční klikání na datasource při startu). Port 3000.

### 3.2 Vrstvení uvnitř Laravelu

- **Livewire komponenty** — prezentace: drží stav formuláře, validují, volají service/repository. Nahrazují controllery.
- **Modely** — `HasUuids`, enumy v `$casts`, factories, custom builder scopy kde dávají smysl. `getKey()` vrací `$this->id->toString()`.
- **Repository** — `{Entity}RepositoryInterface` + `final readonly {Entity}Repository`. Vrací Data objekty / `Collection`, ne query buildery. Zápisy v `DB::transaction()`, upsert přes `createOrFirst()`/`updateOrCreate`.
- **Service** — `FxSyncService`, `CurrencyConverter`, `CsvImportService` a provider třídy pro zdroje kurzů.
- **DTO** — Spatie Laravel Data pro přenos formulář → repository a napříč hranicemi vrstev.

Standardní Laravel layout: `app/Http/Livewire` (nebo `app/Livewire`), `app/Models`, `app/Repositories`, `app/Services`, `app/Data`, `app/Enums`, `database/migrations`, `database/factories`, `database/seeders`, `tests/`.

## 4. Datový model

Vše UUID PK (`HasUuids`). U každého FK sloupce vždy `->index()` vedle `->foreign()` (PostgreSQL index netvoří automaticky). `->nullable()` u volitelných sloupců. Peněžní částky i kurzy jednotně `decimal(20,10)` (≥ 8 desetinných míst).

### `currencies`
- `id` (uuid)
- `code` (string, ISO 4217 — CZK, EUR, USD, GBP…)
- `name` (string)

### `institutions`
- `id` (uuid)
- `name` (string)
- `type` (enum: `bank`, `broker`, `exchange`, `lender`, `other`)
- `note` (text, nullable)

### `accounts`
Konkrétní účet u instituce. Reprezentuje **aktivum** (do čistého jmění se přičítá; může být i záporné — kontokorent).
- `id` (uuid)
- `institution_id` (FK)
- `currency_id` (FK)
- `name` (string — např. „eToro USD", „Fio běžný účet")
- `type` (enum: `bank`, `investment`, `savings`, `wallet`)
- `is_active` (boolean, default true)
- `note` (text, nullable)

### `currency_pairs` ⭐ nová entita
Sledované měnové páry spravované v UI. Definuje, co `fx:sync` tahá a co má historii.
- `id` (uuid)
- `base_currency_id` (FK — „from")
- `quote_currency_id` (FK — „to")
- `source` (enum: `cnb`, `frankfurter`) — předvyplněno pravidlem (pár obsahuje CZK → `cnb`, jinak → `frankfurter`), editovatelné v UI
- `is_active` (boolean, default true)
- `note` (text, nullable)
- unique `(base_currency_id, quote_currency_id)`

### `fx_rates`
Historie kurzů (čistá časová řada na pár + zdroj + datum).
- `id` (uuid)
- `currency_from_id` (FK)
- `currency_to_id` (FK)
- `rate` (decimal(20,10))
- `rate_date` (date)
- `source` (string — `cnb`, `frankfurter`)
- `created_at`
- unique `(currency_from_id, currency_to_id, rate_date, source)` — sync provádí **upsert** (idempotentní přepis)

### `transactions`
- `id` (uuid)
- `account_id` (FK)
- `type` (enum: `deposit`, `withdrawal`, `dividend`, `interest`, `capital_gain`, `capital_loss`, `fee`, `bond_income`, `other`)
- `amount` (decimal(20,10) — v měně účtu)
- `transaction_date` (date)
- `note` (text, nullable)
- `counterparty` (string, nullable — např. ticker akcie, protistrana)
- `created_at`, `updated_at`

### `liabilities`
Strukturovaný dluh (hypotéka, půjčka). Do čistého jmění se **odečítá**.
- `id` (uuid)
- `institution_id` (FK)
- `name` (string — např. „Hypotéka byt Praha")
- `principal_amount` (decimal(20,10) — původní jistina)
- `currency_id` (FK)
- `interest_rate` (decimal — roční úrok v %)
- `monthly_payment` (decimal(20,10), nullable — pokud je fixní)
- `start_date` (date)
- `end_date` (date, nullable)
- `is_active` (boolean, default true)
- `note` (text, nullable)

### `liability_payments`
- `id` (uuid)
- `liability_id` (FK)
- `payment_date` (date)
- `total_amount` (decimal(20,10))
- `principal_portion` (decimal(20,10), nullable)
- `interest_portion` (decimal(20,10), nullable)
- `note` (text, nullable)

### `account_balance_snapshots`
Pravidelný zápis skutečné hodnoty účtu (hlavně investiční účty, kde cashflow ≠ hodnota portfolia).
- `id` (uuid)
- `account_id` (FK)
- `balance` (decimal(20,10) — hodnota účtu v měně účtu)
- `snapshot_date` (date)
- `note` (text, nullable)
- unique `(account_id, snapshot_date)`

### Enumy
Backed string enumy, UPPER_CASE názvy case, lowercase hodnoty, registrované v `$casts`:
- `InstitutionType`: BANK/BROKER/EXCHANGE/LENDER/OTHER
- `AccountType`: BANK/INVESTMENT/SAVINGS/WALLET
- `TransactionType`: DEPOSIT/WITHDRAWAL/DIVIDEND/INTEREST/CAPITAL_GAIN/CAPITAL_LOSS/FEE/BOND_INCOME/OTHER
- `FxSource`: CNB/FRANKFURTER

## 5. Kurzy měn — automatická aktualizace

### 5.1 Zdroje

- **ČNB** — denní kurzovní lístek (`https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt`). Zdarma, bez klíče, bez rate limitu. Báze vůči CZK.
  - Formát: hlavička + řádky `země|měna|množství|kód|kurz` (oddělené `|`, desetinná čárka).
  - **Normalizace množství**: některé měny kotované na 100 jednotek (JPY, HUF…) → `rate = kurz / množství` = CZK za 1 jednotku.
  - **Směr**: ČNB dává `foreign → CZK`. Pár `X→CZK` = přímo; pár `CZK→X` = inverze `1 / (X→CZK)`.
- **frankfurter.app** — `https://api.frankfurter.app/latest?from=USD&to=EUR` (ECB data). Zdarma, bez klíče. Libovolný fiat pár přímo, historie k datu přes `/{date}?from=&to=`.

### 5.2 Implementace

- `RateProviderInterface` — kontrakt `fetchRate(Currency $from, Currency $to, CarbonImmutable $date): ?RateResult` (nebo dávkově dle providera).
  - `CnbRateProvider` — stáhne a naparsuje lístek, ošetří normalizaci množství a směr.
  - `FrankfurterRateProvider` — zavolá API pro daný pár.
- `FxSyncService` — projde **aktivní `currency_pairs`**, pro každý zvolí providera dle `source`, získá kurz a **upsertem** zapíše do `fx_rates` (dle unique klíče `(from, to, rate_date, source)`).
- `php artisan fx:sync` — command volatelný cronem i z Livewire tlačítka (dispatch). Vrací souhrn (kolik updated / skipped / failed), který se zobrazí uživateli.
- HTTP volání přes Laravel `Http` fasádu (kvůli fakeování v testech).

### 5.3 Přepočet do CZK

`CurrencyConverter` service: `toCzk(amount, Currency $from, CarbonImmutable $date): ?Money`
- Když `from` == CZK → beze změny.
- Jinak najde poslední `fx_rate` `from → CZK` s `rate_date <= $date` (nejbližší starší). Když žádný neexistuje → vrací `null` (UI zobrazí „kurz nedostupný").

## 6. Zápisová vrstva (Livewire)

### 6.1 Generické CRUD komponenty
Tabulka + modal formulář (create/edit/delete), řazení, stránkování, bez složitých filtrů. Pro: `institutions`, `currencies`, `accounts`, `liabilities`, **`currency_pairs`**. Tyto entity se mění zřídka.

U `currency_pairs`: při volbě měn se `source` předvyplní pravidlem (CZK v páru → `cnb`, jinak `frankfurter`), uživatel může přepsat.

### 6.2 `transactions` — vyšší priorita na UX
Nejčastěji používaný formulář, i z mobilu, musí být rychlý:
- select instituce → dynamicky filtrovaný select účtu (dle instituce)
- po výběru účtu badge měny účtu (readonly)
- select typ transakce
- input amount
- **živý přepočet „≈ X CZK"** přes `CurrencyConverter` (poslední kurz měna účtu → CZK k datu ≤ `transaction_date`); když chybí → „kurz nedostupný"
- datepicker (default dnešní datum)
- volitelně note / counterparty
- po uložení: reset formuláře, zůstat na stránce (rychlé opakované zapisování)
- pod formulářem tabulka posledních transakcí s rychlou editací/smazáním

### 6.3 `liability_payments`
Formulář navázaný na `liabilities`: select liability → kontext (poslední splátka, zbývající počet splátek), pak formulář splátky.

### 6.4 `account_balance_snapshots`
Jednoduchý formulář: select account → amount → date. Hlavně pro investiční účty jednou za čas.

### 6.5 CSV import (`CsvImport` komponenta)
Livewire upload UI pro historická data z Excelu (přeložená do CSV). Cílové entity: **`transactions`, `account_balance_snapshots`, `liability_payments`** (základní číselníky se importem neřeší — zakládají se ručně přes CRUD před importem).

Tok:
1. výběr cílové entity
2. upload CSV souboru
3. parse hlavičky → náhled prvních N řádků
4. mapování CSV sloupců na pole entity (dropdowny, auto-předvyplnění dle názvu hlavičky)
5. FK resolution přes přirozené klíče: účet dle (název instituce + název účtu) nebo názvu účtu; závazek dle názvu; měna dle kódu
6. validace řádků (chyby vidět v náhledu)
7. import

**Idempotence:**
- `account_balance_snapshots` — upsert dle unique `(account_id, snapshot_date)`
- `transactions`, `liability_payments` — nemají přirozený unikát → volitelný přepínač „přeskoč, pokud identický řádek existuje" (shoda na klíčových polích), jinak insert. Náhled ukazuje, co se založí.

Logika parsování/mapování/validace/importu žije v `CsvImportService` (testovatelné mimo Livewire).

### 6.6 Dashboard (po loginu, minimalistický)
Rychlý kontext při zápisu, NENÍ náhrada Grafany, žádné grafy:
- počet účtů
- posledních N transakcí
- aktivní závazky s datem poslední splátky

## 7. Auth

Laravel Breeze (Blade + Tailwind). **Jeden seedovaný uživatel** (email/heslo z `.env`). Registrační a reset-password route odstraněny, zůstává jen login. Všechny komponenty za `auth` middleware.

## 8. Testy

Plné pokrytí dle guidelines — `{Class}Test` pro každou třídu v zrcadleném test adresáři (`#[CoversClass]`, deterministické UUID přes `Uuid::fromString(...)`):

- **Repository** — CRUD/upsert, návratové Data objekty.
- **Service** — `FxSyncService` (fakeovaný HTTP), `CurrencyConverter` (nejbližší starší kurz, chybějící kurz → null, CZK→CZK), `CsvImportService` (parse, mapování, FK resolution, idempotence).
- **Providery** — `CnbRateProvider` (fixture lístku ČNB, normalizace množství, inverze směru), `FrankfurterRateProvider` (fakeovaný JSON).
- **Command** — `fx:sync` (souhrn, upsert).
- **DTO** — validace/tvorba.
- **Livewire** — přes `Livewire::test()`; hlavně `transactions` (dynamický select účtu, živý přepočet, reset po uložení), fx tlačítko, `CsvImport` (náhled, mapování, import, idempotence).

HTTP volání vždy přes `Http` fasádu kvůli `Http::fake()`. Po psaní/úpravě testů spustit PHPStan a code style (`phpcs`/`phpcbf`), max délka řádku 120.

## 9. Seed data

Seeder pro rychlé vyzkoušení:
- měny: CZK, EUR, USD, GBP
- 2–3 instituce
- pár účtů (různé měny, včetně jednoho investičního)
- ukázkové `currency_pairs`: USD→CZK, EUR→CZK, USD→EUR
- pár transakcí
- seedovaný uživatel (z `.env`)

## 10. Mimo rozsah (později)

- Zálohování DB na Google Drive (dump → Drive)
- Import CSV exportů z eToro/Binance (teď jen vlastní backfill z Excelu)
- Nasazení mimo lokální síť (Railway/VPS)
- Výpočet realizovaného vs. nerealizovaného zisku (řeší Grafana SQL nad `transactions` + `account_balance_snapshots`)
- Konkrétní Grafana dashboardy (samostatný krok po dokončení zápisové vrstvy a naplnění daty)
- Krypto měny a CoinGecko
