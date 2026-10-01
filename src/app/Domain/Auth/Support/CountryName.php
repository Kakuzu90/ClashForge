<?php

namespace App\Domain\Auth\Support;

use Illuminate\Http\Request;
use Locale;

/**
 * "Germany" from DE, for the session list and security emails.
 */
final class CountryName
{
    /**
     * The country the CDN puts in `platform.auth.country_header`, believed only on requests that
     * came through a trusted proxy: a direct request could forge it to make a takeover look like
     * the owner's own device. XX (unknown) and T1 (Tor) are not countries.
     */
    public static function fromRequest(Request $request): ?string
    {
        if (! $request->isFromTrustedProxy()) {
            return null;
        }

        $header = $request->header((string) config('platform.auth.country_header'));
        $code = is_string($header) ? strtoupper(trim($header)) : '';

        return preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['XX', 'T1'], true) ? $code : null;
    }

    public static function of(?string $code): ?string
    {
        if ($code === null || preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return null;
        }

        $name = Locale::getDisplayRegion('-'.$code, 'en');

        return ! is_string($name) || $name === '' || $name === $code ? $code : $name;
    }
}
