<?php

namespace App\Domain\Audit\Data;

/**
 * Who acted. A null id is the console or the scheduler; `via` says which (e.g. `console`).
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
}
