<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FxSource;
use Database\Factories\CurrencyPairFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $base_currency_id
 * @property string $quote_currency_id
 * @property FxSource $source
 * @property bool $is_active
 * @property null|string $note
 */
class CurrencyPair extends Model
{
    /** @use HasFactory<CurrencyPairFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = ['base_currency_id', 'quote_currency_id', 'source', 'is_active', 'note'];

    /** @return BelongsTo<Currency, $this> */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'base_currency_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(related: Currency::class, foreignKey: 'quote_currency_id');
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'source' => FxSource::class,
            'is_active' => 'boolean',
        ];
    }
}
