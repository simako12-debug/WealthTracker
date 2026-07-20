<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountData;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AccountRepository implements AccountRepositoryInterface
{
    private const array SORTABLE = ['name', 'type', 'is_active', 'created_at'];

    /** @return LengthAwarePaginator<int, AccountData> */
    public function paginate(string $sortField, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $field = in_array($sortField, self::SORTABLE, true) === true ? $sortField : 'name';
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        return Account::query()
            ->with(['institution', 'currency'])
            ->orderBy($field, $direction)
            ->paginate($perPage)
            ->through(fn (Account $account): AccountData => AccountData::fromModel($account));
    }

    public function find(string $id): ?AccountData
    {
        $account = Account::query()->with(['institution', 'currency'])->find($id);

        return $account === null ? null : AccountData::fromModel($account);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AccountData
    {
        $account = Account::query()->create($attributes);

        return AccountData::fromModel($account->load(['institution', 'currency']));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountData
    {
        $account = Account::query()->findOrFail($id);
        $account->update($attributes);

        return AccountData::fromModel($account->load(['institution', 'currency']));
    }

    public function delete(string $id): void
    {
        Account::query()->where('id', $id)->delete();
    }

    /** @return Collection<int, AccountData> */
    public function forInstitution(string $institutionId): Collection
    {
        return Account::query()
            ->with(['institution', 'currency'])
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): AccountData => AccountData::fromModel($account));
    }

    /** @return Collection<int, AccountData> */
    public function active(): Collection
    {
        return Account::query()
            ->with(['institution', 'currency'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): AccountData => AccountData::fromModel($account));
    }

    public function count(): int
    {
        return Account::query()->count();
    }

    public function findByInstitutionAndName(string $institutionName, string $accountName): ?AccountData
    {
        $account = Account::query()
            ->with(['institution', 'currency'])
            ->where('name', $accountName)
            ->whereHas('institution', function ($query) use ($institutionName): void {
                $query->where('name', $institutionName);
            })
            ->first();

        return $account === null ? null : AccountData::fromModel($account);
    }
}
