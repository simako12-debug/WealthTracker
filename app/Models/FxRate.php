<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FxSource;
use Database\Factories\FxRateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $currency_from_id
 * @property string $currency_to_id
 * @property string $rate
 * @property Carbon $rate_date
 * @property FxSource $source
 */
class FxRate extends Model
{
    /** @use HasFactory<FxRateFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['currency_from_id', 'currency_to_id', 'rate', 'rate_date', 'source'];

    /** @return BelongsTo<Currency, $this> */
    public function currencyFrom(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'currency_from_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currencyTo(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'currency_to_id');
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'decimal:10',
            'source' => FxSource::class,
        ];
    }
}
