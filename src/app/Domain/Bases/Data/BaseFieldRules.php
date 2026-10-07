<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Support\TagName;
use App\Support\Rules\ValidValueObject;

/**
 * Bases field rules for Form Requests, which may not reach the module's value objects directly
 * (specs/19 §1): validation and normalisation stay with `TagName`.
 */
final class BaseFieldRules
{
    /**
     * @return list<mixed>
     */
    public static function tag(): array
    {
        return ['string', 'max:100', new ValidValueObject(TagName::class)];
    }

    /**
     * A valid tag as stored (lowercase-kebab), so `Ring Base` filters as `ring-base`.
     */
    public static function normaliseTag(string $tag): string
    {
        return TagName::from($tag)->value;
    }
}
