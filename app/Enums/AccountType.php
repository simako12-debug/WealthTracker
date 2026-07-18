<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case BANK = 'bank';
    case INVESTMENT = 'investment';
    case SAVINGS = 'savings';
    case WALLET = 'wallet';
}
