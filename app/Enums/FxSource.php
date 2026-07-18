<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum FxSource: string
{
    use HasLabel;

    case CNB = 'cnb';
    case FRANKFURTER = 'frankfurter';
}
