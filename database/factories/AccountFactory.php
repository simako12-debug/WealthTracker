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
