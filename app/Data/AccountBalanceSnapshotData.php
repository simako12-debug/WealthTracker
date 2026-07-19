<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\AccountBalanceSnapshot;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class AccountBalanceSnapshotData extends Data
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $accountName,
        public string $institutionName,
        public string $currencyCode,
        public string $balance,
        public CarbonImmutable $snapshotDate,
        public ?string $note,
    ) {}

    public static function fromModel(AccountBalanceSnapshot $snapshot): self
    {
        return new self(
            id: $snapshot->id,
            accountId: $snapshot->account_id,
            accountName: $snapshot->account->name,
            institutionName: $snapshot->account->institution->name,
            currencyCode: $snapshot->account->currency->code,
            balance: $snapshot->balance,
            snapshotDate: $snapshot->snapshot_date->toImmutable(),
            note: $snapshot->note,
        );
    }
}
