<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

trait HasLabel
{
    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
