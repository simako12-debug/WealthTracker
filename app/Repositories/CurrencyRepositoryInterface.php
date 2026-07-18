<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface
{
    /** @return Collection<int, CurrencyData> */
    public function all(): Collection;

    public function findByCode(string $code): ?CurrencyData;

    /** @return LengthAwarePaginator<int, CurrencyData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?CurrencyData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyData;

    public function delete(string $id): void;
}
