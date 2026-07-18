<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiabilityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $name
 * @property string $principal_amount
 * @property string $currency_id
 * @property string $interest_rate
 * @property null|string $monthly_payment
 * @property \Illuminate\Support\Carbon $start_date
 * @property null|\Illuminate\Support\Carbon $end_date
 * @property bool $is_active
 * @property null|string $note
 */
class Liability extends Model
{
    /** @use HasFactory<LiabilityFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'institution_id',
        'name',
        'principal_amount',
        'currency_id',
        'interest_rate',
        'monthly_payment',
        'start_date',
        'end_date',
        'is_active',
        'note',
    ];

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

    /** @return HasMany<LiabilityPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(related: LiabilityPayment::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'principal_amount' => 'decimal:10',
            'monthly_payment' => 'decimal:10',
            'interest_rate' => 'decimal:4',
        ];
    }
}
