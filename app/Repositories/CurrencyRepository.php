<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\CurrencyData;
use App\Models\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class CurrencyRepository implements CurrencyRepositoryInterface
{
    private const array SORTABLE = ['code', 'name', 'created_at'];

    /** @return Collection<int, CurrencyData> */
    public function all(): Collection
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): CurrencyData => CurrencyData::fromModel($currency));
    }

    public function findByCode(string $code): ?CurrencyData
    {
        $currency = Currency::query()->where('code', $code)->first();

        return $currency === null ? null : CurrencyData::fromModel($currency);
    }

    /** @return LengthAwarePaginator<int, CurrencyData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'code';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Currency::query()
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Currency $currency): CurrencyData => CurrencyData::fromModel($currency));
    }

    public function find(string $id): ?CurrencyData
    {
        $currency = Currency::query()->find($id);

        return $currency === null ? null : CurrencyData::fromModel($currency);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CurrencyData
    {
        return CurrencyData::fromModel(Currency::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): CurrencyData
    {
        $currency = Currency::query()->findOrFail($id);
        $currency->update($attributes);

        return CurrencyData::fromModel($currency);
    }

    public function delete(string $id): void
    {
        Currency::query()->where('id', $id)->delete();
    }
}
