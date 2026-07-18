<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AccountType;
use App\Models\Account;
use Spatie\LaravelData\Data;

final class AccountData extends Data
{
    public function __construct(
        public string $id,
        public string $institutionId,
        public string $institutionName,
        public string $currencyId,
        public string $currencyCode,
        public string $name,
        public AccountType $type,
        public bool $isActive,
        public ?string $note,
    ) {}

    public static function fromModel(Account $account): self
    {
        return new self(
            id: $account->id,
            institutionId: $account->institution_id,
            institutionName: $account->institution->name,
            currencyId: $account->currency_id,
            currencyCode: $account->currency->code,
            name: $account->name,
            type: $account->type,
            isActive: $account->is_active,
            note: $account->note,
        );
    }
}
