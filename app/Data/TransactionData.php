<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class TransactionData extends Data
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $accountName,
        public string $institutionName,
        public string $accountCurrencyCode,
        public TransactionType $type,
        public string $amount,
        public CarbonImmutable $transactionDate,
        public ?string $note,
        public ?string $counterparty,
    ) {}

    public static function fromModel(Transaction $transaction): self
    {
        return new self(
            id: $transaction->id,
            accountId: $transaction->account_id,
            accountName: $transaction->account->name,
            institutionName: $transaction->account->institution->name,
            accountCurrencyCode: $transaction->account->currency->code,
            type: $transaction->type,
            amount: $transaction->amount,
            transactionDate: $transaction->transaction_date->toImmutable(),
            note: $transaction->note,
            counterparty: $transaction->counterparty,
        );
    }
}
