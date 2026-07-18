<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Institution;
use App\Models\Liability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Liability> */
class LiabilityFactory extends Factory
{
    protected $model = Liability::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => $this->faker->words(3, true),
            'principal_amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'currency_id' => Currency::factory(),
            'interest_rate' => $this->faker->randomFloat(4, 1, 9),
            'monthly_payment' => $this->faker->randomFloat(2, 5000, 40000),
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'is_active' => true,
            'note' => null,
        ];
    }
}
