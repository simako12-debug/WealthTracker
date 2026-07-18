<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FxSource;
use App\Models\FxRate;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class FxRateData extends Data
{
    public function __construct(
        public ?string $id,
        public string $currencyFromId,
        public string $currencyToId,
        public string $rate,
        public CarbonImmutable $rateDate,
        public FxSource $source,
    ) {}

    public static function fromModel(FxRate $rate): self
    {
        return new self(
            id: $rate->id,
            currencyFromId: $rate->currency_from_id,
            currencyToId: $rate->currency_to_id,
            rate: $rate->rate,
            rateDate: $rate->rate_date->toImmutable(),
            source: $rate->source,
        );
    }
}
