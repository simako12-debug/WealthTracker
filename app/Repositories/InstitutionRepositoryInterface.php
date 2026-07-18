<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\InstitutionData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InstitutionRepositoryInterface
{
    /** @return Collection<int, InstitutionData> */
    public function all(): Collection;

    /** @return LengthAwarePaginator<int, InstitutionData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): InstitutionData;

    public function delete(string $id): void;
}
