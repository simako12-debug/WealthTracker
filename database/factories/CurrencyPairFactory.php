<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CurrencyPair> */
class CurrencyPairFactory extends Factory
{
    protected $model = CurrencyPair::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'base_currency_id' => Currency::factory(),
            'quote_currency_id' => Currency::factory(),
            'source' => FxSource::CNB,
            'is_active' => true,
            'note' => null,
        ];
    }
}
