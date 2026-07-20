<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityData;
use App\Models\Liability;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class LiabilityRepository implements LiabilityRepositoryInterface
{
    private const array SORTABLE = ['name', 'start_date', 'is_active', 'created_at'];

    /** @return LengthAwarePaginator<int, LiabilityData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'name';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Liability::query()
            ->with(['institution', 'currency'])
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Liability $liability): LiabilityData => LiabilityData::fromModel($liability));
    }

    public function find(string $id): ?LiabilityData
    {
        $liability = Liability::query()->with(['institution', 'currency'])->find($id);

        return $liability === null ? null : LiabilityData::fromModel($liability);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityData
    {
        $liability = Liability::query()->create($attributes);

        return LiabilityData::fromModel($liability->load(['institution', 'currency']));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityData
    {
        $liability = Liability::query()->findOrFail($id);
        $liability->update($attributes);

        return LiabilityData::fromModel($liability->load(['institution', 'currency']));
    }

    public function delete(string $id): void
    {
        Liability::query()->where('id', $id)->delete();
    }

    /** @return Collection<int, LiabilityData> */
    public function active(): Collection
    {
        return Liability::query()
            ->with(['institution', 'currency'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Liability $liability): LiabilityData => LiabilityData::fromModel($liability));
    }
}
