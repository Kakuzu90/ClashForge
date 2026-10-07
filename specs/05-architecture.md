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
| **Auth** | `users`, `sessions`, `password_reset_tokens`, `two_factor_*`, `username_history` | `RegistrationService` (with `RegistrationGuard` and `RegistrationLimit`), `EmailVerificationService`, `EmailChangeService` (with `EmailChangeLimit`), `UsernameChangeService`, `AccountDeletionService` (request, due anonymisation and policy-checked never-verified purge; cancellation is private to `AuthenticationService`), `UnverifiedAccountLifecycleService` (scheduled notices and dispatch/send receipts), `DisposableEmailDomains`, `TurnstileVerifier` contract, `DeletionHold` / `DeletionStep` contracts (another module's hold on and part of anonymisation, container-tagged, P2-24), `UsernameFieldRules`, `EmailFieldRules`, `SessionService`, `UserStatusService` (also `syncVerifiedAccounts` / `lockAccounts` for PlayerAccounts; `hiddenAuthorIds` for feeds, P3-03), `PasswordConfirmationService` (the current password typed inline on another module's sensitive form, P2-14), `RoleAssignmentService`, `UserLookupService` (username → listed account, and the account that changed away from a held name, for other modules), `AdminUserQuery` (admin user list and detail read model), `UserActivityReader` (last active: the later of the last sign-in and the newest session, for the sync tiers) | Users, Media, Notifications, Audit (deletion pipeline) |
| **Users** | `profiles`, `privacy_settings`, `user_stats`, `follows` | `ProfileService`, `ProfileAnonymisationService` (clears profile/privacy/stats inside Auth's deletion transaction), `PrivacySettingsService`, `PrivacyPolicyResolver`, `PublicProfileReadModel` (also the `/u/{old}` redirect target), `AvatarUrlService` (avatar URLs for many accounts, for other modules' lists), `AuthorDirectory` (authors of public content for a list: username always, display name and avatar for public profiles, whether CoC accounts are shown, P3-03), `CacheInvalidator` (profile and privacy keys) | Auth, Media |
| **CocIntegration** | `coc_api_requests` (log), `sync_states`, cache entries | `CocApiClient` (interface), `PlayerLookup`, `ClanLookup`, `TokenVerifier` (result DTOs, never throw), `CocKeyPool` (status), `CocHealthCheck`, `PlayerTag` / `ClanTag` + `CocTagFieldRules` (Data), `CocApiStatus` (breaker state, background budget), `CocApiHealthReport` (the dashboard's API panel: breaker, key counts, request-log summary; `keys()` lists each key by id for the System Health page; the account sync success rate), `SyncSchedule` (`sync_states` for every synced resource: due rows, tier intervals, backoff, frozen and stopped; the caller picks the tier, P2-09), `CocRequestLogRetention` | — (edge module, no domain deps) |
| **PlayerAccounts** | `coc_accounts`, `coc_account_claims`, `coc_account_snapshots`, `coc_account_disputes` | `AttachAccountService` (preview, attach; `block` says why a user may not attach, `holderUsername` re-checks the conflict card's name on each view), `VerifyOwnershipService` (verify own unverified or disputed row; `verifyTag` for a held tag; a token closes the tag's running disputes), `DisputeService` (open, respond, withdraw, release with the current password, decide, the hourly sweep, staff `releaseTag` of a suspended tag and `removeEvidence` of an identity-document image, P2-25, `eligibility` for the dispute form; refusals as `DisputeResultData`), `DisputePartyQuery` (a party's dispute page and the conflict card's link, P2-16), `DisputeAdminQuery` (the admin queue and the dashboard's pending-disputes counts) and `DisputeReviewService` (the admin review page; audits each evidence view), P2-17, `AccountOwnershipService` (detach with the password, `release()`, the featured switch, P2-14), `AccountImageService` (the owner's custom images, add / remove under the row lock; `AccountImages::releaseAll` when a row leaves its holder, P2-23), `AccountDeletionHooks` (Auth's `DeletionHold` + `DeletionStep`: a running dispute holds, the user's tags are released) and `BannedTagRelease` (`coc:release-banned-tags`), P2-24, `AccountReadModel` (owner-scoped `ownRow` for the verify pages; `detail` / `progression` for the account page `/accounts/{ulid}` behind `CocAccountPolicy::view`, P2-04; `forProfile` for the profile's PlayerCards, hero, badge and war stars under the same policy, P2-22), `AccountSyncService` (one account's background sync: stores fresh data, snapshot on a progression change, the 404 / failure rules) and `AccountSyncScheduler` (`coc:sync-accounts`), P2-09, `AccountRefreshService` (the owner's manual refresh, `apply()` with `source: manual`, job fallback) and `AccountViewRecorder` (signed-in views for the hot tier), P2-20, `AccountCredits` (the account a base credits, for Bases, P3-01; `creditsFor` a feed page and `featuredThLevel` for the feed's default, P3-03), `SnapshotCompaction` (`coc:compact-snapshots`), P2-21 | CocIntegration, Auth (`UserStatusService` count + locks, `UserActivityReader` for sync tiers, `PasswordConfirmationService` for detach and a dispute release), Audit, Users, Media, Notifications, Moderation (`ModerationActionLog` for dispute decisions, `BanLookup` for the ban release), Clans (`ClanDirectory` sets `clan_id`; `ClanReadModel` for the ClanChip), GameAssets (`GameAssetResolver`, `GameAssetCatalogue` for the progression grids) |
| **Clans** | `clans`, `clan_memberships`, `clan_snapshots` | `ClanDirectory` (`ensure`: the clan row behind a player's clan block, a stub until the clan sync; PlayerAccounts calls it where it stores `clan_tag`, P2-13), `ClanReadModel` (`summaries` by id for the ClanChip), `ClanSyncService` (P4-01), `ClanRole` (the API's role names → labels; `admin` is Elder) | CocIntegration |
| **Bases** | `base_layouts`, `base_tags`, `base_layout_tag`, `base_comments`, `base_likes`, `base_bookmarks`, `base_view_events`, `base_metrics` | `PublishBaseService` (publish, `publishIfReady` for the `PublishWhenMediaReady` listener; `DropLostCredits` clears a credit its author lost, P3-01), `BaseInteractionService`, `TrendingService` (`bases:recompute-trending`), `BaseFeedQuery` (the feed, keyset-paged and cached), `FeedDefaults` (the signed-in Town Hall default), `CacheInvalidator::baseFeeds()` (P3-03) | Users (`AuthorDirectory`; `PrivacySettingsChanged`), PlayerAccounts (`AccountCredits`; its `CocAccountReleased` / `CocAccountOwnershipTransferred` events), Media, Notifications, Moderation (`SanctionApplied` / `SanctionLifted`), Auth (`UserStatusService::hiddenAuthorIds`; `AccountDeletionRequested`) |
| **Recruitment** | `recruitment_posts`, `recruitment_applications`, `recruitment_interests` | `RecruitmentPostService`, `ApplicationService`, `RecruitmentSearchQuery` | PlayerAccounts, Clans, Notifications |
| **Marketplace** | `seller_profiles`, `marketplace_listings`, `marketplace_orders`, `marketplace_reviews`, `marketplace_disputes` | `ListingService`, `OrderService`, `ReviewService` | Users, Messaging, Media, Moderation |
| **Messaging** | `conversations`, `conversation_participants`, `messages` | `ConversationService`, `MessageService` | Users, Moderation |
| **Media** | `media`, `media_variants` | `UploadIntentService`, `MediaAttachmentService` (`attach`, `release`, `detachFrom` a parent, P2-23), `MediaLifecycleService` (including owner-media purge for anonymisation), `MediaReadService` (status and variant URLs by media id, or by ULID for media attached to a given parent and collection, P2-17; `attachedTo` lists a parent's media with renditions, P2-23; `firstReadyVariants` picks one cover per parent for a list, P3-03), `MediaUrlResolver`, `MediaProcessingStats` (the processing p95 on System Health, P3-02) | — (edge module) |
| **GameAssets** | — (config + manifest, no tables) | `GameAssetResolver` (unit / TH / clan badge / league emblem → URL + accessible name), `GameAssetCatalogue` (catalogue order of `config/assets.php` and API name → slug, with `assets.aliases`, P2-04), `GameAssetPolicy` flag | — (edge module). The only place Supercell assets are referenced ([18 §2](18-design-system.md)) |
| **Notifications** | `notifications`, `notification_preferences`, `notification_email_deliveries` | `Notifier` (writes in-app rows; `sendOnce` with an event key for a notice that may be delivered twice, P2-18), `NotificationCleanupService` (deletes an anonymised account's notifications, preferences and receipts), `InAppChannel` + `InAppNotification` contract (a module's Laravel notification lists the channel beside `mail`), `NotificationReadModel` (centre and unread count), `NotificationService` (mark read), `EmailPreferenceService`, `UnsubscribeService` (signed opt-out), `EmailDeliveryService` (non-security mail, cap and receipts; `queue()` is how another module sends one), `NotificationType` / `NotificationCategory` | Users; listens to Auth and Media events (edge modules cannot call it) |
| **Moderation** | `reports`, `report_cases`, `moderation_actions`, `user_sanctions` | `ReportService`, `CaseService`, `SanctionService`, `SanctionHistoryQuery`, `ModerationActionLog` (another module's staff decision, e.g. an ownership dispute), `BanLookup` (users whose active ban started before a cutoff, for the tag release, P2-24), `Moderatable` contract | Auth (`UserStatusService` sets the status), Users, Notifications, Audit |
| **Audit** | `audit_logs` | `AuditLogger`, `AuditLogQuery` (admin viewer read model) | — |
| **Operations** | — (reads Laravel's `jobs` and `failed_jobs`, which no other module touches) | `FailedJobsQuery` (the dashboard's summary and the System Health page's failures by class), `QueueStatsQuery` (depth and wait per queue), `SchedulerStatusQuery` (heartbeat age, from `App\Support\Health`), `FailedJobService` (retry through `queue:retry` or delete, one job or a class, each job in its own locked transaction with its audit entry, P2-19; `FailedJobsQuery::jobs` lists a class on demand) | Audit (`AuditLogger`), Auth (`StaffAbility` for the `manage-failed-jobs` Gate) |
| **Search** | (no tables; owns `search_documents` materialised view) | `SearchService` (interface), `IndexableContract` | reads other modules' read models |
| **Admin** | — | Admin controllers, Inertia pages and Gates only | all modules' public surfaces |

### Boundary enforcement

- A Pest architecture test (`tests/Architecture/ModuleBoundariesTest.php`, discovering modules
  automatically) fails the build when `App\Domain\X` references `App\Domain\Y\Models\*` or any other
  internal namespace of Y. Allowed targets are `App\Domain\Y\Contracts\*`, `App\Domain\Y\Services\*`,
  `App\Domain\Y\Data\*`, `App\Domain\Y\Events\*`, `App\Domain\Y\Enums\*`. Deptrac enforces the
  Http → Domain → Support layering.
- The "Depends on" column above documents intent; it is not enforced, because event listeners
  legitimately reference publishers outside it (e.g. Clans consuming `CocAccountVerified` from P4-01).
- Cross-module **reads** that would be expensive through services (feed rendering) use explicit
  read-model query classes that join across tables and return DTOs. This is a deliberate,
  documented escape hatch — joins are fine, model coupling is not.
- Cross-module **writes** are always via service call or event listener.

### Events as the decoupling seam

Domain events published by modules and consumed elsewhere:

| Event | Publisher | Consumers |
|---|---|---|
| `UserRegistered` | Auth | Users (create profile, privacy and stats rows). After commit |
| `PasswordChanged` / `UnrecognisedDeviceSignedIn` | Auth | Notifications (in-app copy; Auth sends the security email itself). After commit |
| `EmailVerified` | Auth | Notifications (in-app "email confirmed"). After commit. Gates read `email_verified_at`, so Users has nothing to unlock |
| `CocAccountAttached` | PlayerAccounts | Search (index), Audit |
| `CocAccountVerified` | PlayerAccounts | PlayerAccounts' queued `StartAccountSync` (first snapshot, `source = verification`, and the sync schedule, P2-09; an admin dispute transfer dispatches this event too, so it starts there as well), Users (badge, stats), Notifications (PlayerAccounts' queued `SendOwnershipNotice` writes the verifier's in-app notice and queues the preference-checked email through `EmailDeliveryService::queue()`, keyed by the event's `claimId`), Clans (P4-01: `SyncClanJob` for a clan not yet synced; the stub row and `clan_id` are already written at attach and sync, P2-13), Audit |
| `CocAccountOwnershipTransferred` | PlayerAccounts | Notifications (the same listener sends the previous holder `CocAccountTakenOverNotification`, `mail` beside `InAppChannel`, always sent), Audit, Bases (re-attribute? no — bases stay with the publishing user; the credit is nulled, P3-01, and cached feeds dropped, P3-03) |
| `CocAccountDisputeOpened` / `CocAccountDisputeInfoRequested` / `CocAccountDisputeReminderDue` / `CocAccountDisputeClosed` | PlayerAccounts | Notifications: PlayerAccounts' queued `SendDisputeNotice` (`high`) writes the in-app rows and queues the preference-checked email, keyed per dispute and notice (P2-18: opened to the holder, reminders to the holder, the request to the asked party, the outcome per [13 §8](13-claiming-workflow.md)). After commit. `ReminderDue` comes from the hourly sweep. `Closed` covers every end: decision, release, token, withdrawal, and carries `closedBy` and `by` so each ending is told apart. An admin transfer also dispatches `CocAccountVerified` and `CocAccountOwnershipTransferred` (method `admin`), whose token-takeover notice is skipped in favour of the decision notice; a holder's release dispatches only `CocAccountVerified` |
| `CocAccountReleased` | PlayerAccounts | Notifications (the same `SendOwnershipNotice` writes the in-app "Tag released" notice to the user who held it, no link), Bases (P3-01: null the credit; P3-03: drop cached feeds). After commit; carries the account id, the former owner's id and a `ReleaseReason` (`detach`, P2-14; `deletion`, `ban`, P2-24) |
| `CocAccountSnapshotTaken` | PlayerAccounts | Users (refresh stats cache) |
| `BasePublished` | Bases | Search, Notifications (followers, P2), Users (stats), Bases (drop cached feeds, P3-03) |
| `BaseInteracted` (like/copy/view) | Bases | Metrics aggregator, Notifications (throttled) |
| `CommentPosted` | Bases | Notifications, Moderation (auto-screen) |
| `MediaReady` / `MediaFailed` | Media | Bases, PlayerAccounts, Marketplace, Users (`MediaReady` on an avatar: forget the cached profile) |
| `MediaRetriesExhausted` | Media | Notifications (owner: re-upload needed; in-app, the email joins with P1-15) |
| `ReportFiled` | Moderation | Notifications (staff), Metrics |
| `SanctionApplied` / `SanctionLifted` | Moderation | Notifications (Moderation's listener sends the notices; each lists `InAppChannel` beside `mail`, so the in-app copy is Notifications'), Search (de-index), Bases (drop cached feeds, P3-03). Held until commit. The status change (Auth) and the audit entry are written inside `SanctionService`'s transaction, not by listeners |
| `PrivacySettingsChanged` | Users | Bases (drop cached feeds: cards show the author by these settings, P3-03). After commit |
| `AccountDeletionRequested` | Auth | Bases (drop cached feeds: the author's bases are hidden from now on, P3-03). After commit |
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
3. `BaseLayoutPolicy::create` → verified email, content writes allowed, a held CoC account
   (`users.verified_accounts_count`); the service runs it again under the author's row lock, then
   the publish limit (P3-01).
4. `PublishBaseService::handle(PublishBaseData)`:
   - opens a transaction;
   - parses `BaseLink` into a `LayoutHash`; checks the duplicate rules;
   - picks the credited account (PlayerAccounts' `AccountCredits`: one of the author's held
     accounts, the featured one by default);
   - creates the `base_layouts` row in `processing`;
   - calls `MediaAttachmentService::attach()` to bind media to the base;
   - syncs tags; creates the `base_metrics` row;
   - publishes at once when every item is already `ready`, and dispatches `BasePublished` after
     commit. `BasePublished` means visible: a base waiting on media dispatches it when it flips.
5. Queued listeners: media processing finalisation check, search indexing, notification fan-out.
6. When the last media item emits `MediaReady`, `PublishWhenMediaReady` checks the uploader's
   `processing` bases (the event names the uploader, not the parent). Each whose items are all
   ready, and whose author may still publish, flips `processing → published` and becomes visible;
   an author sanctioned meanwhile keeps it waiting (specs/12, P3-01).

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
| Local | Docker compose (`app`, `web`, `node` → Vite dev server, `queue`, `scheduler`, `db`, `mailpit`; opt-in profiles: `ssr` → Inertia SSR renderer, `storage` → minio, `tools` → adminer) | No external accounts needed: MinIO stands in for R2, Mailpit for the mail provider, and the CoC client binds to a recorded-fixture fake by default (`COC_API_DRIVER=fake`; refused in production) |
| CI | GitHub Actions, matrix SQLite + Postgres, running `scripts/check.sh` (deferred at P0-01; added before staging) | All external edges faked; no network |
| Staging | Single small VPS, real R2 bucket (separate), real CoC API with a staging key | Seeded with synthetic data |
| Production | App VPS (PHP-FPM + `ssr` container) + managed Postgres + R2 + Cloudflare | Zero-downtime deploy, migrations gated |
