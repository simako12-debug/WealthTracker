<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityPaymentData;
use Illuminate\Support\Collection;

interface LiabilityPaymentRepositoryInterface
{
    /** @return Collection<int, LiabilityPaymentData> */
    public function recentForLiability(string $liabilityId, int $limit): Collection;

    public function countForLiability(string $liabilityId): int;

    /** @return Collection<string, string> */
    public function latestDateByLiability(): Collection;

    public function find(string $id): ?LiabilityPaymentData;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityPaymentData;

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityPaymentData;

    public function delete(string $id): void;
}
