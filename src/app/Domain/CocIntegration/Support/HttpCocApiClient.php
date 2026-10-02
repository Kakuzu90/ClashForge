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
use App\Domain\CocIntegration\Enums\TokenVerificationStatus;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use App\Domain\CocIntegration\Exceptions\TagNotFound;
use App\Domain\CocIntegration\Services\CocKeyPool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * `api.clashofclans.com/v1` over Laravel's HTTP client. Maps every row of specs/09 §7 to a
 * TagNotFound or a CocApiFailure. It neither retries nor caches: the decorators do (P2-07). The
 * one retry here is the key swap after a 403, because that is a key fault, not an API fault.
 */
final class HttpCocApiClient implements CocApiClient
{
    public function __construct(
        private readonly CocKeyPool $keys,
        private readonly ResponseMapper $mapper,
    ) {}

    public function player(PlayerTag $tag): PlayerData
    {
        $endpoint = 'players';
        $response = $this->call($endpoint, 'GET', '/players/'.$tag->urlEncoded());

        return $this->read($endpoint, $response, $tag, $this->mapper->player(...));
    }

    public function clan(ClanTag $tag): ClanData
    {
        $endpoint = 'clans';
        $response = $this->call($endpoint, 'GET', '/clans/'.$tag->urlEncoded());

        return $this->read($endpoint, $response, $tag, $this->mapper->clan(...));
    }

    public function verifyToken(PlayerTag $tag, #[SensitiveParameter] string $token): TokenVerificationResult
    {
        $endpoint = 'players.verifytoken';
        $response = $this->call($endpoint, 'POST', '/players/'.$tag->urlEncoded().'/verifytoken', ['token' => $token]);

        // The body echoes the token: never logged, never kept.
        $status = $this->read($endpoint, $response, $tag, $this->mapper->tokenStatus(...), logBody: false);

        return new TokenVerificationResult(
            tag: $tag,
            status: $status,
            verifiedAt: $status === TokenVerificationStatus::Ok ? Date::now() : null,
        );
    }

    /**
     * One cheap authenticated call with this key, for `coc:check-health`: true when the key works,
     * false when the API refused it (now marked unhealthy), null when the API did not answer.
     */
    public function probe(CocApiKey $key): ?bool
    {
        try {
            $response = $this->request($key, 'GET', '/locations', ['limit' => 1]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 403) {
            $this->keys->markUnhealthy($key, $this->apiReason($response));

            return false;
        }

        return $response->successful() ? true : null;
    }

    /**
     * Sends with the next healthy key; on a 403 the key leaves the rotation and the call is retried
     * once with another (specs/09 §7).
     *
     * @param  array<string, mixed>  $body
     */
    private function call(string $endpoint, string $method, string $path, array $body = []): Response
    {
        $tried = [];

        while (count($tried) < 2) {
            $key = $this->keys->next($tried);

            if ($key === null) {
                break;
            }

            try {
                $response = $this->request($key, $method, $path, $body);
            } catch (ConnectionException) {
                Log::warning('coc.request_failed', ['endpoint' => $endpoint, 'reason' => CocFailureReason::Timeout->value, 'key' => $key->id]);

                throw new CocApiFailure(CocFailureReason::Timeout, detail: $endpoint);
            }

            if ($response->status() !== 403) {
                return $response;
            }

            $this->keys->markUnhealthy($key, $this->apiReason($response));
            $tried[] = $key->id;
        }

        Log::error('coc.request_failed', ['endpoint' => $endpoint, 'reason' => CocFailureReason::NoHealthyKey->value, 'keys_tried' => $tried]);

        throw new CocApiFailure(CocFailureReason::NoHealthyKey, detail: $endpoint);
    }

    /**
     * @param  array<string, mixed>  $data  query for GET, JSON body otherwise
     *
     * @throws ConnectionException
     */
    private function request(CocApiKey $key, string $method, string $path, array $data): Response
    {
        $pending = Http::baseUrl((string) config('coc.base_url'))
            ->withToken($key->token())
            ->acceptJson()
            ->connectTimeout((int) config('coc.timeouts.connect'))
            ->timeout((int) config('coc.timeouts.total'));

        return $method === 'GET' ? $pending->get($path, $data) : $pending->post($path, $data);
    }

    /**
     * Turns a response into a DTO: 404 → TagNotFound, other errors → CocApiFailure, and a 200 that
     * does not decode or map → a malformed failure, logged with the raw body cut short (specs/23 §5)
     * unless the body may hold a secret.
     *
     * @template T
     *
     * @param  callable(Payload): T  $map
     * @return T
     */
    private function read(string $endpoint, Response $response, CocTag $tag, callable $map, bool $logBody = true): mixed
    {
        $status = $response->status();

        if ($status === 404) {
            throw new TagNotFound($tag->value);
        }

        if (! $response->successful()) {
            $failure = $this->failureFor($response);
            Log::warning('coc.request_failed', [
                'endpoint' => $endpoint,
                'reason' => $failure->reason->value,
                'status' => $status,
                'api_reason' => $this->apiReason($response),
            ]);

            throw $failure;
        }

        try {
            return $map(Payload::decode($response->body(), $endpoint));
        } catch (CocApiFailure $e) {
            $this->logMalformed($endpoint, $status, $e, $logBody ? $response->body() : null);

            throw $e;
        }
    }

    private function failureFor(Response $response): CocApiFailure
    {
        return match (true) {
            $response->status() === 429 => new CocApiFailure(CocFailureReason::Throttled, $this->retryAfter($response)),
            $response->status() === 503 && $this->apiReason($response) === 'inMaintenance' => new CocApiFailure(CocFailureReason::Maintenance, $this->retryAfter($response)),
            default => new CocApiFailure(CocFailureReason::ServerError, detail: (string) $response->status()),
        };
    }

    private function logMalformed(string $endpoint, int $status, CocApiFailure $e, ?string $body): void
    {
        Log::warning('coc.malformed_response', [
            'endpoint' => $endpoint,
            'status' => $status,
            'problem' => $e->getMessage(),
            'body' => $body === null ? null : substr($body, 0, (int) config('coc.log.malformed_body_bytes')),
        ]);
    }

    private function apiReason(Response $response): ?string
    {
        $reason = $response->json('reason');

        return is_string($reason) ? substr($reason, 0, 64) : null;
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return ctype_digit($header) ? (int) $header : null;
    }
}
