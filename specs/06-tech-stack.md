# 06 — Tech Stack Evaluation & Recommendation

## 1. Recommendation summary

| Layer | Choice | Confidence |
|---|---|---|
| Language / framework | PHP 8.3 + Laravel 12 | High — matches the committed Docker stack |
| Frontend | **Inertia 2 + Vue 3 (TypeScript, `<script setup>`) + Tailwind CSS 4** | High |
| SSR | Inertia SSR renderer (Node 22, own container) on public routes | Medium — one extra process, see §2 |
| Database | PostgreSQL 16 | High |
| Cache / Queue / Session | `database` driver (Postgres) | High for MVP |
| Object storage | Cloudflare R2 (S3 driver) | High |
| CDN / WAF | Cloudflare | High |
| Search | PostgreSQL FTS (`tsvector` + GIN) + `pg_trgm` | High for MVP |
| Media processing | Intervention Image v3 + ffmpeg on the worker | Medium — revisit at volume |
| Mail | Postmark or Amazon SES (Mailpit locally) | Medium |
| Admin UI | Hand-built Inertia + Vue pages under `/admin` | High |
| Error tracking | Sentry | High |
| Testing | Pest 3 (+ `assertInertia`), Vitest + Vue Test Utils, Laravel HTTP/queue/storage fakes | High |
| Static analysis | PHPStan/Larastan L6 (L8 on `app/Domain`) + Pint + Deptrac; `vue-tsc` + ESLint (`eslint-plugin-vue`) + Prettier | High |
| PHP ↔ TS contracts | `spatie/laravel-typescript-transformer` (DTOs, enums → TS types), Laravel Wayfinder (routes → TS helpers) | High |

## 2. Frontend: the actual decision

### Option A — Laravel + Inertia 2 + Vue 3 (chosen)

**For this project specifically:**
- The interactive surface is wider than it first looks: filter panels that update without losing
  scroll position, optimistic like/bookmark toggles, multi-file presigned uploads with progress, a
  searchable TH/unit picker, the notification bell, infinite scroll, and admin workflow screens
  (dispute resolution, report triage) with multi-step local state. Vue handles these on the client
  without a server round-trip per interaction — which matters for a mobile-heavy, high-latency
  audience.
- **Server-driven routing stays.** Inertia keeps Laravel routes, controllers, middleware, policies,
  Form Requests and session auth exactly as they are. No API layer, no client-side router, no token
  storage, no CORS, one deploy.
- **SEO and Open Graph are covered by SSR.** Public pages (base detail, profiles, discovery,
  recruitment, market) are rendered on the server by Inertia's SSR renderer, so crawlers and
  link-unfurlers get full HTML. Title, meta description, canonical, OG and JSON-LD are *also*
  emitted by the root Blade view from controller-supplied view data (`PageMeta::page()`), so share
  cards keep working even if the SSR process is down. The same call passes `meta.title` as a prop so
  the client title matches after hydration.
- **Typed contracts between PHP and Vue.** Page props are the modules' `Data` DTOs; TypeScript types
  are generated from them and route helpers from Laravel routes. A renamed field fails `vue-tsc` in
  CI, not in production.
- **Headroom for the roadmap.** The interactive base-layout editor and any live war dashboard slot
  into the same stack instead of forcing a second UI paradigm later.
- Payload stays controlled: per-page code splitting, partial reloads (`only:`), deferred props and
  merge props for infinite scroll keep public pages inside the JS budget (NFR-PERF-6).

**Costs, honestly:**
- **One more long-running process.** SSR needs a Node renderer (`php artisan inertia:start-ssr`)
  beside PHP-FPM. That is a deliberate exception to "no daemons we don't need"
  ([01](01-product-overview.md)) — it is the price of SEO. It runs in its own container, is
  stateless, is health-checked and auto-restarted, and pages degrade to client-side rendering if it
  dies. SSR is disabled for `/admin/*`, `/settings/*`, `/dashboard`, `/notifications`,
  `/account/*`, `/moderation/*` and `/disputes/*`, which need no crawlability.
- **Two languages, two toolchains.** PHP + TypeScript, Pest + Vitest, PHPStan + `vue-tsc`/ESLint.
  Mitigated by keeping Vue components presentational: business rules, authorization and validation
  stay in PHP.
- **Props are public.** Everything passed to a page is serialised into the HTML and the Inertia XHR
  response. Only DTOs cross the boundary — never Eloquent models or `toArray()`
  ([11](11-security.md)).
- SSR-unsafe code (touching `window`/`document` during setup) breaks the server render; a lint rule
  and an SSR smoke test in CI catch it.

### Option B — Laravel + Blade + Livewire (not chosen)

Better if: the team were PHP-only, the interactive surface were limited to a handful of toggles,
and avoiding a Node process outranked client-side interactivity.

Why not: every interaction is a server round-trip (expensive on the target mobile networks),
chatty components add write load to a database-backed cache/session stack, component state is
harder to type and test, and the planned base editor / war room would need Inertia anyway —
leaving two UI paradigms in one app.

### Option C — Separate API + decoupled frontend

Right when there are multiple consumers (web + native apps + third-party integrations) or separate
frontend/backend teams. Today it means: two repos, two deploys, CORS, token storage, duplicated
validation and authorization, and no SEO without another rendering tier — in exchange for
flexibility we would not use.

**Revisit if:** a native mobile app is funded, or we open a public API.

### Decision

**Inertia 2 + Vue 3 (Composition API, `<script setup lang="ts">`), SSR on public routes.** Keep
business logic out of controllers and Vue components entirely: controllers validate → call a
service/action → `Inertia::render()` with DTO props, or redirect. Vue components render props and
emit user intent; they never compute permissions, quotas, prices or eligibility. If a public JSON
API arrives (Phase 7), it reuses the same services and DTOs — only a thin resource layer is added.

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

**Admin panel.** Hand-built Inertia + Vue, not Filament/Nova. Reasoning: the admin surfaces here are
workflow tools (dispute resolution, report triage with content snapshots, ownership transfer), not
CRUD grids. A generic admin package optimises for the CRUD we barely have and fights us on the
workflows we actually need — plus it doubles the UI dependency surface we must style and secure.
Simple CRUD screens are one controller plus one Vue page built on shared `Admin*` table and
filter components.

**Auth scaffolding.** Use Laravel's Fortify for backend auth logic only (no Breeze/Jetstream views).
All views are ours, per the design-system constraint in [18](18-design-system.md).

**Frontend build.** Vite (already in compose), Vue 3 + TypeScript, `@inertiajs/vue3`, Tailwind CSS 4.
Client and SSR bundles are built together (`vite build && vite build --ssr`); the SSR bundle ships
in the same image and runs in the `ssr` container. Generated TS types and Wayfinder route helpers
are rebuilt in CI and a diff fails the build. Two self-hosted font families
(Lilita One, Inter) with `font-display: swap` — self-hosted, not Google Fonts CDN, to avoid a
third-party request and any consent question.

**Testing.** Pest. Feature tests are the default; unit tests for value objects, services with
complex rules, and policies. External edges always faked: `Http::fake()` for the CoC API,
`Storage::fake()` for R2, `Queue::fake()`/`Bus::fake()` for dispatch assertions, `Mail::fake()`.
A small set of contract tests runs the real `CocApiClient` against recorded fixtures.
Inertia responses are asserted with `assertInertia()` (component name + prop shape), with
`inertia.testing.ensure_pages_exist` on. Vue components with logic (composables, forms, the upload
queue) get Vitest + Vue Test Utils tests; purely presentational components are covered by the
`/dev/components` gallery and one SSR smoke test that renders every public page.

## 6. Explicitly rejected for the MVP

| Rejected | Reason |
|---|---|
| Redis / Horizon | Cost and ops burden before the load justifies it ([21](21-caching-strategy.md)) |
| Meilisearch / Typesense / Elasticsearch | Postgres FTS is sufficient below ~100k documents |
| Laravel Reverb / websockets | No real-time requirement in the MVP; requires Redis |
| Filament / Nova | Workflow-shaped admin, not CRUD-shaped |
| Livewire / Alpine | See §2 |
| React | Vue chosen for SFC ergonomics and smaller runtime; no team preference for React |
| Kubernetes | One VPS, one container set; compose or a PaaS is the correct tier |
| Serverless / Vapor | Long-running ffmpeg jobs and a low, predictable load profile fit a VPS better |
| A payments provider | Marketplace payments are out of scope ([15](15-marketplace-workflow.md)) |
| Third-party analytics with cookies | Consent burden; use self-hosted, cookieless analytics |
