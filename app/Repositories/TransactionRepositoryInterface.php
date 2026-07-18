<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\TransactionData;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    /** @return Collection<int, TransactionData> */
    public function recent(int $limit): Collection;

    public function find(string $id): ?TransactionData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TransactionData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): TransactionData;

    public function delete(string $id): void;
}
