<?php

namespace App\Domain\CocIntegration\Support;

/**
 * Every cache key the module writes, in one place (specs/21 §1 rule 5).
 */
final class CocCacheKeys
{
    public static function keyUnhealthy(string $keyId): string
    {
        return "coc:key:{$keyId}:unhealthy";
    }

    public static function keyCursor(): string
    {
        return 'coc:key:cursor';
    }
}
