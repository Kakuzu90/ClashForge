# 06 — Tech Stack Evaluation & Recommendation

## 1. Recommendation summary

| Layer | Choice | Confidence |
|---|---|---|
| Language / framework | PHP 8.3 + Laravel 12 | High — matches the committed Docker stack |
| Frontend | **Livewire 3 + Alpine.js + Tailwind CSS 4** | High |
| Database | PostgreSQL 16 | High |
| Cache / Queue / Session | `database` driver (Postgres) | High for MVP |
| Object storage | Cloudflare R2 (S3 driver) | High |
| CDN / WAF | Cloudflare | High |
| Search | PostgreSQL FTS (`tsvector` + GIN) + `pg_trgm` | High for MVP |
| Media processing | Intervention Image v3 + ffmpeg on the worker | Medium — revisit at volume |
| Mail | Postmark or Amazon SES (Mailpit locally) | Medium |
| Admin UI | Hand-built Livewire under `/admin` | High |
| Error tracking | Sentry | High |
| Testing | Pest 3, Laravel HTTP/queue/storage fakes | High |
| Static analysis | PHPStan/Larastan L6 (L8 on `app/Domain`) + Pint + Deptrac | High |

## 2. Frontend: the actual decision

### Option A — Laravel + Blade + Livewire (recommended)

**For this project specifically:**
- The product is **content-first and SEO-critical**. Public base pages and player profiles must be
  server-rendered, crawlable and shareable with rich Open Graph cards. Livewire gives that for free;
  an SPA makes you build SSR to get it back.
- The interactive surface is **narrow and local**: like/bookmark toggles, filter panels, upload
  progress, notification bell, infinite scroll, admin tables. None of these need a client-side
  router or a client-side store.
- **One language, one mental model, one deploy.** With a small team, every hour spent on a TypeScript
  API client is an hour not spent on the dispute workflow.
- Forms with file uploads, validation and authorization are Livewire's strongest case, and this app
  is mostly forms with file uploads.
- Payload size stays small, which matters because the audience is mobile-heavy in regions where
  data is expensive.

**Costs, honestly:**
- Every interaction is a network round-trip. Mitigated by doing genuinely local UI in Alpine
  (dropdowns, modals, tab switching, optimistic like animation) and reserving Livewire for state
  that touches the server.
- Livewire's server-state model means chatty components can generate load; we cap it with
  `wire:model.blur`/`.live.debounce`, computed properties and a per-user `global-write` limiter.
- If a genuinely app-like surface appears later (a live war dashboard, real-time chat), Livewire is
  the wrong tool, and we would add Inertia for that section only. Nothing in the architecture
  prevents that.

### Option B — Laravel + Inertia + Vue/React

Better if: the UI were interaction-heavy, the team had strong frontend specialists, or the product
needed rich client-side state (drag-and-drop base editor, live war room).

Why not now: dual-stack build/debug/test cost, SSR needed for SEO (a Node process we then have to
operate — directly against the low-cost, no-extra-daemon constraint), and a large share of the work
would be re-describing server state as props and types.

**Revisit if:** we build an interactive base-layout editor, or real-time features become core.

### Option C — Separate API + decoupled frontend

Right when there are multiple consumers (web + native apps + third-party integrations) or separate
frontend/backend teams. Today it means: two repos, two deploys, CORS, token storage, duplicated
validation and authorization, and no SEO without another rendering tier — in exchange for
flexibility we would not use.

**Revisit if:** a native mobile app is funded, or we open a public API.

### Decision

**Livewire 3.** The one non-obvious consequence to plan for: keep business logic out of Livewire
components entirely (they call services, like controllers do), so that if a section ever migrates
to Inertia or an API, only the thin presentation layer is rewritten.

## 3. Database: PostgreSQL over MySQL

Already chosen in the committed Docker stack; the reasons hold:

| Capability | Why it matters here |
|---|---|
| `jsonb` + GIN indexes | Heroes/troops/spells/equipment from the CoC API are naturally nested and change shape between game updates. Storing them as `jsonb` with indexed extracted columns avoids a migration every balance patch. |
| Full-text search (`tsvector`, `ts_rank`) + `pg_trgm` | Delivers MVP search with zero extra infrastructure, including fuzzy IGN matching. |
| Partial and expression indexes | `WHERE status='verified'` uniqueness on player tags, case-insensitive username uniqueness — one index instead of a shadow column. |
| True `CHECK` constraints and deferrable FKs | Domain invariants enforced by the database, not only the app. |
| Native `uuid`/`ulid` handling, arrays, `EXCLUDE` constraints | Useful for tags and time-boxed sanctions. |
| Table partitioning | The escape hatch for `base_view_events` and snapshots at scale. |

MySQL would work; Postgres is simply a better fit for the JSON-heavy game data and lets us defer a
search engine longer.

## 4. Cache, queue and session on `database`

**The bet:** at MVP scale (≤50k MAU, ≤2 queue workers, ≤50 jobs/min), Postgres handles cache, queue
and session tables without measurable pain, and saves an entire managed service.

**Hard rules that make the bet reversible:**
1. All cache access is through `Cache::` / `cache()` — never a driver-specific call, never
   `Redis::` anything.
2. All queueing is through `dispatch()` / `Queue::` — no Horizon-specific APIs, no Lua scripts.
3. Rate limiting only via `RateLimiter` / `Illuminate\Support\Facades\Cache::lock`.
4. Locks use `Cache::lock()` (the database store supports it), never `flock` or static memory.
5. No cache driver assumptions in tests: tests run on the `array` store.
6. Queue names are explicit and few: `high`, `default`, `media`, `sync`, `low`.

**Operational cost of `database` driver (plan for it):**
- `cache` table needs a scheduled prune (`cache:prune-stale-tags` is Redis-specific; use a scheduled
  delete of expired rows).
- `jobs` table needs an index on `(queue, reserved_at, available_at, id)` — Laravel's default
  migration provides it; do not remove it.
- `sessions` table needs `php artisan session:prune` (scheduled) or it grows unbounded.
- Long-polling workers should use `--sleep=1 --max-time=3600` (already in `docker-compose.yml`) and
  `--rest=0.2` so they do not hammer the database.
- Avoid queueing tens of thousands of jobs in one burst (notification fan-out must be chunked).

### When Redis becomes necessary

| Signal | Threshold | What breaks | Action |
|---|---|---|---|
| Queue throughput | > 100 jobs/min sustained, or `jobs` table > 50k rows | Row-lock contention on `jobs`, worker polling load | Move `QUEUE_CONNECTION=redis`, add Horizon |
| Cache churn | Cache writes > 200/s, or `cache` table > 500 MB | Table bloat, vacuum pressure | Move `CACHE_STORE=redis` |
| Rate limiting | > 500 limiter hits/s, or limiter writes visible in slow-query logs | Every check is a write | Move cache to Redis (limiters follow) |
| Sessions | > 5k concurrent sessions or session writes competing with reads | Write amplification on every request | `SESSION_DRIVER=redis` |
| Real-time features | Any websockets/presence/broadcast work | Requires a broadcast driver | Redis + Reverb |
| Multi-server app tier | ≥2 app servers with cache-dependent behaviour | Database cache is shared and fine, but latency adds up | Redis for latency |

All six are `.env` changes plus an infrastructure add — no application code changes. That property is
the requirement; verify it with a CI job that runs the test suite with `CACHE_STORE=array` and, once
Redis exists in CI, with `redis`.

## 5. Supporting choices

**Media processing.** Intervention Image v3 (GD, already in the Dockerfile) for resizing and EXIF
stripping. ffmpeg on the `media` queue worker for video validation, transcode to h264/aac ≤1080p and
poster-frame extraction. Runs in-house until: median transcode > 90 s, or the media queue backs up
> 15 min during peak, or CPU steal on the worker box exceeds 20%. Then move to Cloudflare Stream or
a transcoding service — the `MediaProcessor` interface exists for that swap.

**Search.** Postgres FTS behind a `SearchService` interface. Migration trigger and target in
[17](17-search-and-discovery.md).

**Admin panel.** Hand-built Livewire, not Filament/Nova. Reasoning: the admin surfaces here are
workflow tools (dispute resolution, report triage with content snapshots, ownership transfer), not
CRUD grids. A generic admin package optimises for the CRUD we barely have and fights us on the
workflows we actually need — plus it doubles the UI dependency surface we must style and secure.
Simple CRUD tables in Livewire are ~60 lines each.

**Auth scaffolding.** Use Laravel's Fortify for backend auth logic only (no Breeze/Jetstream views).
All views are ours, per the design-system constraint in [18](18-design-system.md).

**Frontend build.** Vite (already in compose), Tailwind CSS 4, Alpine. Two self-hosted font families
(Lilita One, Inter) with `font-display: swap` — self-hosted, not Google Fonts CDN, to avoid a
third-party request and any consent question.

**Testing.** Pest. Feature tests are the default; unit tests for value objects, services with
complex rules, and policies. External edges always faked: `Http::fake()` for the CoC API,
`Storage::fake()` for R2, `Queue::fake()`/`Bus::fake()` for dispatch assertions, `Mail::fake()`.
A small set of contract tests runs the real `CocApiClient` against recorded fixtures.

## 6. Explicitly rejected for the MVP

| Rejected | Reason |
|---|---|
| Redis / Horizon | Cost and ops burden before the load justifies it ([21](21-caching-strategy.md)) |
| Meilisearch / Typesense / Elasticsearch | Postgres FTS is sufficient below ~100k documents |
| Laravel Reverb / websockets | No real-time requirement in the MVP; requires Redis |
| Filament / Nova | Workflow-shaped admin, not CRUD-shaped |
| Inertia + Vue/React | See §2 |
| Kubernetes | One VPS, one container set; compose or a PaaS is the correct tier |
| Serverless / Vapor | Long-running ffmpeg jobs and a low, predictable load profile fit a VPS better |
| A payments provider | Marketplace payments are out of scope ([15](15-marketplace-workflow.md)) |
| Third-party analytics with cookies | Consent burden; use self-hosted, cookieless analytics |
