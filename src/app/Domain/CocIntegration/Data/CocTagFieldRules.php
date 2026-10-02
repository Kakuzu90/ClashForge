<?php

namespace App\Domain\CocIntegration\Data;

use App\Support\Rules\ValidValueObject;

/**
 * Form Request rules for tag fields, delegating to the value objects so a request cannot accept a
 * tag the client would refuse (FR-COC-2).
 */
final class CocTagFieldRules
{
    // Room for a pasted tag with spaces around it; anything longer is not a tag.
    public const MAX_INPUT = 32;

    /**
     * @return list<mixed>
     */
    public static function player(): array
    {
        return ['bail', 'required', 'string', 'max:'.self::MAX_INPUT, new ValidValueObject(PlayerTag::class)];
    }

    /**
     * @return list<mixed>
     */
    public static function clan(): array
    {
        return ['bail', 'required', 'string', 'max:'.self::MAX_INPUT, new ValidValueObject(ClanTag::class)];
    }
}
