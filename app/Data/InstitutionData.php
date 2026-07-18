<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Spatie\LaravelData\Data;

final class InstitutionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public InstitutionType $type,
        public null|string $note,
    ) {
    }

    public static function fromModel(Institution $institution): self
    {
        return new self(
            id: $institution->id,
            name: $institution->name,
            type: $institution->type,
            note: $institution->note,
        );
    }
}
