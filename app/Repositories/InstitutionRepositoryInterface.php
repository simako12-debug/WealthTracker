<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\InstitutionData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InstitutionRepositoryInterface
{
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator;

    public function find(string $id): ?InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InstitutionData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): InstitutionData;

    public function delete(string $id): void;
}
