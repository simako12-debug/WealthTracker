<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'type' => $this->faker->randomElement(TransactionType::cases()),
            'amount' => $this->faker->randomFloat(2, 1, 10000),
            'transaction_date' => $this->faker->date(),
            'note' => null,
            'counterparty' => null,
        ];
    }
}
