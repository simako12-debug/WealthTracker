<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\InstitutionData;
use App\Models\Institution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class InstitutionRepository implements InstitutionRepositoryInterface
{
    private const array SORTABLE = ['name', 'type', 'created_at'];

    /** @return LengthAwarePaginator<int, InstitutionData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'name';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Institution::query()
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Institution $institution): InstitutionData => InstitutionData::fromModel($institution));
    }

    public function find(string $id): ?InstitutionData
    {
        $institution = Institution::query()->find($id);

        return $institution === null ? null : InstitutionData::fromModel($institution);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): InstitutionData
    {
        return InstitutionData::fromModel(Institution::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): InstitutionData
    {
        $institution = Institution::query()->findOrFail($id);
        $institution->update($attributes);

        return InstitutionData::fromModel($institution);
    }

    public function delete(string $id): void
    {
        Institution::query()->where('id', $id)->delete();
    }
}
