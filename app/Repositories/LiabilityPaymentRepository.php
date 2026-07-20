<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\LiabilityPaymentData;
use App\Models\LiabilityPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class LiabilityPaymentRepository implements LiabilityPaymentRepositoryInterface
{
    private const array WITH = ['liability.currency'];

    /** @return Collection<int, LiabilityPaymentData> */
    public function recentForLiability(string $liabilityId, int $limit): Collection
    {
        return LiabilityPayment::query()
            ->with(self::WITH)
            ->where('liability_id', $liabilityId)
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LiabilityPayment $payment): LiabilityPaymentData => LiabilityPaymentData::fromModel($payment));
    }

    public function countForLiability(string $liabilityId): int
    {
        return LiabilityPayment::query()->where('liability_id', $liabilityId)->count();
    }

    /** @return Collection<string, string> */
    public function latestDateByLiability(): Collection
    {
        return LiabilityPayment::query()
            ->selectRaw('liability_id, max(payment_date) as latest')
            ->groupBy('liability_id')
            ->pluck('latest', 'liability_id')
            ->map(fn (mixed $date): string => CarbonImmutable::parse((string) $date)->toDateString());
    }

    public function find(string $id): ?LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->with(self::WITH)->find($id);

        return $payment === null ? null : LiabilityPaymentData::fromModel($payment);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->create($attributes);

        return LiabilityPaymentData::fromModel($payment->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): LiabilityPaymentData
    {
        $payment = LiabilityPayment::query()->findOrFail($id);
        $payment->update($attributes);

        return LiabilityPaymentData::fromModel($payment->load(self::WITH));
    }

    public function delete(string $id): void
    {
        LiabilityPayment::query()->where('id', $id)->delete();
    }

    /** @param array<string, mixed> $key */
    public function existsMatching(array $key): bool
    {
        return LiabilityPayment::query()
            ->where('liability_id', $key['liability_id'])
            ->where('payment_date', $key['payment_date'])
            ->where('total_amount', $key['total_amount'])
            ->exists();
    }
}
