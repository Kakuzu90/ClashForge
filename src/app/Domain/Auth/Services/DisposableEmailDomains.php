<?php

namespace App\Domain\Auth\Services;

/**
 * The disposable-email blocklist (FR-AUTH-11, specs/11 "Spam and fake accounts"): the CC0
 * `disposable-email-domains` list, committed to the repo and refreshed monthly into storage by
 * `auth:refresh-disposable-domains`. The refreshed copy wins when it exists. A domain matches
 * itself and every subdomain (`x.mailinator.com`).
 */
class DisposableEmailDomains
{
    /**
     * @var array<string, true>|null
     */
    private ?array $domains = null;

    public function isDisposable(string $email): bool
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $labels = explode('.', strtolower(trim(substr($email, $at + 1), " .\t\n\r\0\x0B")));
        $domains = $this->domains();

        // Check the domain and each parent, stopping before the bare top-level label.
        for ($i = 0; $i < count($labels) - 1; $i++) {
            if (isset($domains[implode('.', array_slice($labels, $i))])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parses a list: one domain per line, `#` comments and blanks skipped.
     *
     * @return array<string, true>
     */
    public static function parse(string $contents): array
    {
        $domains = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line !== '' && ! str_starts_with($line, '#') && preg_match('/^[a-z0-9.-]+\.[a-z0-9-]+$/', $line) === 1) {
                $domains[$line] = true;
            }
        }

        return $domains;
    }

    /**
     * @return array<string, true>
     */
    private function domains(): array
    {
        if ($this->domains !== null) {
            return $this->domains;
        }

        foreach ([config('platform.auth.disposable_domains_refreshed'), config('platform.auth.disposable_domains_file')] as $path) {
            if (is_string($path) && is_file($path) && ($contents = file_get_contents($path)) !== false) {
                $parsed = self::parse($contents);
                if ($parsed !== []) {
                    return $this->domains = $parsed;
                }
            }
        }

        return $this->domains = [];
    }
}
