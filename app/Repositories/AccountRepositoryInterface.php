<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AccountRepositoryInterface
{
    /** @return LengthAwarePaginator<int, AccountData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?AccountData;

    /** @return Collection<int, AccountData> */
    public function forInstitution(string $institutionId): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AccountData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountData;

    public function delete(string $id): void;

    /** @return Collection<int, AccountData> */
    public function active(): Collection;

    public function count(): int;

    public function findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData;
}
