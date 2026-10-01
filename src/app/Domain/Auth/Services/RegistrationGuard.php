<?php

namespace App\Domain\Auth\Services;

use App\Support\Privacy\IpHash;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bot traps on the registration form (specs/11 "Spam and fake accounts"): a field people never
 * see, and an encrypted form token holding the time the form was shown and a nonce kept in the
 * session, so each rendered form allows one accepted submission and must be older than a few
 * seconds. A trapped submission gets the same answer as a real one. A form left open too long is
 * not a bot signal, so it gets a field error instead.
 */
class RegistrationGuard
{
    public const HONEYPOT = 'website';

    public const STARTED = 'started';

    private const NONCES = 'register.nonces';

    /** Open register tabs a session keeps tokens for. */
    private const MAX_NONCES = 5;

    public static function startToken(): string
    {
        $nonce = Str::random(32);
        $nonces = array_slice([...(array) session()->get(self::NONCES, []), $nonce], -self::MAX_NONCES);
        session()->put(self::NONCES, $nonces);

        return Crypt::encryptString(Date::now()->getTimestamp().'|'.$nonce);
    }

    /**
     * True when the token is genuine but older than the form's lifetime.
     */
    public function expired(mixed $started): bool
    {
        $token = $this->decode($started);

        return $token !== null && Date::now()->getTimestamp() - $token['shown_at'] > 60 * (int) config('platform.auth.register_max_form_age_minutes');
    }

    /**
     * Null when the submission looks human, else the reason it was trapped. Uses up the token.
     */
    public function trapped(mixed $honeypot, mixed $started, ?string $ip): ?string
    {
        $reason = $this->reason($honeypot, $started);

        if ($reason !== null) {
            Log::channel('security')->info('auth.registration_blocked', ['reason' => $reason, 'ip_hash' => IpHash::of($ip)]);
        }

        return $reason;
    }

    private function reason(mixed $honeypot, mixed $started): ?string
    {
        if (is_string($honeypot) ? trim($honeypot) !== '' : $honeypot !== null) {
            return 'honeypot';
        }

        if (! is_string($started) || $started === '') {
            return 'no_form_time';
        }

        $token = $this->decode($started);

        if ($token === null) {
            return 'bad_form_time';
        }

        if (! $this->consume($token['nonce'])) {
            return 'replayed_form';
        }

        if (Date::now()->getTimestamp() - $token['shown_at'] < (int) config('platform.auth.register_min_seconds')) {
            return 'too_fast';
        }

        return null;
    }

    private function consume(string $nonce): bool
    {
        $nonces = (array) session()->get(self::NONCES, []);

        if (! in_array($nonce, $nonces, true)) {
            return false;
        }

        session()->put(self::NONCES, array_values(array_diff($nonces, [$nonce])));

        return true;
    }

    /**
     * @return array{shown_at: int, nonce: string}|null
     */
    private function decode(mixed $started): ?array
    {
        if (! is_string($started) || $started === '') {
            return null;
        }

        try {
            [$shownAt, $nonce] = array_pad(explode('|', Crypt::decryptString($started), 2), 2, '');
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($shownAt) && $nonce !== '' ? ['shown_at' => (int) $shownAt, 'nonce' => $nonce] : null;
    }
}
