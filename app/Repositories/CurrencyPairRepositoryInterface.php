<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CurrencyPairRepositoryInterface
{
    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection;

    /** @return LengthAwarePaginator<int, CurrencyPairData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?CurrencyPairData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyPairData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyPairData;

    public function delete(string $id): void;
}
