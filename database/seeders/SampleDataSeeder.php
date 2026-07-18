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

        $institutions = [];
        foreach ([['fio', 'Fio banka', InstitutionType::BANK], ['etoro', 'eToro', InstitutionType::BROKER]] as [$key, $name, $type]) {
            $institutions[$key] = Institution::query()->updateOrCreate(['name' => $name], ['type' => $type]);
        }

        $accounts = [];
        foreach ([
            ['fio_checking', 'fio', 'Fio běžný účet', 'CZK', AccountType::BANK],
            ['fio_savings', 'fio', 'Fio spořicí účet', 'CZK', AccountType::SAVINGS],
            ['etoro_usd', 'etoro', 'eToro USD', 'USD', AccountType::INVESTMENT],
        ] as [$key, $institutionKey, $name, $currencyCode, $type]) {
            $accounts[$key] = Account::query()->updateOrCreate(
                ['institution_id' => $institutions[$institutionKey]->id, 'name' => $name],
                ['currency_id' => $currencies[$currencyCode]->id, 'type' => $type, 'is_active' => true],
            );
        }

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

        if (Transaction::query()->where('account_id', $accounts['fio_checking']->id)->doesntExist()) {
            Transaction::factory()->count(3)->create([
                'account_id' => $accounts['fio_checking']->id,
                'type' => TransactionType::DEPOSIT,
            ]);
        }
    }
}
