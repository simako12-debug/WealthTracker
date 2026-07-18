<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccountRepositoryInterface
{
    /** @return LengthAwarePaginator<int, AccountData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?AccountData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AccountData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountData;

    public function delete(string $id): void;
}
