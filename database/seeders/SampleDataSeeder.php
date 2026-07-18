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
