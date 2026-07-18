<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Liability;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class LiabilityData extends Data
{
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $currencyId,
        public string $currencyCode,
        public string $name,
        public string $principalAmount,
        public string $interestRate,
        public ?string $monthlyPayment,
        public CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public bool $isActive,
        public ?string $note,
    ) {}

    public static function fromModel(Liability $liability): self
    {
        return new self(
            id: $liability->id,
            institutionId: $liability->institution_id,
            institutionName: $liability->institution->name,
            currencyId: $liability->currency_id,
            currencyCode: $liability->currency->code,
            name: $liability->name,
            principalAmount: $liability->principal_amount,
            interestRate: $liability->interest_rate,
            monthlyPayment: $liability->monthly_payment,
            startDate: $liability->start_date->toImmutable(),
            endDate: $liability->end_date?->toImmutable(),
            isActive: $liability->is_active,
            note: $liability->note,
        );
    }
}
