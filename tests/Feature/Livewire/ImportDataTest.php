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
            ->assertSee('Valid: 1')
            ->assertSee('Errors: 1')
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
