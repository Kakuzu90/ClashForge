<?php

namespace App\Support\Health;

/**
 * Per-check result. `Unknown` means "no fresh data yet" (e.g. right after a cache flush) and never
 * fails the endpoint on its own.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';
}
