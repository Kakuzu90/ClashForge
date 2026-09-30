# 19 — Laravel Module & Folder Structure

## 1. Shape

A standard Laravel skeleton with a `app/Domain` tree added. **Not** a package-per-module setup
(no separate composer packages, no `modules/` with their own service providers and autoload
entries) — that machinery costs real time and buys isolation we can get from namespaces plus a
dependency-rule check in CI.

```
src/
├── app/
│   ├── Console/Commands/            # artisan commands (sync, reconcile, backfill)
│   ├── Domain/                      # ← the business
│   │   ├── Auth/
│   │   ├── Users/
│   │   ├── CocIntegration/
│   │   ├── PlayerAccounts/
│   │   ├── Clans/
│   │   ├── Bases/
│   │   ├── Recruitment/
│   │   ├── Marketplace/
│   │   ├── Messaging/
│   │   ├── Media/
│   │   ├── GameAssets/          # Supercell asset resolution — the single permitted touchpoint
│   │   ├── Notifications/
│   │   ├── Moderation/
│   │   ├── Audit/
│   │   └── Search/
│   ├── Http/
│   │   ├── Controllers/             # ← UI entry points: thin, return Inertia::render() or redirect
│   │   │   ├── Home/ Bases/ Profile/ Accounts/ Recruit/ Market/ Settings/
│   │   │   ├── Web/                 # redirects, downloads, copy-link, sitemap, health
│   │   │   ├── Upload/              # presigned intent + complete (JSON)
│   │   │   └── Admin/
│   │   ├── Middleware/              # incl. HandleInertiaRequests (shared props, root view, SSR toggle)
│   │   ├── Requests/                # form requests grouped by domain
│   │   └── Resources/               # only if a JSON API appears (Phase 7)
│   ├── Models/                      # thin re-export shims ONLY if needed for conventions
│   ├── Policies/                    # registered centrally; implementations may live in Domain
│   ├── Providers/
│   ├── Support/                     # framework-adjacent helpers shared by all modules
│   │   ├── Enums/ Casts/ Rules/ Traits/ Macros/ ValueObjects/
│   │   ├── Seo/ Health/ Observability/   # page meta, /health checks, Sentry scrubbing + log redaction
├── resources/
│   ├── css/app.css                  # Tailwind 4 `@theme` tokens
│   ├── js/
│   │   ├── app.ts  ssr.ts           # Inertia client + SSR entry points
│   │   ├── Pages/                   # ← the UI: one Vue page per Inertia::render() name
│   │   │   ├── Home/ Bases/ Profile/ Accounts/ Recruit/ Market/ Settings/ Admin/
│   │   ├── Layouts/                 # PublicLayout, AppLayout, AdminLayout (persistent layouts)
│   │   ├── Components/              # design system: ui/ game/ admin/; shell/ (header, navs, footer); dev/ (previews)
│   │   ├── navigation.ts            # primary nav config (items appear once their page exists)
│   │   ├── Composables/             # useLike, useUpload, useFilters …
│   │   ├── types/generated.d.ts     # from PHP Data DTOs + enums — generated, never hand-edited
│   │   └── routes/  actions/  wayfinder/   # Wayfinder output — generated, never hand-edited
│   └── views/app.blade.php          # the single root view: @vite, @inertiaHead, meta/OG/JSON-LD, @inertia
├── bootstrap/ config/ database/ public/ routes/ storage/ tests/
```

### Inside a module

```
app/Domain/Bases/
├── Actions/          PublishBase.php, ToggleLike.php, RecordView.php
├── Contracts/        BaseRepository.php, TrendingScorer.php     ← what others may depend on
├── Data/             PublishBaseData.php, BaseCardData.php      ← readonly DTOs
├── Enums/            BaseCategory.php, BaseStatus.php, Visibility.php
├── Events/           BasePublished.php, BaseLiked.php
├── Exceptions/       DuplicateLayoutException.php
├── Jobs/             AggregateBaseMetrics.php, RecomputeTrending.php
├── Listeners/        PublishWhenMediaReady.php
├── Models/           BaseLayout.php, BaseComment.php, BaseMetric.php   ← INTERNAL
├── Notifications/    NewCommentNotification.php
├── Policies/         BaseLayoutPolicy.php, BaseCommentPolicy.php
├── Queries/          BaseFeedQuery.php, RelatedBasesQuery.php   ← cross-table reads → DTOs
├── Services/         PublishBaseService.php, BaseInteractionService.php
├── Support/          BaseLink.php, LayoutHash.php
└── BasesServiceProvider.php    (optional: bindings, policy registration, event wiring)
```

Models living inside modules rather than `app/Models` is the one convention break from stock
Laravel. It is worth it: it makes the ownership boundary visible in the file path, and
`App\Domain\Bases\Models\BaseLayout` reads better than a flat namespace of 40 models.

## 2. Dependency rules (enforced in CI)

```
Http             ──▶  Domain\*\Services | Actions | Queries | Data | Enums | Contracts
Domain\X         ──▶  Domain\Y\Contracts | Services | Data | Events | Enums          ✅
Domain\X         ──▶  Domain\Y\Models                                                 ❌
Domain\*         ──▶  App\Http                                                      ❌
Domain\CocIntegration ──▶ any other Domain                                            ❌ (edge module)
Domain\Media          ──▶ any other Domain                                            ❌ (edge module)
Domain\GameAssets     ──▶ any other Domain                                            ❌ (edge module)
Domain\Audit          ──▶ any other Domain                                            ❌ (leaf)
Support          ──▶  nothing in Domain                                               ❌
```

On the frontend, `resources/js` may import only its own modules plus generated types/routes;
ESLint `no-restricted-imports`/`no-restricted-syntax` ban direct `axios`/`fetch` to app routes
outside the upload composable, and game-asset paths in `.vue` files.

Layering (Http / Domain / Support) is implemented with Deptrac (`deptrac.yaml`); the per-module rules
(no cross-module `Models` or internals, isolated edge/leaf modules) are Pest arch tests that discover
`app/Domain/*` automatically. Both run in `scripts/check.sh`; a violation fails with the exact file
and line. Exceptions require an entry in a `deptrac.allowlist` file with a comment explaining
why — visible, reviewable debt rather than silent erosion.

## 3. Naming conventions

| Thing | Convention | Example |
|---|---|---|
| Service | `{Verb}{Noun}Service` or `{Noun}Service` | `PublishBaseService`, `AccountSyncService` |
| Action (single operation) | imperative verb phrase, `__invoke` or `handle` | `ToggleLike`, `NormalizePlayerTag` |
| Query object | `{Noun}Query` | `BaseFeedQuery` |
| DTO | `{Noun}Data` | `PublishBaseData`, `PlayerData` |
| Event | past tense | `BasePublished`, `CocAccountVerified` |
| Listener | `{Verb}{Noun}` describing the reaction | `SendVerificationNotification` |
| Job | imperative | `SyncCocAccount`, `ProcessMedia` |
| Policy | `{Model}Policy` | `BaseLayoutPolicy` |
| Enum | singular noun | `BaseCategory`, `ReportReason` |
| Value object | domain noun | `PlayerTag`, `BaseLink`, `LayoutHash` |
| Controller | `App\Http\Controllers\{Area}\{Resource}Controller`, resourceful methods | `Bases\BaseController@show` |
| Inertia page | `resources/js/Pages/{Area}/{Action}.vue`, rendered as `'{Area}/{Action}'` | `Pages/Bases/Show.vue` |
| Vue component | PascalCase, prefixed by its folder | `UiButton.vue`, `GamePlayerCard.vue`, `AdminTable.vue` |
| Composable | `use{Thing}` | `useLike`, `useUpload` |
| Migration | `{timestamp}_create_{table}_table` | standard |
| Test | `{Subject}Test` in a mirrored path | `tests/Feature/Bases/PublishBaseTest.php` |

## 4. Routes

```
routes/
├── web.php          # public + authenticated, grouped by domain, one include per area
├── admin.php        # /admin, role-gated
├── channels.php     # empty until broadcasting exists
└── console.php      # scheduler definitions live in bootstrap/app.php (Laravel 11+) or here
```

`web.php` stays a table of contents:
```
require __DIR__.'/web/auth.php';
require __DIR__.'/web/bases.php';
require __DIR__.'/web/accounts.php';
...
```

**URL conventions:**

| Pattern | Route |
|---|---|
| `/` | home feed |
| `/u/{username}` | public profile |
| `/accounts/{ulid}` | CoC account detail |
| `/accounts/attach` | attach flow |
| `/bases` `/bases/th{n}` `/bases/{category}` `/bases/th{n}/{category}` | discovery |
| `/bases/{slug}` | base detail (`{ulid}-{title-slug}`) |
| `/bases/{slug}/copy` | server-side copy-click redirect |
| `/recruit` `/recruit/clans` `/recruit/players` `/recruit/{ulid}` | recruitment |
| `/market` `/market/{slug}` `/market/orders/{ulid}` | marketplace |
| `/search` | search |
| `/notifications` `/settings/*` `/dashboard` | authenticated |
| `/admin/*` | staff |
| `/uploads/intent` `/uploads/{ulid}/complete` `/uploads/{ulid}` | presigned upload flow (JSON, owner only, [10 §3](10-media-storage.md)) |
| `/health` `/sitemap.xml` `/robots.txt` | infrastructure; `/health` sits outside the `web` group (no session, no cookies) and replaces the framework's `/up` |

All state-changing routes are POST/PATCH/DELETE (Inertia `router`/`useForm`); nothing mutates on GET
(`/bases/{slug}/copy` records a counter, which is the one deliberate exception — it is deduped,
rate-limited and idempotent within its window).

## 5. Configuration

```
config/
├── coc.php        # API: base url, tokens, timeouts, cache TTLs, rate budget, sync tiers
├── media.php      # collections, size/dimension/duration limits, variants, quotas, sweep windows
├── moderation.php # reason codes, priority weights, auto-action rules, SLA targets
├── bases.php      # categories, TH range, trending weights, publish quotas
├── recruitment.php# activity levels, war preferences, expiry and bump windows
├── platform.php   # feature flags defaults, trust-ramp thresholds, reserved usernames
├── assets.php     # pack_version, manifest path, CDN base, enabled flag, placeholder + fallback rules
```

No magic numbers in application code. Every limit, weight and window named above is a config key,
overridable per environment, and asserted by a test that reads config rather than hardcoding.

## 6. Tests

```
tests/
├── Feature/          # mirrors the domain tree; the default place to write a test
│   ├── Auth/ Users/ PlayerAccounts/ Bases/ Recruitment/ Media/ Moderation/ Admin/ Search/
├── Unit/             # value objects, enums, scoring, parsers, policies
├── Security/         # authorization matrix, IDOR, mass assignment, upload, rate limits, XSS
├── Contract/         # CoC API mapper against recorded fixtures
├── Architecture/     # Pest arch tests: no Domain→Http, models not used cross-module, no `env()` outside config
├── Fixtures/         # recorded API responses, sample media
├── Support/          # factories helpers, fake clients, test traits
└── js/               # Vitest + Vue Test Utils: composables and components with logic
```

**Architecture tests** (Pest's `arch()`) run as unit tests and cover what Deptrac does not:
no `dd`/`dump`/`ray` in `app/`, no `env()` outside `config/`, every Inertia page has a
feature test asserting its component and props, no Eloquent model passed to `Inertia::render()`, every model has `$fillable`, every enum is backed.

## 7. Console commands

```
php artisan coc:sync-accounts            # scheduler entry point, tier-aware
php artisan coc:sync-clans
php artisan coc:rotate-keys
php artisan coc:check-health             # CoC key pool ready (P2-01)
php artisan platform:check-health        # external dependencies → /health state (every 5 min)
php artisan platform:heartbeat           # scheduler liveness marker (every minute, no summary line)
php artisan media:sweep-orphans
php artisan media:purge-deleted
php artisan media:reconcile-storage
php artisan bases:recompute-trending
php artisan bases:aggregate-metrics
php artisan stats:reconcile               # repairs all denormalised counters
php artisan moderation:expire-sanctions
php artisan notifications:prune
php artisan search:reindex {type?}
php artisan assets:make-manifest {path} --pack-version=  # write/refresh a pack's manifest from its files
php artisan assets:publish-pack {path} --pack-version=   # upload a curated game-asset pack to R2, byte-exact
php artisan assets:verify-pack [--pack-version=]         # bucket objects still match the manifest checksums
php artisan platform:anonymize-deleted
php artisan dev:seed-demo                 # non-production only
```

Every command is idempotent, safe to run twice, logs a summary line, and supports `--dry-run` where
it deletes anything.

## 8. Adding a new feature — the expected sequence

1. Write or update the spec section in `specs/`.
2. Create or extend the module folder under `app/Domain/{Module}`.
3. Migration + model + factory.
4. Enums and value objects for the new invariants.
5. Service/Action with its transaction boundary, dispatching domain events.
6. Policy, registered in `AuthServiceProvider`.
7. Form Request.
8. Controller returning `Inertia::render()` with DTO props + Vue page using existing design-system
   components; regenerate TS types and Wayfinder routes.
9. Feature test with `assertInertia` (happy path + authorization + validation), plus a security test if a new surface
   accepts user input or files.
10. Add the component variant to `/dev/components` if a new UI variant was introduced.
11. Update `specs/` if the implementation diverged from the plan — the spec is the artifact that
   outlives the ticket.
