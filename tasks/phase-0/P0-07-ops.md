---
id: P0-07
title: Add ops plumbing: /health, external health check, scheduler heartbeat, structured JSON logs with request ids, security log channel, Sentry
phase: 0
status: done
depends_on: [P0-01]
---

# Add ops plumbing: health, heartbeat, structured logging, error tracking

## Spec refs
- Core: specs/20 §2 "Platform" (`CheckExternalHealthJob`), §3 (`platform:check-health` every 5 min, `onFailure` alerts), §5 (job exception → Sentry), §6 (scheduler heartbeat, worker liveness); specs/25 Phase 0 "Ops"
- Plus: specs/03 §7 NFR-OBS-1/2/4, §3 NFR-AVAIL-2/3; specs/11 §3 (security events to a dedicated channel); specs/19 §1 (`Http/Controllers/Web/` health), §4 (`/health` route); specs/05 §6 (environments); specs/06 (Sentry); specs/09 §4 (health reports key-pool status, from P2-01)
- FR: none (foundation)
- Edge cases: specs/23 §9 "Postgres failover" (health returns 503), "cache table truncated" (health state rebuilds, never a false 500)

## Scope
- **Health endpoint** (`GET /health`, `Http/Controllers/Web/HealthController`): live checks for database (a `select 1`) and queue (the `jobs` table is reachable; the oldest pending `high`/`default` job is younger than the specs/20 §6 thresholds); cached checks for storage reachability and scheduler heartbeat. `200` when all pass, `503` when any required check fails. The body is `{status, checks: {name: ok|degraded|down}}` with no error text, hosts or versions. It replaces Laravel's default `/up`.
- **External health** (`platform:check-health`, every 5 min, `withoutOverlapping`/`onOneServer`/`runInBackground`): probes media storage (and later the CoC key pool, P2-01) and caches the result with a TTL. A missing cache entry reads as `unknown`, not `down`.
- **Scheduler heartbeat**: `platform:heartbeat` every minute stores a timestamp. Health reports `down` when it is older than 5 min (specs/20 §6).
- **Structured logging** (NFR-OBS-1): `AssignRequestId` middleware (accepts a sane inbound `X-Request-Id`, otherwise generates a ULID, echoes it on the response, `Log::withContext(request_id, user_id, route)`); a request summary line with duration; a JSON channel (`json`, stderr/daily) selectable by env; a `security` channel (specs/11 §3) for later tasks to use.
- **Sentry (backend)**: `sentry/sentry-laravel`, DSN and `release` from env (`APP_RELEASE`), traces sample rate from config, request id as a tag, PII scrubbed (no emails, IPs or cookies). Queue job exceptions are reported by the SDK.
- **Config keys**: `config/platform.php` → `health` (thresholds, cache TTL, required checks), `logging.request_id` header; `config/sentry.php` published and env-driven.

## Out of scope
- Staging deploy, backup restore drill, Sentry project and alert rules, R2/Cloudflare setup → **P0-09** (owner, open question 1)
- Frontend error tracking and source maps → P0-09 with a bundle-budget decision (owner, open question 2)
- Metrics dashboard, alert rules and the admin System Health page (NFR-OBS-3/5/6) → Admin v1 (P1-06) and the ops platform
- CoC key-pool health → P2-01; `platform:prune-operational-tables` → its own task

## Acceptance criteria
- Functional: NFR-OBS-1, NFR-OBS-2 (backend), NFR-OBS-4.
- Authorization: `/health` is public, returns no secrets, error text, hostnames or versions, and is rate limited.
- Edge cases: database down → 503; a stale heartbeat or failed storage probe → 503; an empty cache → `unknown`, not 503; a hostile `X-Request-Id` is replaced.
- States: n/a (no UI).

## Tests
- Feature: `/health` 200 all ok; 503 per failing required check (DB, queue, storage, heartbeat); response shape carries no detail; `unknown` after a cache flush; `/up` gone; rate limit.
- Feature: request id generated / accepted / replaced when malformed, echoed on the response, present in the log context; the summary line has route and duration.
- Feature: `platform:check-health` caches storage ok/down (faked disk that throws); heartbeat writes a timestamp; schedule entries (cron, overlap, one server, background).
- Unit: config-driven thresholds; Sentry `before_send` scrubs PII.
- Security: `/health` output has no secrets; the security log channel exists and is JSON.

## Notes

### Decisions
- `/health` replaces the framework's `/up`. It is registered outside the `web` group, so probes start no session and set no cookies. It is throttled per IP by `ThrottleHealth` on its own cache store (`file`), failing open: the database-backed limiter would otherwise turn a database outage into a 500 and cost a write per ping (spec review). synced → specs/19 §4, specs/21 §3.
- Required checks (503 when down) are database, queue, storage and scheduler, configurable in `platform.health.required`. A queue backlog is `degraded` (200), `unknown` never fails. synced → specs/03 NFR-OBS-4.
- Storage and scheduler state come from cache written by `platform:check-health` (5 min) and `platform:heartbeat` (every minute, the one task allowed at :00). The storage probe is one HEAD (`fileExists`) on `health/.probe` on the media disk, with no writes. `CheckExternalHealthJob` is the command, run inline. The heartbeat is stored with a 7-day TTL, not `forever` (specs/21 rule 4); an expired one reads `unknown`. Mutexes are 5 and 10 minutes so a killed run cannot stall the heartbeat for a day. synced → specs/20 §2–3, specs/21 §3.
- Request ids: a well-formed inbound `X-Request-Id` (8–64 of `[A-Za-z0-9._-]`) is kept, anything else is replaced by a ULID; it goes into Laravel `Context`, so every log line and queued job carries it. The route joins on `RouteMatched`, the user id on `Authenticated`. synced → specs/03 NFR-OBS-1.
- Health and security code lives in `App\Support\Health` and `App\Support\Observability`: platform plumbing, not a domain module.
- Sentry: release from `APP_RELEASE`, `/health` ignored, `before_send` scrubber keeps only the user id and drops cookies, bodies, auth/XSRF headers and query strings; the request id is a tag. synced → specs/11 §3.
- `expose_php = Off` and nginx `server_tokens off`, so no version reaches any response. synced → specs/11 "Transport & headers".
- (Security review) Nothing personal reaches Sentry or the JSON logs: `zend.exception_ignore_args = On` and frame vars dropped (stack arguments such as login credentials), `before_send_transaction` scrubs traces too, `max_request_body_size = never`, the query string is cut from `request.url`, client-IP headers (`X-Forwarded-For`, `CF-Connecting-IP`, `True-Client-IP`, `Forwarded`, …) are removed, and `QueryException` messages are replaced by SQLSTATE + placeholder SQL in Sentry and on the `json`/`security` channels. synced → specs/11 §3.
- (Security review) `TRUSTED_PROXIES` (CDN ranges in staging/production) feeds `TrustProxies::at()`, so client IPs, rate limits and logs see the visitor rather than the edge. synced → specs/11 "Transport & headers".
- `platform:check-health` logs a summary line; the minute heartbeat deliberately does not (1,440 identical lines a day). synced → specs/19 §7.

### Follow-ups
- P0-09: Sentry project, alert rules (NFR-OBS-5), frontend error tracking and source maps, `LOG_STACK=json` in staging and production, uptime monitor on `/health`.
- Locally `/health` reads storage `down` until `.env` has the media storage keys (see `.env.example`).

### Open questions
Resolved by the owner, 2026-09-30:
1. Split: P0-07 is code only; new task P0-09 (deploy runbook, staging deploy, backup restore drill, Sentry project and alerts, R2 + Cloudflare incl. the P0-05/P0-06 follow-ups) waits on the accounts. synced → specs/25 Phase 0 and §4, tasks/BOARD.md.
2. Backend-only Sentry for now; browser error tracking and source maps move to P0-09. synced → specs/03 NFR-OBS-2, specs/25.

### Verification
- Reviews: no UI, so the antislop audit does not apply; spec review 9 findings and security review 5 findings, all fixed.
- Live stack: `/health` served `ok` for database, queue and scheduler (heartbeat from the running scheduler container). A storage probe with MinIO credentials recorded `ok`; without them, `down` → 503. No `X-Powered-By`, and `Server: nginx` without a version. A JSON log line carried `extra.request_id`.
