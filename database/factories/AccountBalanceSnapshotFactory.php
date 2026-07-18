<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountBalanceSnapshot> */
class AccountBalanceSnapshotFactory extends Factory
{
    protected $model = AccountBalanceSnapshot::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 500000),
            'snapshot_date' => $this->faker->date(),
            'note' => null,
        ];
    }
}
