<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyPairData;
use App\Models\CurrencyPair;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class CurrencyPairRepository implements CurrencyPairRepositoryInterface
{
    private const array SORTABLE = ['source', 'is_active', 'created_at'];

    /** @return Collection<int, CurrencyPairData> */
    public function activePairs(): Collection
    {
        return CurrencyPair::query()
            ->with(['baseCurrency', 'quoteCurrency'])
            ->where('is_active', true)
            ->get()
            ->map(fn (CurrencyPair $pair): CurrencyPairData => CurrencyPairData::fromModel($pair));
    }

    /** @return LengthAwarePaginator<int, CurrencyPairData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'created_at';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return CurrencyPair::query()
            ->with(['baseCurrency', 'quoteCurrency'])
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (CurrencyPair $pair): CurrencyPairData => CurrencyPairData::fromModel($pair));
    }

    public function find(string $id): ?CurrencyPairData
    {
        $pair = CurrencyPair::query()->with(['baseCurrency', 'quoteCurrency'])->find($id);

        return $pair === null ? null : CurrencyPairData::fromModel($pair);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyPairData
    {
        $pair = CurrencyPair::query()->create($attributes);

        return CurrencyPairData::fromModel($pair->load(['baseCurrency', 'quoteCurrency']));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyPairData
    {
        $pair = CurrencyPair::query()->findOrFail($id);
        $pair->update($attributes);

        return CurrencyPairData::fromModel($pair->load(['baseCurrency', 'quoteCurrency']));
    }

    public function delete(string $id): void
    {
        CurrencyPair::query()->where('id', $id)->delete();
    }
}
