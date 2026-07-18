<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $account_id
 * @property TransactionType $type
 * @property string $amount
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property null|string $note
 * @property null|string $counterparty
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['account_id', 'type', 'amount', 'transaction_date', 'note', 'counterparty'];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(related: Account::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transaction_date' => 'date',
            'amount' => 'decimal:10',
        ];
    }
}
