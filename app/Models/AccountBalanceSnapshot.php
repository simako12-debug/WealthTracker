<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountBalanceSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property string $balance
 * @property \Illuminate\Support\Carbon $snapshot_date
 * @property null|string $note
 */
class AccountBalanceSnapshot extends Model
{
    /** @use HasFactory<AccountBalanceSnapshotFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['account_id', 'balance', 'snapshot_date', 'note'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'balance' => 'decimal:10',
        ];
    }
}
