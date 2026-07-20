<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\LiabilityPayment;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class LiabilityPaymentData extends Data
{
    public function __construct(
        public string $id,
        public string $liabilityId,
        public string $liabilityName,
        public string $currencyCode,
        public CarbonImmutable $paymentDate,
        public string $totalAmount,
        public ?string $principalPortion,
        public ?string $interestPortion,
        public ?string $note,
    ) {}

    public static function fromModel(LiabilityPayment $payment): self
    {
        return new self(
            id: $payment->id,
            liabilityId: $payment->liability_id,
            liabilityName: $payment->liability->name,
            currencyCode: $payment->liability->currency->code,
            paymentDate: $payment->payment_date->toImmutable(),
            totalAmount: $payment->total_amount,
            principalPortion: $payment->principal_portion,
            interestPortion: $payment->interest_portion,
            note: $payment->note,
        );
    }
}
