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

    public function test_account_belongs_to_institution_and_currency(): void
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
