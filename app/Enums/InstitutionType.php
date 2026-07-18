<?php

declare(strict_types=1);

namespace App\Enums;

enum InstitutionType: string
{
    case BANK = 'bank';
    case BROKER = 'broker';
    case EXCHANGE = 'exchange';
    case LENDER = 'lender';
    case OTHER = 'other';
}
