<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AccountType: string
{
    use HasLabel;

    case BANK = 'bank';
    case INVESTMENT = 'investment';
    case SAVINGS = 'savings';
    case WALLET = 'wallet';
}
