<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case DIVIDEND = 'dividend';
    case INTEREST = 'interest';
    case CAPITAL_GAIN = 'capital_gain';
    case CAPITAL_LOSS = 'capital_loss';
    case FEE = 'fee';
    case BOND_INCOME = 'bond_income';
    case OTHER = 'other';
}
