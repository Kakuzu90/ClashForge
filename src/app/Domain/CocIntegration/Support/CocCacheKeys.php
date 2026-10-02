<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Data\CocTag;

/**
 * Every cache and rate-limiter key the module writes, in one place (specs/21 §1 rule 5). Tags go
 * in without their `#`.
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

    public static function player(CocTag $tag): string
    {
        return "coc:player:{$tag->bare()}";
    }

    public static function playerLast(CocTag $tag): string
    {
        return "coc:player:{$tag->bare()}:last";
    }

    public static function clan(CocTag $tag): string
    {
        return "coc:clan:{$tag->bare()}";
    }

    public static function clanLast(CocTag $tag): string
    {
        return "coc:clan:{$tag->bare()}:last";
    }

    /**
     * Negative cache, per kind: a player and a clan may share a tag.
     *
     * @param  'player'|'clan'  $kind
     */
    public static function notFound(string $kind, CocTag $tag): string
    {
        return "coc:404:{$kind}:{$tag->bare()}";
    }

    public static function circuit(): string
    {
        return 'coc:circuit';
    }

    public static function circuitConsecutive(): string
    {
        return 'coc:circuit:consecutive';
    }

    /**
     * @param  'ok'|'fail'  $outcome
     */
    public static function circuitBucket(int $bucket, string $outcome): string
    {
        return "coc:circuit:bucket:{$bucket}:{$outcome}";
    }

    public static function circuitProbe(): string
    {
        return 'coc:circuit:probe';
    }

    /**
     * Rate-limiter keys: `global` and `background`, per second or per minute (specs/09 §4).
     *
     * @param  'global'|'background'  $bucket
     * @param  'second'|'minute'  $per
     */
    public static function rate(string $bucket, string $per): string
    {
        return "coc-rate:{$bucket}:{$per}";
    }

    public static function rateKey(string $keyId): string
    {
        return "coc-rate:key:{$keyId}";
    }
}
