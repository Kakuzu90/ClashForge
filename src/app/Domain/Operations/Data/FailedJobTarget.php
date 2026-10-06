<?php

namespace App\Domain\Operations\Data;

/**
 * Which failed jobs an action or the job list is about (P2-19): one job by uuid, every job of a
 * class (the payload's `displayName`), or the jobs whose payload has no readable class.
 */
final class FailedJobTarget
{
    private function __construct(
        public readonly ?string $uuid,
        public readonly ?string $class,
        public readonly bool $unreadable,
    ) {}

    public static function job(string $uuid): self
    {
        return new self($uuid, null, false);
    }

    public static function ofClass(string $class): self
    {
        return new self(null, $class, false);
    }

    public static function unreadable(): self
    {
        return new self(null, null, true);
    }

    public function isJob(): bool
    {
        return $this->uuid !== null;
    }
}
