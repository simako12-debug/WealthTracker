<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LiabilityRepositoryInterface
{
    /** @return LengthAwarePaginator<int, LiabilityData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?LiabilityData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityData;

    public function delete(string $id): void;

    /** @return Collection<int, LiabilityData> */
    public function active(): Collection;
}
