<?php

namespace App\Domain\Audit\Data;

/**
 * Who acted. A null id is the console or the scheduler; `via` says which (`console`, `scheduler`).
 */
final readonly class AuditActorData
{
    public function __construct(
        public ?int $id,
        public ?string $role,
        public ?string $via = null,
    ) {}

    public static function console(): self
    {
        return new self(id: null, role: null, via: 'console');
    }

    public static function scheduler(): self
    {
        return new self(id: null, role: null, via: 'scheduler');
    }
}
