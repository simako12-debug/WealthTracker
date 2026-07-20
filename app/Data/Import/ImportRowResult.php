<?php

declare(strict_types=1);

namespace App\Data\Import;

final readonly class ImportRowResult
{
    public const string VALID = 'valid';

    public const string DUPLICATE = 'duplicate';

    public const string ERROR = 'error';

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, mixed>|null  $attributes
     */
    public function __construct(
        public int $line,
        public array $raw,
        public string $status,
        public ?string $error = null,
        public ?array $attributes = null,
    ) {}
}
