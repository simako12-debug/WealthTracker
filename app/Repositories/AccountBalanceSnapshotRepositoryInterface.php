<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\AccountBalanceSnapshotData;
use Illuminate\Support\Collection;

interface AccountBalanceSnapshotRepositoryInterface
{
    /** @return Collection<int, AccountBalanceSnapshotData> */
    public function recent(int $limit): Collection;

    public function find(string $id): ?AccountBalanceSnapshotData;

    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): AccountBalanceSnapshotData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): AccountBalanceSnapshotData;

    public function delete(string $id): void;
}
