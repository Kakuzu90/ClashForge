# 05 — Recommended Architecture

## 1. Decision: modular monolith

One Laravel application, one deployable artifact, one database, internally partitioned into
domain modules with enforced boundaries.

**Why not microservices:** the team is small, the domains are chatty (a base card needs profile +
CoC account + media + counters in one render), and there is no independent scaling pressure. Every
service boundary would buy distributed-systems cost and sell nothing.

**Why not a plain Laravel app:** without boundaries, `app/Models` becomes a 40-model junk drawer and
the CoC API leaks into Vue pages by month three. Modules are the cheap insurance.

**When this changes:** the media pipeline is the only realistic candidate for extraction (different
resource profile — CPU-bound ffmpeg work). It is designed as a queue-consuming module with no
synchronous callers precisely so it can be pulled out later. See [22](22-scaling.md).

```
                         ┌──────────────┐
  Browser ──────────────▶│  Cloudflare  │── static + media (R2 via CDN)
                         │   CDN / WAF  │
                         └──────┬───────┘
                                │ HTML / Inertia XHR (JSON page objects)
                         ┌──────▼───────┐
                         │    nginx     │
                         └──────┬───────┘
                                │ FastCGI
      ┌─────────────────────────▼─────────────────────────┐
      │              Laravel application                   │
      │  HTTP layer: controllers → Inertia::render(),       │
      │  form requests, policies, shared props              │
      │  ───────────────────────────────────────────────    │
      │  Domain modules (see §2)                            │
      │  ───────────────────────────────────────────────    │
      │  Infrastructure: CoC client, storage, mail, search   │
      └───────┬──────────────────┬──────────────────┬──────┘
              │                  │                  │
       ┌──────▼─────┐     ┌──────▼──────┐    ┌──────▼──────┐
       │ PostgreSQL │     │  Queue      │    │ Cloudflare  │
       │ (data +    │◀────│  workers    │───▶│     R2      │
       │  cache +   │     │  (same code)│    └─────────────┘
       │  queue +   │     └──────┬──────┘
       │  sessions) │            │
       └────────────┘     ┌──────▼──────┐    ┌──────────────┐
                          │  Scheduler  │───▶│ CoC Official │
                          └─────────────┘    │     API      │
                                             └──────────────┘
```

Public-route HTML is produced by the **Inertia SSR renderer** — a stateless Node process on the
same host that Laravel calls over localhost with the page object. If it is unavailable, Laravel
returns the client-rendered shell; meta/OG/JSON-LD are emitted by the root Blade view either way.

## 2. Modules and their boundaries

Each module owns its tables, its models, its services and its events. Anything outside the module
talks to it through **(a)** its public service classes, **(b)** its read-model/DTO objects, or
**(c)** its domain events. Never through its Eloquent models directly.

| Module | Owns (tables) | Public surface | Depends on |
|---|---|---|---|
| **Auth** | `users`, `sessions`, `password_reset_tokens`, `two_factor_*` | `RegistrationService`, `EmailVerificationService`, `SessionService`, `UserStatusService`, `RoleAssignmentService`, `UserLookupService` (username → listed account, for other modules), `AdminUserQuery` (admin user list and detail read model) | — |
| **Users** | `profiles`, `privacy_settings`, `user_stats`, `follows` | `ProfileService`, `PrivacySettingsService`, `PrivacyPolicyResolver`, `PublicProfileReadModel`, `CacheInvalidator` (profile and privacy keys) | Auth, Media |
| **CocIntegration** | `coc_api_requests` (log), cache entries | `CocApiClient` (interface), `PlayerLookup`, `ClanLookup`, `TokenVerifier`, `CocApiStatus` | — (edge module, no domain deps) |
| **PlayerAccounts** | `coc_accounts`, `coc_account_claims`, `coc_account_snapshots`, `coc_account_disputes` | `AttachAccountService`, `VerifyOwnershipService`, `DisputeService`, `AccountSyncService`, `AccountReadModel` | CocIntegration, Users, Media, Notifications |
| **Clans** | `clans`, `clan_memberships`, `clan_snapshots` | `ClanSyncService`, `ClanReadModel` | CocIntegration |
| **Bases** | `base_layouts`, `base_tags`, `taggables`, `base_comments`, `base_likes`, `base_bookmarks`, `base_view_events`, `base_metrics` | `PublishBaseService`, `BaseInteractionService`, `TrendingService`, `BaseFeedQuery` | Users, PlayerAccounts (read), Media, Notifications, Moderation |
| **Recruitment** | `recruitment_posts`, `recruitment_applications`, `recruitment_interests` | `RecruitmentPostService`, `ApplicationService`, `RecruitmentSearchQuery` | PlayerAccounts, Clans, Notifications |
| **Marketplace** | `seller_profiles`, `marketplace_listings`, `marketplace_orders`, `marketplace_reviews`, `marketplace_disputes` | `ListingService`, `OrderService`, `ReviewService` | Users, Messaging, Media, Moderation |
| **Messaging** | `conversations`, `conversation_participants`, `messages` | `ConversationService`, `MessageService` | Users, Moderation |
| **Media** | `media`, `media_variants` | `UploadIntentService`, `MediaAttachmentService`, `MediaReadService` (status and variant URLs by media id), `MediaUrlResolver` | — (edge module) |
| **GameAssets** | — (config + manifest, no tables) | `GameAssetResolver` (unit / TH / clan badge / league emblem → URL + accessible name), `GameAssetPolicy` flag | — (edge module). The only place Supercell assets are referenced ([18 §2](18-design-system.md)) |
| **Notifications** | `notifications`, `notification_preferences` | `Notifier` (facade over channels), `NotificationReadModel` | Users |
| **Moderation** | `reports`, `report_cases`, `moderation_actions`, `user_sanctions` | `ReportService`, `CaseService`, `SanctionService`, `Moderatable` contract | Users, Notifications, Audit |
| **Audit** | `audit_logs` | `AuditLogger`, `AuditLogQuery` (admin viewer read model) | — |
| **Search** | (no tables; owns `search_documents` materialised view) | `SearchService` (interface), `IndexableContract` | reads other modules' read models |
| **Admin** | — | Admin controllers, Inertia pages and Gates only | all modules' public surfaces |

### Boundary enforcement

- A Pest architecture test (`tests/Architecture/ModuleBoundariesTest.php`, discovering modules
  automatically) fails the build when `App\Domain\X` references `App\Domain\Y\Models\*` or any other
  internal namespace of Y. Allowed targets are `App\Domain\Y\Contracts\*`, `App\Domain\Y\Services\*`,
  `App\Domain\Y\Data\*`, `App\Domain\Y\Events\*`, `App\Domain\Y\Enums\*`. Deptrac enforces the
  Http → Domain → Support layering.
- The "Depends on" column above documents intent; it is not enforced, because event listeners
  legitimately reference publishers outside it (e.g. Clans consuming `CocAccountVerified`).
- Cross-module **reads** that would be expensive through services (feed rendering) use explicit
  read-model query classes that join across tables and return DTOs. This is a deliberate,
  documented escape hatch — joins are fine, model coupling is not.
- Cross-module **writes** are always via service call or event listener.

### Events as the decoupling seam

Domain events published by modules and consumed elsewhere:

| Event | Publisher | Consumers |
|---|---|---|
| `UserRegistered` | Auth | Users (create profile), Notifications |
| `EmailVerified` | Auth | Notifications, Users (unlock writes) |
| `CocAccountAttached` | PlayerAccounts | Search (index), Audit |
| `CocAccountVerified` | PlayerAccounts | Users (badge, stats), Notifications, Clans (ensure clan), Audit |
| `CocAccountOwnershipTransferred` | PlayerAccounts | Notifications, Audit, Bases (re-attribute? no — bases stay with the publishing user) |
| `CocAccountSnapshotTaken` | PlayerAccounts | Users (refresh stats cache) |
| `BasePublished` | Bases | Search, Notifications (followers, P2), Users (stats) |
| `BaseInteracted` (like/copy/view) | Bases | Metrics aggregator, Notifications (throttled) |
| `CommentPosted` | Bases | Notifications, Moderation (auto-screen) |
| `MediaReady` / `MediaFailed` | Media | Bases, PlayerAccounts, Marketplace, Users (`MediaReady` on an avatar: forget the cached profile) |
| `MediaRetriesExhausted` | Media | Notifications (owner: re-upload needed) |
| `ReportFiled` | Moderation | Notifications (staff), Metrics |
| `SanctionApplied` | Moderation | Auth (status change), Notifications, Audit, Search (de-index) |
| `RecruitmentApplicationSubmitted` | Recruitment | Notifications |
| `OrderStatusChanged` | Marketplace | Notifications, Audit |

All listeners that do I/O are queued. The one exception is Auth's `RecordLogin`, which stamps
`last_login_at` and the hashed IP in the request because the IP is only known there. Otherwise,
synchronous listeners are limited to cache invalidation, including the single key lookup it needs
(Users' `ForgetProfileWhenAvatarReady` reads the username to name the key).

## 3. Layering inside a module

```
Domain/Bases/
├── Actions/          single-purpose write operations (PublishBase, ToggleLike)
├── Contracts/        interfaces exposed to other modules
├── Data/             DTOs (BaseCardData, PublishBaseData) — readonly classes
├── Events/
├── Exceptions/
├── Jobs/
├── Listeners/
├── Models/           Eloquent models — INTERNAL
├── Policies/
├── Queries/          read models / query objects returning DTOs or paginators
├── Services/         orchestration, transactions, event dispatch
└── Support/          value objects (BaseLink, LayoutHash), enums
```

**Rules:**
- Controllers contain no business logic: validate → call a service/action → redirect or
  `Inertia::render()` with DTO props. Vue components contain none either: they render props and
  submit intent.
- Services own transactions. An action never opens a second transaction.
- Models contain relationships, casts, scopes and accessors — no side effects, no dispatching.
- Value objects for anything with rules: `PlayerTag`, `BaseLink`, `LayoutHash`, `ThLevel`.
  Validation lives in the value object, so it cannot be bypassed by a second code path.
- Enums (PHP 8.1 backed enums) for every status column, with `label()` and `color()` helpers used
  by the UI.

## 4. Request lifecycle (representative: publishing a base)

1. The `Bases/Create` Vue page (`BaseComposer`, Inertia `useForm`) collects metadata; screenshots and
   video were uploaded earlier via presigned URLs and exist as `media` rows in `uploaded` state
   owned by the user.
2. Submit → `POST /bases` → `PublishBaseRequest` validation (errors return to `useForm` as shared
   `errors`) (metadata, media ownership, quotas, base-link format).
3. `BaseLayoutPolicy::create` → verified email, active status, verified CoC account, publish quota.
4. `PublishBaseService::handle(PublishBaseData)`:
   - opens a transaction;
   - parses `BaseLink` into a `LayoutHash`; checks the duplicate rules;
   - creates the `base_layouts` row in `processing`;
   - calls `MediaAttachmentService::attach()` to bind media to the base;
   - syncs tags; creates the `base_metrics` row;
   - commits; dispatches `BasePublished`.
5. Queued listeners: media processing finalisation check, search indexing, notification fan-out.
6. When the last media item emits `MediaReady`, the base flips `processing → published` and becomes
   visible.

## 5. Cross-cutting concerns

| Concern | Mechanism |
|---|---|
| Transactions | Service-level `DB::transaction`; events dispatched **after** commit (`DB::afterCommit` / queued listeners) |
| Idempotency | Every job carries a natural key and checks state before acting; `WithoutOverlapping` and `ShouldBeUnique` on syncs |
| Authorization | Policies + Gates only ([04](04-roles-and-permissions.md)) |
| Validation | Form Requests for shape (errors surface through Inertia's shared `errors` prop); value objects for domain invariants |
| Rate limiting | Named `RateLimiter` definitions, `Cache`-backed |
| Auditing | `AuditLogger` called explicitly in admin/moderation services (not a model observer — observers make the *why* invisible) |
| Feature flags | A `feature_flags` table + a `Feature` facade wrapper, so flags work in queue workers too |
| Time | `CarbonImmutable` everywhere; `Date::now()` so tests can freeze time |
| Money (P3) | Integer minor units + currency code; never floats |

## 6. Environments

| Environment | Purpose | Notes |
|---|---|---|
| Local | Docker compose (`app`, `web`, `node` → Vite dev server, `queue`, `scheduler`, `db`, `mailpit`; opt-in profiles: `ssr` → Inertia SSR renderer, `storage` → minio, `tools` → adminer) | No external accounts needed: MinIO stands in for R2, Mailpit for the mail provider, and the CoC client binds to a recorded-fixture fake by default |
| CI | GitHub Actions, matrix SQLite + Postgres, running `scripts/check.sh` (deferred at P0-01; added before staging) | All external edges faked; no network |
| Staging | Single small VPS, real R2 bucket (separate), real CoC API with a staging key | Seeded with synthetic data |
| Production | App VPS (PHP-FPM + `ssr` container) + managed Postgres + R2 + Cloudflare | Zero-downtime deploy, migrations gated |
