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
