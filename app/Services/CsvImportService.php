<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Import\ImportPreview;
use App\Data\Import\ImportResult;
use App\Data\Import\ImportRowResult;
use App\Enums\ImportTarget;
use App\Enums\TransactionType;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use App\Services\CsvImport\ImportRowException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CsvImportService
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private LiabilityRepositoryInterface $liabilities,
        private TransactionRepositoryInterface $transactions,
        private LiabilityPaymentRepositoryInterface $payments,
        private AccountBalanceSnapshotRepositoryInterface $snapshots,
    ) {}

    /** @return list<array<string, string>> */
    public function parse(string $contents): array
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($contents));

        if ($lines === false || $lines === [] || $lines[0] === '') {
            return [];
        }

        $header = str_getcsv(array_shift($lines), escape: '');
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, escape: '');
            $values = array_slice($values, 0, count($header));
            $values = array_pad($values, count($header), '');
            /** @var array<string, string> $row */
            $row = array_combine($header, array_map(fn (?string $v): string => (string) $v, $values));
            $rows[] = $row;
        }

        return $rows;
    }

    public function preview(ImportTarget $target, string $contents, bool $skipDuplicates): ImportPreview
    {
        $rows = $this->evaluate($target, $contents, $skipDuplicates);

        return new ImportPreview(
            total: $rows->count(),
            validCount: $this->countByStatus($rows, ImportRowResult::VALID),
            duplicateCount: $this->countByStatus($rows, ImportRowResult::DUPLICATE),
            errorCount: $this->countByStatus($rows, ImportRowResult::ERROR),
            rows: $rows,
        );
    }

    public function import(ImportTarget $target, string $contents, bool $skipDuplicates): ImportResult
    {
        $rows = $this->evaluate($target, $contents, $skipDuplicates);

        DB::transaction(function () use ($target, $rows): void {
            foreach ($rows as $row) {
                if ($row->status === ImportRowResult::VALID && $row->attributes !== null) {
                    $this->persist($target, $row->attributes);
                }
            }
        });

        return new ImportResult(
            imported: $this->countByStatus($rows, ImportRowResult::VALID),
            skipped: $this->countByStatus($rows, ImportRowResult::DUPLICATE),
            failed: $this->countByStatus($rows, ImportRowResult::ERROR),
            rows: $rows,
        );
    }

    /** @param Collection<int, ImportRowResult> $rows */
    private function countByStatus(Collection $rows, string $status): int
    {
        return $rows->where('status', $status)->count();
    }

    /** @return Collection<int, ImportRowResult> */
    private function evaluate(ImportTarget $target, string $contents, bool $skipDuplicates): Collection
    {
        $rows = new Collection;
        $seenKeys = [];

        foreach ($this->parse($contents) as $index => $raw) {
            $line = $index + 2; // +1 header, +1 to 1-index
            $normalized = $this->normalize($raw);

            $validator = Validator::make($normalized, $this->rules($target));
            if ($validator->fails()) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::ERROR, (string) $validator->errors()->first()));

                continue;
            }

            try {
                $attributes = $this->build($target, $normalized);
            } catch (ImportRowException $e) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::ERROR, $e->getMessage()));

                continue;
            }

            if ($skipDuplicates && $this->isDuplicate($target, $attributes)) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::DUPLICATE));

                continue;
            }

            $key = $this->duplicateKey($target, $attributes);

            if ($skipDuplicates && $key !== null && in_array($key, $seenKeys, true)) {
                $rows->push(new ImportRowResult($line, $raw, ImportRowResult::DUPLICATE));

                continue;
            }

            if ($key !== null) {
                $seenKeys[] = $key;
            }

            $rows->push(new ImportRowResult($line, $raw, ImportRowResult::VALID, null, $attributes));
        }

        return $rows;
    }

    /** @param array<string, mixed> $attributes */
    private function duplicateKey(ImportTarget $target, array $attributes): ?string
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => implode('|', [
                $attributes['account_id'],
                $attributes['transaction_date'],
                $attributes['type'],
                $attributes['amount'],
                $attributes['counterparty'],
            ]),
            ImportTarget::LIABILITY_PAYMENTS => implode('|', [
                $attributes['liability_id'],
                $attributes['payment_date'],
                $attributes['total_amount'],
            ]),
            ImportTarget::ACCOUNT_SNAPSHOTS => null,
        };
    }

    /**
     * @param  array<string, string>  $raw
     * @return array<string, string|null>
     */
    private function normalize(array $raw): array
    {
        return array_map(function (string $value): ?string {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }, $raw);
    }

    /** @return array<string, mixed> */
    private function rules(ImportTarget $target): array
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => [
                'institution' => ['required', 'string'],
                'account' => ['required', 'string'],
                'type' => ['required', Rule::enum(TransactionType::class)],
                'amount' => ['required', 'numeric'],
                'transaction_date' => ['required', 'date_format:Y-m-d'],
                'counterparty' => ['nullable', 'string', 'max:255'],
            ],
            ImportTarget::ACCOUNT_SNAPSHOTS => [
                'institution' => ['required', 'string'],
                'account' => ['required', 'string'],
                'balance' => ['required', 'numeric'],
                'snapshot_date' => ['required', 'date_format:Y-m-d'],
            ],
            ImportTarget::LIABILITY_PAYMENTS => [
                'liability' => ['required', 'string'],
                'payment_date' => ['required', 'date_format:Y-m-d'],
                'total_amount' => ['required', 'numeric'],
                'principal_portion' => ['nullable', 'numeric'],
                'interest_portion' => ['nullable', 'numeric'],
            ],
        };
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function build(ImportTarget $target, array $row): array
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => [
                'account_id' => $this->resolveAccountId($row),
                'type' => $row['type'],
                'amount' => $row['amount'],
                'transaction_date' => $row['transaction_date'],
                'note' => null,
                'counterparty' => $row['counterparty'],
            ],
            ImportTarget::ACCOUNT_SNAPSHOTS => [
                'account_id' => $this->resolveAccountId($row),
                'balance' => $row['balance'],
                'snapshot_date' => $row['snapshot_date'],
                'note' => null,
            ],
            ImportTarget::LIABILITY_PAYMENTS => [
                'liability_id' => $this->resolveLiabilityId($row),
                'payment_date' => $row['payment_date'],
                'total_amount' => $row['total_amount'],
                'principal_portion' => $row['principal_portion'],
                'interest_portion' => $row['interest_portion'],
                'note' => null,
            ],
        };
    }

    /** @param array<string, string|null> $row */
    private function resolveAccountId(array $row): string
    {
        $account = $this->accounts->findByInstitutionAndName((string) $row['institution'], (string) $row['account']);

        if ($account === null) {
            throw new ImportRowException("Account '{$row['account']}' at institution '{$row['institution']}' not found.");
        }

        return $account->id;
    }

    /** @param array<string, string|null> $row */
    private function resolveLiabilityId(array $row): string
    {
        $liability = $this->liabilities->findByName((string) $row['liability']);

        if ($liability === null) {
            throw new ImportRowException("Liability '{$row['liability']}' not found.");
        }

        return $liability->id;
    }

    /** @param array<string, mixed> $attributes */
    private function isDuplicate(ImportTarget $target, array $attributes): bool
    {
        return match ($target) {
            ImportTarget::TRANSACTIONS => $this->transactions->existsMatching($attributes),
            ImportTarget::LIABILITY_PAYMENTS => $this->payments->existsMatching($attributes),
            ImportTarget::ACCOUNT_SNAPSHOTS => false, // upsert is idempotent; never a "duplicate"
        };
    }

    /** @param array<string, mixed> $attributes */
    private function persist(ImportTarget $target, array $attributes): void
    {
        match ($target) {
            ImportTarget::TRANSACTIONS => $this->transactions->create($attributes),
            ImportTarget::ACCOUNT_SNAPSHOTS => $this->snapshots->upsert($attributes),
            ImportTarget::LIABILITY_PAYMENTS => $this->payments->create($attributes),
        };
    }
}
