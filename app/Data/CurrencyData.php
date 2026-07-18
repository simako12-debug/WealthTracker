<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Currency;
use Spatie\LaravelData\Data;

final class CurrencyData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {}

    public static function fromModel(Currency $currency): self
    {
        return new self(
            id: $currency->id,
            code: $currency->code,
            name: $currency->name,
        );
    }
}
