<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InstitutionType;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property InstitutionType $type
 * @property null|string $note
 */
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = ['name', 'type', 'note'];

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(related: Account::class);
    }

    /** @return HasMany<Liability, $this> */
    public function liabilities(): HasMany
    {
        return $this->hasMany(related: Liability::class);
    }

    /** @return array<string,mixed> */
    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
        ];
    }
}
