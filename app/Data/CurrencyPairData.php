<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FxSource;
use App\Models\CurrencyPair;
use Spatie\LaravelData\Data;

final class CurrencyPairData extends Data
{
    public function __construct(
        public string $id,
        public string $baseCurrencyId,
        public string $baseCurrencyCode,
        public string $quoteCurrencyId,
        public string $quoteCurrencyCode,
        public FxSource $source,
        public bool $isActive,
    ) {
    }

    public static function fromModel(CurrencyPair $pair): self
    {
        return new self(
            id: $pair->id,
            baseCurrencyId: $pair->base_currency_id,
            baseCurrencyCode: $pair->baseCurrency->code,
            quoteCurrencyId: $pair->quote_currency_id,
            quoteCurrencyCode: $pair->quoteCurrency->code,
            source: $pair->source,
            isActive: $pair->is_active,
        );
    }
}
