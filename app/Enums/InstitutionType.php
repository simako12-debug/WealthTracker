<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum InstitutionType: string
{
    use HasLabel;

    case BANK = 'bank';
    case BROKER = 'broker';
    case EXCHANGE = 'exchange';
    case LENDER = 'lender';
    case OTHER = 'other';
}
