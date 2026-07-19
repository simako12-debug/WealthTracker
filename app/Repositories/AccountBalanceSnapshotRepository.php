<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountBalanceSnapshotData;
use App\Models\AccountBalanceSnapshot;
use Illuminate\Support\Collection;

final readonly class AccountBalanceSnapshotRepository implements AccountBalanceSnapshotRepositoryInterface
{
    private const array WITH = ['account.institution', 'account.currency'];

    /** @return Collection<int, AccountBalanceSnapshotData> */
    public function recent(int $limit): Collection
    {
        return AccountBalanceSnapshot::query()
            ->with(self::WITH)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AccountBalanceSnapshot $snapshot): AccountBalanceSnapshotData => AccountBalanceSnapshotData::fromModel($snapshot));
    }

    public function find(string $id): ?AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->with(self::WITH)->find($id);

        return $snapshot === null ? null : AccountBalanceSnapshotData::fromModel($snapshot);
    }

    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->updateOrCreate(
            ['account_id' => $attributes['account_id'], 'snapshot_date' => $attributes['snapshot_date']],
            ['balance' => $attributes['balance'], 'note' => $attributes['note']],
        );

        return AccountBalanceSnapshotData::fromModel($snapshot->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountBalanceSnapshotData
    {
        $snapshot = AccountBalanceSnapshot::query()->findOrFail($id);
        $snapshot->update($attributes);

        return AccountBalanceSnapshotData::fromModel($snapshot->load(self::WITH));
    }

    public function delete(string $id): void
    {
        AccountBalanceSnapshot::query()->where('id', $id)->delete();
    }
}
