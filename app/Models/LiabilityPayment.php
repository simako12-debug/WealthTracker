<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiabilityPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $liability_id
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string $total_amount
 * @property null|string $principal_portion
 * @property null|string $interest_portion
 * @property null|string $note
 */
class LiabilityPayment extends Model
{
    /** @use HasFactory<LiabilityPaymentFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'liability_id',
        'payment_date',
        'total_amount',
        'principal_portion',
        'interest_portion',
        'note',
    ];

    /** @return BelongsTo<Liability, $this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(related: Liability::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'total_amount' => 'decimal:10',
            'principal_portion' => 'decimal:10',
            'interest_portion' => 'decimal:10',
        ];
    }
}
