<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Controllers\ImportSampleController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ImportSampleController::class)]
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
        $response->assertDownload('transactions-sample.csv');
        $this->assertStringContainsString('institution,account,type,amount,transaction_date,counterparty', $response->streamedContent());
    }

    public function test_unknown_target_is_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/import/sample/nope')->assertNotFound();
    }
}
