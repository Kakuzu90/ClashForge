# 25 — Development Phases

## 1. Changes to the proposed roadmap

The brief's seven phases are sound. Four adjustments:

1. **Phase 0 added.** Foundation work (docker, CI, design tokens, base layout, error tracking,
   media pipeline skeleton) is a phase, not a footnote. Building it inside Phase 1 makes Phase 1
   appear to slip.
2. **Media moves earlier.** The brief places images and video in Phase 3. The upload pipeline is
   needed for avatars in Phase 1 and account images in Phase 2, and it is the most
   failure-prone subsystem in the project. Build it in Phase 0, use it from Phase 1.
3. **Moderation moves much earlier.** The brief places reports in Phase 5. The moment users can
   publish anything (Phase 3), a report path and a moderator action must exist — otherwise the
   first abusive base has no answer. Basic reporting ships **with** bases.
4. **Marketplace stays last and is explicitly conditional.** It does not ship unless Phases 3–5
   produced a real community and the legal preconditions in [15](15-marketplace-workflow.md) are met.

Estimates assume **one experienced full-stack developer working with an agentic coding workflow**.
Double them for a part-time effort.

---

## Phase 0 — Foundation (2–3 weeks)

**Goal:** a deployable, tested, styled skeleton with nothing business-specific in it.

| Workstream | Deliverables |
|---|---|
| Project setup | Laravel 12 in `src/`, PHP 8.3, docker compose running, Pint, PHPStan L6, Pest, Deptrac, CI green on SQLite + Postgres |
| Domain skeleton | `app/Domain/*` module folders, service provider wiring, `Support` primitives (enums, value objects, casts) |
| Design system | Tokens in Tailwind `@theme`, fonts self-hosted, `x-ui.*` primitives (button, input, card, pill, badge, avatar, modal, toast, skeleton, empty-state), component gallery at `/dev/components` |
| Asset policy | `GameAssets` module: `GameAssetResolver` + `<x-game.asset>` (fallback, accessible name, kill switch), `assets:publish-pack` / `assets:verify-pack`, `game/` CDN binding with resizing disabled, reconcile-job prefix allowlist + its test, fan-content disclaimer in the global footer, lint rule banning game-asset paths in templates ([18 §2](18-design-system.md), [10 §11](10-media-storage.md)) |
| App shell | Layouts (public, app, admin), mobile bottom nav, desktop sidebar, global search stub, dark theme |
| Media pipeline | `media` + `media_variants` tables, presigned intent/complete endpoints, `ProcessMediaJob` with image validation + variants, orphan sweeper, storage wiring against **MinIO locally** ([10 §2.1](10-media-storage.md)); R2 + CDN domain swapped in by env when the account exists |
| Ops | Health endpoint, Sentry, structured logging, queue workers + scheduler running, backup verified, staging deployed |

**Exit:** a styled page that uploads an image to object storage, processes it, and renders the
variants — end to end against MinIO locally, with tests. Re-verify the same flow against R2 + CDN
before Phase 3 ships publicly; the only difference must be `.env`.

---

## Phase 1 — Identity (3–4 weeks)

**Goal:** people can have accounts and profiles.

| Workstream | Deliverables |
|---|---|
| Auth | Fortify backend + our views: register, login, logout, email verification, password reset, remember me, rate limiters, Turnstile, session management UI |
| Roles | `role` + `status` columns, policies/gates scaffold, admin middleware, permission matrix test |
| Profiles | `profiles`, `privacy_settings`, `user_stats`; edit profile, avatar upload (uses Phase 0 media), privacy settings |
| Public profile | `/u/{username}` with visibility rules (mostly empty at this stage — that is fine) |
| Settings | Profile, privacy, security, sessions, danger zone (deletion request) |
| Admin v1 | User list, user detail, suspend/ban, audit log viewer |
| Notifications v1 | `notifications` table, bell, list page, mark-read; security emails |

**Exit:** register → verify → complete profile → upload avatar → view public profile → an admin can
suspend the user and the suspension takes effect. Security test suite (enumeration, mass assignment,
IDOR) green.

---

## Phase 2 — Verified CoC accounts (4–5 weeks) ← *the differentiator*

**Goal:** verified ownership works, and profiles become worth visiting.

| Workstream | Deliverables |
|---|---|
| API layer | `CocApiClient` + decorators (cache, throttle, circuit breaker), DTO mappers, key pool, `coc_api_requests`, health reporting, fake client + fixtures |
| Attach & verify | Tag normalisation value object, attach flow UI, `verifytoken` verification, claims log, state machine, featured account |
| Conflicts & disputes | Conflict detection, dispute creation with evidence, holder response flow, admin resolution, ownership transfer with audit |
| Sync | `sync_states`, tiered scheduler, `SyncCocAccountJob`, snapshot-on-change, manual refresh, stale-data UI |
| Account UI | PlayerCard (all variants), account detail page, progression grids wired to `GameAssetResolver` (unit, TH, clan badge, league emblem — unmodified, with fallbacks), account images (≤5), TH badge ramp |
| Asset pack v1 | Curated unit / Town Hall / league asset pack assembled and published to `game/1/` with its manifest; unknown-unit placeholder verified against a game update |
| Profile v2 | Connected accounts, verified badge, featured card, stat blocks |
| Admin | CoC account management, claims, disputes queue, forced transfer |

**Exit:** a user verifies a real account with a real in-game token; a second user is blocked and
opens a dispute; an admin resolves it and both parties are notified; the API is taken down in
staging and every page still renders from snapshots.

---

## Phase 3 — Bases + basic moderation (4–5 weeks) — **MVP completes here**

**Goal:** the content loop works, and abuse has an answer.

| Workstream | Deliverables |
|---|---|
| Publishing | `base_layouts`, `base_metrics`, tags, `BaseLink`/`LayoutHash` value objects, composer UI, screenshots + video via the media pipeline, publish-on-media-ready |
| Video | ffmpeg transcode, poster frames, duration/size enforcement, dedicated media worker |
| Discovery | Feed with filters and sorts, trending score job, TH/category/tag landing pages, base detail page, copy-link redirect + counter, view dedupe + aggregation |
| Interactions | Likes, bookmarks, comments with one reply level, counters, notifications |
| Search v1 | Postgres FTS across players, accounts, bases; `SearchService` interface; tag short-circuit; query parser |
| Moderation v1 | Reports on bases/comments/profiles, report cases with grouping, moderator queue, hide/remove/warn/restrict, moderation log, auto-hide rules, duplicate flagging |
| SEO | Server-rendered metadata, JSON-LD, OG images, sitemap, robots |

**Exit:** every MVP exit criterion in [01 §2](01-product-overview.md). Ship publicly.

---

## Phase 4 — Recruitment (3–4 weeks)

| Workstream | Deliverables |
|---|---|
| Clans | `clans`, `clan_memberships`, clan sync job, clan lookup, clan chip/card |
| LFC posts | Player posts with auto-filled verified stats, expiry, bump, auto-close on join |
| Clan posts | Role-verified creation, auto-filled clan data, auto-pause when full, nightly role re-check |
| Applications | Apply/interest flows, states, limits, notifications, responsiveness metric |
| Discovery | Recruitment browse with structured filters, ranking, the parsed queries from [17](17-search-and-discovery.md) |
| Moderation | Recruitment posts reportable; contact-pattern screening |

**Exit:** a clan leader posts, a player applies with a verified account, the recruiter accepts, and
both sides are notified. A non-leader cannot post for a clan.

---

## Phase 5 — Community (3–4 weeks)

| Workstream | Deliverables |
|---|---|
| Social | Follows, follower counts, followed-authors feed section, pull-based fan-out above 1000 followers |
| Activity | Profile activity timeline |
| Notifications v2 | Preferences, grouping/aggregation, digests, bounce handling |
| Moderation v2 | Appeals queue with reviewer separation, anomaly detection job, transparency page, reporter trust scoring |
| Achievements | Badge framework + a first set (verified, first base, milestones), reward toasts |
| Polish | Empty/loading/error states audited across every page, accessibility pass with assistive tech, performance budgets enforced |

**Exit:** a user follows a creator, receives an aggregated notification, adjusts preferences, and a
sanctioned user successfully appeals to a different reviewer.

---

## Phase 6 — Marketplace, Stage 1 only (4–5 weeks) — **conditional**

**Preconditions (all):** Phases 3–5 shipped; an active community with sustained content; moderation
capacity demonstrably keeping SLA; the legal review in [15 §4](15-marketplace-workflow.md) done;
a decision that discovery-without-payments is worth building.

| Workstream | Deliverables |
|---|---|
| Sellers | Application, manual approval, seller profiles, portfolio |
| Listings | Categories, prohibited-term screening, listing lifecycle, review queue |
| Orders | Full state machine, order events timeline, deliverable upload, auto-complete |
| Messaging | Order-scoped conversations, moderation hooks, reporting |
| Reviews | Completed-order gating, replies, ring detection |
| Disputes | Reputational dispute workflow, admin decisions |
| Safety | No-payment-protection interstitial and banners, scam guidance, Critical report path |

**Exit:** a full order completes with a delivered file and a review, and a prohibited listing is
auto-rejected.

---

## Phase 7 — Advanced (ongoing)

Pulled in by evidence, not by plan. Candidates, roughly in expected order of value:

| Candidate | Trigger |
|---|---|
| Meilisearch migration | The thresholds in [17 §7](17-search-and-discovery.md) |
| Redis + Horizon | The thresholds in [21 §7](21-caching-strategy.md) |
| Saved searches + alerts | Recruitment usage proves demand |
| Recommendations ("similar bases", "clans for you") | Enough interaction data to be non-embarrassing |
| Creator analytics dashboard | Creators ask for it |
| Light theme | User demand; tokens already support it |
| i18n (PH/ID/BR first) | Regional traffic share |
| Public read API | Third-party interest |
| Base collections, tournaments, clan pages, CWL tools | Community demand |
| Payments (Marketplace Stage 2) | Every precondition in [15 §4](15-marketplace-workflow.md) |

---

## 2. Cross-phase practices

Applied in every phase, not scheduled as separate work:

- Feature tests for the happy path, authorization and validation on every new surface.
- A security test whenever a surface accepts user input, files, or crosses a trust boundary.
- New UI variants added to `/dev/components` in the same change.
- Specs in `specs/` updated when the implementation diverges from the plan.
- Performance budgets and query-count assertions enforced by CI.
- Each phase ends with: an accessibility pass on its main flows, a dependency audit, and a
  staging soak with seeded data.

## 3. Converting a phase into tasks

Each phase becomes a task set following this template. A task is *ready* when all five sections
are answerable from the specs.

```
Task: <imperative, one deliverable>
Spec refs: <spec file(s) + section>
Scope:
  - Migrations / models / factories
  - Domain: enums, value objects, services/actions, events
  - Policy + form request / Livewire validation
  - UI: Livewire page or component + design-system components used
  - Jobs / listeners / schedule entries
  - Config keys added
Out of scope: <explicit, to stop drift>
Acceptance criteria:
  - Functional: <FR ids from 02>
  - Authorization: <who can and cannot, per 04>
  - Edge cases: <from 23>
  - States: empty / loading / error designed and implemented
Tests:
  - Feature: happy path, authorization, validation
  - Security: <if the surface takes input, files, or crosses a trust boundary>
  - Unit: <value objects, scoring, parsers>
```

**Ordering rules within a phase:**
1. Schema and domain primitives before anything that uses them.
2. Services and policies before UI.
3. The happy path before every edge case; edge cases before polish.
4. Anything another task blocks goes first, even if it is less interesting.
5. Nothing merges without its tests.

**Sizing:** if a task cannot be described in the template above in under a page, it is two tasks.

## 4. Spec bundle per task

Which specs to put in context for each phase task. **Core** is required; **Plus** is the supporting
detail. The four always-in-context files ([README](README.md), [05](05-architecture.md),
[19](19-module-structure.md), [04](04-roles-and-permissions.md)) are assumed and not repeated.

Every task additionally pulls: the `FR-*` rows it satisfies from [02](02-functional-requirements.md),
its domain's rows from [23](23-edge-cases.md), and [11](11-security.md) whenever the surface takes
user input, files, or crosses a trust boundary.

### Phase 0 — Foundation

| Task | Core | Plus |
|---|---|---|
| Project setup, CI, static analysis | [19](19-module-structure.md) | [06](06-tech-stack.md) |
| Domain skeleton + `Support` primitives | [05](05-architecture.md), [19](19-module-structure.md) | — |
| Design tokens + `x-ui.*` primitives | [18 §3–4](18-design-system.md) | [03 §8](03-non-functional-requirements.md) |
| App shell, layouts, navigation | [18 §5](18-design-system.md) | [04](04-roles-and-permissions.md) |
| Media pipeline (tables, intent/complete, processing, sweeper) | [10](10-media-storage.md), [07](07-database-schema.md) (media) | [20](20-jobs-and-scheduling.md), [11](11-security.md) |
| GameAssets module + asset policy plumbing | [18 §2](18-design-system.md), [10 §11](10-media-storage.md) | [19](19-module-structure.md) |
| Ops: health, logging, error tracking, workers | [20](20-jobs-and-scheduling.md) | [03 §7](03-non-functional-requirements.md) |

### Phase 1 — Identity

| Task | Core | Plus |
|---|---|---|
| Registration, login, verification, reset | [04 §4](04-roles-and-permissions.md), [11](11-security.md), [07](07-database-schema.md) (auth) | [16](16-notifications.md), [18](18-design-system.md) |
| Roles, status, policy scaffold | [04](04-roles-and-permissions.md), [11](11-security.md) | [19](19-module-structure.md) |
| Profiles + avatar upload | [07](07-database-schema.md) (users), [10](10-media-storage.md), [18](18-design-system.md) | [04](04-roles-and-permissions.md) |
| Privacy settings + public profile | [04](04-roles-and-permissions.md), [18 §6](18-design-system.md) | [17 §6](17-search-and-discovery.md) (SEO) |
| Settings area incl. sessions, deletion | [04](04-roles-and-permissions.md), [11](11-security.md) | [08 §6](08-entity-relationships.md) (deletion semantics) |
| Admin v1 + audit log | [12](12-moderation-system.md), [07](07-database-schema.md) (audit) | [18 §4](18-design-system.md) (admin components) |
| Notifications v1 | [16](16-notifications.md), [07](07-database-schema.md) | [20](20-jobs-and-scheduling.md) |

### Phase 2 — Verified CoC accounts

| Task | Core | Plus |
|---|---|---|
| API client, decorators, key pool | [09](09-coc-api-integration.md) | [21](21-caching-strategy.md), [20](20-jobs-and-scheduling.md) |
| Attach + token verification flow | [13](13-claiming-workflow.md), [09](09-coc-api-integration.md), [07](07-database-schema.md) (coc) | [18 §6](18-design-system.md), [23 §2](23-edge-cases.md) |
| Conflicts, disputes, ownership transfer | [13](13-claiming-workflow.md), [12](12-moderation-system.md) | [16](16-notifications.md), [08](08-entity-relationships.md) |
| Tiered sync + snapshots | [09 §6](09-coc-api-integration.md), [20](20-jobs-and-scheduling.md) | [07](07-database-schema.md) (snapshots, sync_states) |
| PlayerCard, account detail, progression | [18 §4](18-design-system.md) | [08](08-entity-relationships.md) |
| Asset pack v1 | [10 §11](10-media-storage.md), [18 §2](18-design-system.md) | — |

### Phase 3 — Bases + moderation

| Task | Core | Plus |
|---|---|---|
| Publishing + composer | [07](07-database-schema.md) (bases), [10](10-media-storage.md), [18 §6](18-design-system.md) | [23 §3](23-edge-cases.md) |
| Video processing | [10 §6, §11](10-media-storage.md), [20](20-jobs-and-scheduling.md) | [03 §1](03-non-functional-requirements.md) |
| Feed, trending, landing pages | [17](17-search-and-discovery.md), [21](21-caching-strategy.md) | [20](20-jobs-and-scheduling.md), [22 §6](22-scaling.md) |
| Likes, bookmarks, comments, counters | [07](07-database-schema.md), [08 §5](08-entity-relationships.md) | [16](16-notifications.md) |
| Search v1 | [17](17-search-and-discovery.md), [07](07-database-schema.md) | [21](21-caching-strategy.md) |
| Moderation v1 | [12](12-moderation-system.md), [07](07-database-schema.md) (moderation) | [16](16-notifications.md), [04](04-roles-and-permissions.md) |
| SEO surfaces | [17 §6](17-search-and-discovery.md), [18](18-design-system.md) | — |

### Phases 4–6

| Task | Core | Plus |
|---|---|---|
| Clans + clan sync | [09](09-coc-api-integration.md), [07](07-database-schema.md) (clans), [20](20-jobs-and-scheduling.md) | [14](14-recruitment-workflow.md) |
| Recruitment posts + applications | [14](14-recruitment-workflow.md), [07](07-database-schema.md) | [16](16-notifications.md), [12](12-moderation-system.md) |
| Follows, activity, fan-out | [16 §7](16-notifications.md), [07](07-database-schema.md) | [22 §4](22-scaling.md) |
| Notifications v2, appeals, anomaly detection | [16](16-notifications.md), [12](12-moderation-system.md) | [20](20-jobs-and-scheduling.md) |
| Marketplace | [15](15-marketplace-workflow.md), [07](07-database-schema.md) | [12](12-moderation-system.md), [10](10-media-storage.md) |
