<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FxRate> */
class FxRateFactory extends Factory
{
    protected $model = FxRate::class;

    public function definition(): array
    {
        return [
            'currency_from_id' => Currency::factory(),
            'currency_to_id' => Currency::factory(),
            'rate' => $this->faker->randomFloat(6, 1, 30),
            'rate_date' => $this->faker->date(),
            'source' => FxSource::CNB,
        ];
    }
}
