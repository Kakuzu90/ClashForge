<?php

namespace App\Domain\Auth\Support;

/**
 * "Firefox on Windows" from a User-Agent header, for the session list and the new-sign-in email
 * (specs/07 `sessions.device_label`). Coarse on purpose: browser family and OS, no versions.
 */
final class DeviceLabel
{
    /**
     * Checked in order: Edge, Opera and Samsung Internet also claim Chrome, and Chrome claims Safari.
     */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    /**
     * iPad and iPhone before macOS (iPadOS can claim "Mac OS X"), Android before Linux.
     */
    private const SYSTEMS = [
        'iPad' => 'iPadOS',
        'iPhone' => 'iOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'CrOS' => 'ChromeOS',
        'Mac OS X' => 'macOS',
        'Macintosh' => 'macOS',
        'Linux' => 'Linux',
    ];

    public static function fromUserAgent(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;
        $browser = self::first(self::BROWSERS, $userAgent);
        $system = self::first(self::SYSTEMS, $userAgent);

        return match (true) {
            $browser !== null && $system !== null => "{$browser} on {$system}",
            $browser !== null => $browser,
            $system !== null => "Browser on {$system}",
            default => 'Unknown device',
        };
    }

    /**
     * @param  array<string, string>  $needles
     */
    private static function first(array $needles, string $haystack): ?string
    {
        foreach ($needles as $needle => $name) {
            if (str_contains($haystack, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
