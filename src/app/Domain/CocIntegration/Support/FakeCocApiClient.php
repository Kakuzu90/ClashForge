<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Contracts\CocApiClient;
use App\Domain\CocIntegration\Data\ClanData;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\CocTag;
use App\Domain\CocIntegration\Data\PlayerData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Data\TokenVerificationResult;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Enums\CocPriority;
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use Closure;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * The client for tests and local development (specs/05 §6, NFR-MAINT-5). Players and clans come
 * from what a test scripted, else from the fixture files (`players/{TAG}.json`,
 * `clans/{TAG}.json`, tag without `#`); anything else is a 404. Payloads go through the real
 * mapper, so a fixture the mapper refuses fails here too.
 */
final class FakeCocApiClient implements CocApiClient
{
    private ?Closure $onVerify = null;

    /** @var array<string, array<string, mixed>> */
    private array $players = [];

    /** @var array<string, array<string, mixed>> */
    private array $clans = [];

    /** @var array<string, list<string>> */
    private array $tokens = [];

    /** @var list<CocApiFailure|TagNotFound> */
    private array $failures = [];

    /** @var list<array{endpoint: string, tag: string}> */
    private array $calls = [];

    public function __construct(
        private readonly ResponseMapper $mapper,
        private readonly ?string $fixturesPath,
        #[SensitiveParameter] private readonly ?string $acceptedToken,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  an API-shaped player object
     */
    public function withPlayer(array $payload): self
    {
        $this->players[$this->bareTag($payload)] = $payload;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $payload  an API-shaped clan object
     */
    public function withClan(array $payload): self
    {
        $this->clans[$this->bareTag($payload)] = $payload;

        return $this;
    }

    /**
     * Runs during the next token checks, for tests of what changes while the API answers.
     */
    public function onVerify(Closure $callback): self
    {
        $this->onVerify = $callback;

        return $this;
    }

    public function acceptToken(PlayerTag $tag, #[SensitiveParameter] string $token): self
    {
        $this->tokens[$tag->bare()][] = $token;

        return $this;
    }

    /**
     * The next call fails this way, whatever it asks for.
     */
    public function failNext(CocFailureReason $reason, ?int $retryAfter = null): self
    {
        $this->failures[] = new CocApiFailure($reason, $retryAfter);

        return $this;
    }

    public function notFoundNext(): self
    {
        $this->failures[] = new TagNotFound('scripted');

        return $this;
    }

    /**
     * Every call made, in order, by endpoint and tag (never a token).
     *
     * @return list<array{endpoint: string, tag: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function player(PlayerTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false): PlayerData
    {
        $this->record('players', $tag);

        return $this->mapper->player(new Payload($this->find('players', $this->players, $tag), 'players'), Date::now());
    }

    public function clan(ClanTag $tag, CocPriority $priority = CocPriority::Interactive, bool $fresh = false): ClanData
    {
        $this->record('clans', $tag);

        return $this->mapper->clan(new Payload($this->find('clans', $this->clans, $tag), 'clans'), Date::now());
    }

    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult
    {
        $this->record('players.verifytoken', $tag);
        $this->find('players', $this->players, $tag);

        if ($this->onVerify !== null) {
            ($this->onVerify)();
        }

        $accepted = in_array($token, $this->tokens[$tag->bare()] ?? [], true)
            || ($this->acceptedToken !== null && $this->acceptedToken !== '' && hash_equals($this->acceptedToken, $token));

        return $accepted
            ? new TokenVerificationResult($tag, TokenVerificationStatus::Ok, Date::now())
            : new TokenVerificationResult($tag, TokenVerificationStatus::Invalid);
    }

    private function record(string $endpoint, CocTag $tag): void
    {
        $this->calls[] = ['endpoint' => $endpoint, 'tag' => $tag->value];

        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $scripted
     * @return array<string, mixed>
     */
    private function find(string $kind, array $scripted, CocTag $tag): array
    {
        if (isset($scripted[$tag->bare()])) {
            return $scripted[$tag->bare()];
        }

        $file = $this->fixturesPath === null ? null : "{$this->fixturesPath}/{$kind}/{$tag->bare()}.json";

        if ($file !== null && is_file($file)) {
            return Payload::decode((string) file_get_contents($file), $kind)->all();
        }

        throw new TagNotFound($tag->value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bareTag(array $payload): string
    {
        $tag = is_string($payload['tag'] ?? null) ? PlayerTag::tryFrom($payload['tag']) : null;

        return $tag?->bare() ?? throw new InvalidArgumentException('A scripted payload needs a valid tag.');
    }
}
