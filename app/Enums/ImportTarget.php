<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ImportTarget: string
{
    use HasLabel;

    case TRANSACTIONS = 'transactions';
    case ACCOUNT_SNAPSHOTS = 'account_snapshots';
    case LIABILITY_PAYMENTS = 'liability_payments';

    /** @return list<string> */
    public function headers(): array
    {
        return match ($this) {
            self::TRANSACTIONS => ['institution', 'account', 'type', 'amount', 'transaction_date', 'counterparty'],
            self::ACCOUNT_SNAPSHOTS => ['institution', 'account', 'balance', 'snapshot_date'],
            self::LIABILITY_PAYMENTS => ['liability', 'payment_date', 'total_amount', 'principal_portion', 'interest_portion'],
        };
    }

    /** @return list<string> */
    public function sampleRow(): array
    {
        return match ($this) {
            self::TRANSACTIONS => ['Fio banka', 'Fio běžný účet', 'dividend', '120.50', '2026-01-15', 'AAPL'],
            self::ACCOUNT_SNAPSHOTS => ['Degiro', 'Broker USD', '15000.00', '2026-03-31'],
            self::LIABILITY_PAYMENTS => ['Hypotéka byt Praha', '2026-01-31', '12500.00', '10000.00', '2500.00'],
        };
    }
}
