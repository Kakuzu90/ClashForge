<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Data\CocApiStateData;
use App\Domain\CocIntegration\Enums\CocCircuitReason;
use App\Domain\CocIntegration\Enums\CocCircuitState;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\CocIntegration\Exceptions\CocApiFailure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

/**
 * The breaker of specs/09 §7, shared by every worker through the cache (`coc:circuit`).
 *
 * Opens after `circuit.consecutive_failures` failures in a row, or when more than `error_rate` of
 * at least `min_samples` calls in the last `window` seconds failed, counted in `bucket_seconds`
 * buckets. Maintenance opens it at once, for `Retry-After` when the API sends one (capped at
 * `max_open_seconds`). While open no call goes out; once `openUntil` passes, one call at a time
 * becomes the probe: success closes the breaker, failure opens it again for `probe_interval`.
 *
 * Failures are API faults (5xx, timeout, malformed, maintenance). A 404 is an answer, a 429 is the
 * budget's business and a 403 is a key fault, so none of them count.
 */
final class CircuitBreaker
{
    private const COUNTED = [
        CocFailureReason::ServerError,
        CocFailureReason::Timeout,
        CocFailureReason::Malformed,
        CocFailureReason::Maintenance,
    ];

    public function state(): CocApiStateData
    {
        $stored = $this->stored();

        if ($stored === null) {
            return new CocApiStateData(CocCircuitState::Closed, null, null);
        }

        $until = CarbonImmutable::createFromTimestamp($stored['until']);

        return new CocApiStateData(
            Date::now()->getTimestamp() < $stored['until'] ? CocCircuitState::Open : CocCircuitState::HalfOpen,
            CocCircuitReason::from($stored['reason']),
            $until,
        );
    }

    /**
     * Lets the call through, or refuses it while open. Once the open period is over, the one
     * caller that wins `coc:circuit:probe` (an atomic `Cache::add`) goes through as the probe.
     *
     * @return bool true when this call is the probe
     *
     * @throws CocApiFailure circuit_open or maintenance
     */
    public function allow(): bool
    {
        $stored = $this->stored();

        if ($stored === null) {
            return false;
        }

        $now = Date::now()->getTimestamp();

        if ($now >= $stored['until'] && Cache::add(CocCacheKeys::circuitProbe(), $now, (int) config('coc.circuit.probe_interval'))) {
            return true;
        }

        throw new CocApiFailure(
            $stored['reason'] === CocCircuitReason::Maintenance->value ? CocFailureReason::Maintenance : CocFailureReason::CircuitOpen,
            max(1, $stored['until'] - $now),
            'circuit open',
        );
    }

    /**
     * Only the probe closes an open breaker: a call admitted before it opened and answering late
     * says nothing about the API now. Closing starts a clean window, so the failures that opened
     * the breaker cannot reopen it on the next error.
     */
    public function recordSuccess(bool $probe = false): void
    {
        $this->setConsecutive(0);

        if ($this->stored() === null) {
            $this->count('ok');

            return;
        }

        if (! $probe) {
            return;
        }

        Cache::forget(CocCacheKeys::circuit());
        Cache::forget(CocCacheKeys::circuitProbe());
        $this->clearWindow();
        Log::info('coc.circuit_closed');
    }

    /**
     * @return bool whether the failure counted (an API fault); a probe that ends any other way
     *              hands the probe slot back at once (releaseProbe)
     */
    public function recordFailure(CocApiFailure $failure, bool $probe = false): bool
    {
        if (! in_array($failure->reason, self::COUNTED, true)) {
            return false;
        }

        $open = $this->stored() !== null;

        if ($failure->reason === CocFailureReason::Maintenance) {
            $this->open(CocCircuitReason::Maintenance, $failure->retryAfter);
        } elseif ($open) {
            // Only a failed probe extends the open period; a late failure from before is ignored.
            if ($probe) {
                $this->open(CocCircuitReason::Failures, null);
            }
        } else {
            $this->count('fail');
            $consecutive = $this->setConsecutive($this->consecutive() + 1);

            if ($consecutive >= (int) config('coc.circuit.consecutive_failures') || $this->errorRateExceeded()) {
                $this->open(CocCircuitReason::Failures, null);
            }
        }

        return true;
    }

    /**
     * The probe never reached a verdict (throttled, refused key, 429): let the next call probe.
     */
    public function releaseProbe(): void
    {
        Cache::forget(CocCacheKeys::circuitProbe());
    }

    /**
     * More than `rate` of at least `minSamples` calls failed (specs/09 §7).
     */
    public static function exceeds(int $ok, int $fail, int $minSamples, float $rate): bool
    {
        $samples = $ok + $fail;

        return $samples >= $minSamples && $samples > 0 && $fail / $samples > $rate;
    }

    /**
     * @return array{ok: int, fail: int} calls in the current window
     */
    public function window(): array
    {
        $size = (int) config('coc.circuit.bucket_seconds');
        $current = intdiv(Date::now()->getTimestamp(), $size);
        $buckets = intdiv((int) config('coc.circuit.window'), $size);
        $totals = ['ok' => 0, 'fail' => 0];

        for ($i = 0; $i < $buckets; $i++) {
            foreach (['ok', 'fail'] as $outcome) {
                $totals[$outcome] += (int) Cache::get(CocCacheKeys::circuitBucket($current - $i, $outcome), 0);
            }
        }

        return $totals;
    }

    private function errorRateExceeded(): bool
    {
        ['ok' => $ok, 'fail' => $fail] = $this->window();

        return self::exceeds($ok, $fail, (int) config('coc.circuit.min_samples'), (float) config('coc.circuit.error_rate'));
    }

    private function consecutive(): int
    {
        return (int) Cache::get(CocCacheKeys::circuitConsecutive(), 0);
    }

    /**
     * Rewritten with a fresh TTL on every change, so a slow run of failures keeps its count.
     */
    private function setConsecutive(int $value): int
    {
        Cache::put(CocCacheKeys::circuitConsecutive(), $value, $this->windowTtl());

        return $value;
    }

    private function clearWindow(): void
    {
        $size = (int) config('coc.circuit.bucket_seconds');
        $current = intdiv(Date::now()->getTimestamp(), $size);

        for ($i = 0; $i <= intdiv((int) config('coc.circuit.window'), $size); $i++) {
            Cache::forget(CocCacheKeys::circuitBucket($current - $i, 'ok'));
            Cache::forget(CocCacheKeys::circuitBucket($current - $i, 'fail'));
        }
    }

    private function open(CocCircuitReason $reason, ?int $retryAfter): void
    {
        $now = Date::now()->getTimestamp();
        // A stated end is honoured up to `max_open_seconds`, so one odd header cannot shut the
        // API off for days; past that the probe decides.
        $until = $now + min($retryAfter ?? (int) config('coc.circuit.probe_interval'), (int) config('coc.circuit.max_open_seconds'));
        $wasOpen = $this->stored() !== null;

        // Kept a day past the open period at most, so a stale entry cannot outlive a quiet API.
        Cache::put(CocCacheKeys::circuit(), ['reason' => $reason->value, 'until' => $until, 'opened_at' => $now], $until - $now + 86400);
        Cache::forget(CocCacheKeys::circuitProbe());

        if (! $wasOpen) {
            Log::error('coc.circuit_opened', ['reason' => $reason->value, 'until' => CarbonImmutable::createFromTimestamp($until)->toIso8601String()]);
        }
    }

    /**
     * @param  'ok'|'fail'  $outcome
     */
    private function count(string $outcome): void
    {
        $key = CocCacheKeys::circuitBucket(intdiv(Date::now()->getTimestamp(), (int) config('coc.circuit.bucket_seconds')), $outcome);
        Cache::add($key, 0, $this->windowTtl());
        Cache::increment($key);
    }

    private function windowTtl(): int
    {
        return (int) config('coc.circuit.window') + (int) config('coc.circuit.bucket_seconds');
    }

    /**
     * @return array{reason: string, until: int, opened_at: int}|null
     */
    private function stored(): ?array
    {
        $value = Cache::get(CocCacheKeys::circuit());

        if (! is_array($value) || ! is_string($value['reason'] ?? null) || ! is_int($value['until'] ?? null)
            || CocCircuitReason::tryFrom($value['reason']) === null) {
            return null;
        }

        return ['reason' => $value['reason'], 'until' => $value['until'], 'opened_at' => (int) ($value['opened_at'] ?? $value['until'])];
    }
}
