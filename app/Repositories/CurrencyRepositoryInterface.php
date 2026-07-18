<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface
{
    /** @return Collection<int, CurrencyData> */
    public function all(): Collection;

    public function findByCode(string $code): ?CurrencyData;
}
