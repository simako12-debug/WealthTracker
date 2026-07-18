<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $currency_id
 * @property string $name
 * @property AccountType $type
 * @property bool $is_active
 * @property null|string $note
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['institution_id', 'currency_id', 'name', 'type', 'is_active', 'note'];

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(related: Institution::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(related: Transaction::class);
    }

    /** @return HasMany<AccountBalanceSnapshot, $this> */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(related: AccountBalanceSnapshot::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }
}
