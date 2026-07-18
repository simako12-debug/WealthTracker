<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\TransactionData;
use App\Models\Transaction;
use Illuminate\Support\Collection;

final readonly class TransactionRepository implements TransactionRepositoryInterface
{
    private const array WITH = ['account.institution', 'account.currency'];

    /** @return Collection<int, TransactionData> */
    public function recent(int $limit): Collection
    {
        return Transaction::query()
            ->with(self::WITH)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction): TransactionData => TransactionData::fromModel($transaction));
    }

    public function find(string $id): ?TransactionData
    {
        $transaction = Transaction::query()->with(self::WITH)->find($id);

        return $transaction === null ? null : TransactionData::fromModel($transaction);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TransactionData
    {
        $transaction = Transaction::query()->create($attributes);

        return TransactionData::fromModel($transaction->load(self::WITH));
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $id, array $attributes): TransactionData
    {
        $transaction = Transaction::query()->findOrFail($id);
        $transaction->update($attributes);

        return TransactionData::fromModel($transaction->load(self::WITH));
    }

    public function delete(string $id): void
    {
        Transaction::query()->where('id', $id)->delete();
    }
}
