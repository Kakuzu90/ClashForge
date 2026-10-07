<?php

namespace App\Domain\Bases\Support;

use App\Support\ValueObjects\StringValueObject;

/**
 * What identifies a layout for duplicate detection (FR-BASE-11): the sha256 of the link's layout
 * id, so two links to the same layout match whatever their language path or extra parameters.
 */
final class LayoutHash extends StringValueObject
{
    public static function of(BaseLink $link): self
    {
        return new self(hash('sha256', $link->layoutId()));
    }

    protected static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    protected static function validate(string $value): ?string
    {
        return preg_match('/^[0-9a-f]{64}$/D', $value) === 1 ? null : 'A layout hash is 64 hex characters.';
    }
}
