<?php

namespace App\Domain\CocIntegration\Support;

use LogicException;
use SensitiveParameter;

/**
 * One API key of the pool. The token is readable only through token(), hidden from var_dump and
 * stack traces, and cannot be serialised, so it never lands in a cache value or a queued job
 * payload (specs/09 §3). `id` is what logs and status pages show.
 */
final class CocApiKey
{
    public function __construct(
        public readonly string $id,
        #[SensitiveParameter] private readonly string $token,
    ) {}

    public static function fromToken(#[SensitiveParameter] string $token): self
    {
        return new self(substr(hash('sha256', $token), 0, (int) config('coc.key_pool.id_length')), $token);
    }

    public function token(): string
    {
        return $this->token;
    }

    /**
     * @return array{id: string}
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A CoC API key must never be serialised.');
    }
}
