<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Liability;
use App\Models\LiabilityPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiabilityPayment> */
class LiabilityPaymentFactory extends Factory
{
    protected $model = LiabilityPayment::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'liability_id' => Liability::factory(),
            'payment_date' => $this->faker->date(),
            'total_amount' => $this->faker->randomFloat(2, 5000, 40000),
            'principal_portion' => null,
            'interest_portion' => null,
            'note' => null,
        ];
    }
}
