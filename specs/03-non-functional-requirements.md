# 03 — Non-Functional Requirements

Each NFR has a target and a way to verify it. Anything unverifiable is a wish, not a requirement.

## 1. Performance

| ID | Requirement | Target | Verification |
|---|---|---|---|
| NFR-PERF-1 | Server render time, p95, cached-out pages | < 400 ms | Load test with 10k bases / 5k users seeded |
| NFR-PERF-2 | Server render time, p95, base feed | < 300 ms | Same, feed endpoint specifically |
| NFR-PERF-3 | Time to first byte from CDN edge for static assets | < 100 ms | Synthetic checks from SEA/EU/NA |
| NFR-PERF-4 | Largest Contentful Paint on mobile 4G, base detail | < 2.5 s | Lighthouse CI budget in the pipeline |
| NFR-PERF-5 | Cumulative Layout Shift | < 0.1 | Lighthouse CI; all media has intrinsic dimensions |
| NFR-PERF-6 | JS shipped on a public page | < 120 KB gzipped | Bundle-size budget failing the build |
| NFR-PERF-7 | No page issues more than 25 SQL queries | hard cap | `preventLazyLoading` in non-production + a query-count assertion in feature tests |
| NFR-PERF-8 | Livewire component update round-trip, p95 | < 250 ms | Instrumented in application metrics |
| NFR-PERF-9 | Background job p95 wait time in queue | < 30 s for `default`, < 5 min for `media` | Queue depth metric + job timing |

**Non-negotiable query rules:** no N+1 in any list view; every feed and profile query is covered by
an index verified with `EXPLAIN`; counters are denormalised columns, never `COUNT(*)` in a loop.

## 2. Scalability

| ID | Requirement |
|---|---|
| NFR-SCALE-1 | The MVP must run on a single 4 vCPU / 8 GB VPS plus managed Postgres and serve 50k monthly actives. |
| NFR-SCALE-2 | The web tier must be stateless: sessions in the database, no local file writes except a wiped cache directory, no in-memory locks. |
| NFR-SCALE-3 | Horizontal scaling must be a config change (add app containers behind the load balancer), not a code change. |
| NFR-SCALE-4 | Every write path that fans out (notifications, counters, sync) must be queued, not inline. |
| NFR-SCALE-5 | Schema must tolerate 10M `base_views`, 1M `coc_account_snapshots` rows without table scans; partitioning strategy documented before those thresholds ([22](22-scaling.md)). |

## 3. Availability & resilience

| ID | Requirement | Target |
|---|---|---|
| NFR-AVAIL-1 | Application uptime | 99.5% monthly (≈3.6 h/month budget) |
| NFR-AVAIL-2 | CoC API unavailability must not cause 5xx anywhere | 100% of pages degrade to snapshots |
| NFR-AVAIL-3 | Object storage unavailability degrades to placeholder media; publishing is disabled with a clear message | no 5xx |
| NFR-AVAIL-4 | Failed jobs retry with exponential backoff and land in `failed_jobs` with an alert | 3 attempts |
| NFR-AVAIL-5 | Database backups | Nightly full + PITR, 30-day retention, restore rehearsed quarterly |
| NFR-AVAIL-6 | Deploys are zero-downtime and migrations are backward-compatible for one release | verified in staging |
| NFR-AVAIL-7 | Documented RPO ≤ 15 min, RTO ≤ 2 h | restore drill |

## 4. Security

Full controls in [11-security.md](11-security.md). NFR-level commitments:

| ID | Requirement |
|---|---|
| NFR-SEC-1 | HTTPS only, HSTS with a 1-year max-age, secure + httpOnly + SameSite=Lax session cookies. |
| NFR-SEC-2 | A Content-Security-Policy with no `unsafe-inline` for scripts; nonce-based where inline is unavoidable. |
| NFR-SEC-3 | All authorization goes through Policies/Gates. No inline role checks in controllers or Blade beyond `@can`. |
| NFR-SEC-4 | No secrets in the repository; all credentials via environment. |
| NFR-SEC-5 | Dependency scanning on every PR; critical CVEs block merge. |
| NFR-SEC-6 | Every destructive or cross-user admin action is audit-logged with actor, target, before/after and IP. |
| NFR-SEC-7 | PII at rest: emails and IP addresses are the only sensitive fields; IPs are stored hashed with a rotating salt after 30 days. |

## 5. Privacy & compliance

| ID | Requirement |
|---|---|
| NFR-PRIV-1 | Data export: a user can request a machine-readable export of their data, delivered within 30 days (queued, emailed as a signed link). |
| NFR-PRIV-2 | Data deletion honours FR-AUTH-9; moderation records are retained under legitimate interest with the user pseudonymised. |
| NFR-PRIV-3 | Cookie use is limited to strictly-necessary session and CSRF cookies at launch; no third-party analytics that requires consent (use a self-hosted or cookieless analytics tool). |
| NFR-PRIV-4 | Minors: the platform states a 13+ (16+ in the EEA where required) minimum age at registration; underage reports are actioned by deletion. |
| NFR-PRIV-5 | Supercell Fan Content Policy compliance: the required disclaimer visible in the global footer on every page, no Supercell trademarks in the logo, domain or platform branding, and game assets used only to identify game content, unmodified ([18 §2](18-design-system.md)). |
| NFR-PRIV-6 | Terms of Service and Privacy Policy exist before launch and are versioned; material changes require re-acceptance. |
| NFR-PRIV-7 | Every game asset is rendered through a single resolver, so the entire category can be withdrawn, replaced or re-pointed in one change if fan-content permission changes. |

## 6. Maintainability

| ID | Requirement |
|---|---|
| NFR-MAINT-1 | Domain code lives in modules with explicit public surfaces ([19](19-module-structure.md)); cross-module access goes through services or events, never another module's Eloquent model. |
| NFR-MAINT-2 | Static analysis at PHPStan level 6 minimum (level 8 on `app/Domain`), enforced in CI. |
| NFR-MAINT-3 | Code style enforced by Pint with the Laravel preset; CI fails on drift. |
| NFR-MAINT-4 | Test coverage: 100% of domain services and policies have feature or unit tests; overall line coverage ≥70%. |
| NFR-MAINT-5 | Every external dependency (CoC API, storage, mail) sits behind an interface with a fake implementation used in tests. |
| NFR-MAINT-6 | Migrations are additive and reversible; no destructive migration without a documented two-release plan. |
| NFR-MAINT-7 | Architecture decisions are recorded as short ADRs in `specs/adr/`. |

## 7. Observability

| ID | Requirement |
|---|---|
| NFR-OBS-1 | Structured JSON logs with request id, user id, route and duration. |
| NFR-OBS-2 | Error tracking (Sentry or equivalent) with release tagging and source maps. |
| NFR-OBS-3 | Application metrics: queue depth per queue, failed jobs, CoC API success/error/latency, media pipeline throughput, cache hit ratio. |
| NFR-OBS-4 | Health endpoint checking database, storage and queue liveness, consumed by uptime monitoring. |
| NFR-OBS-5 | Alerts: queue depth > 500 for 10 min, failed jobs > 20/h, CoC API error rate > 25% for 15 min, disk > 80%, 5xx rate > 1%. |
| NFR-OBS-6 | An admin-visible sync-health page so moderators can distinguish "user is lying" from "API is down". |

## 8. Usability & accessibility

| ID | Requirement |
|---|---|
| NFR-UX-1 | Mobile-first; every page usable at 360 px width with no horizontal scroll. |
| NFR-UX-2 | WCAG 2.1 AA contrast for all text and meaningful UI. |
| NFR-UX-3 | No information conveyed by colour alone (TH badges carry the number, statuses carry a label). |
| NFR-UX-4 | Full keyboard navigation with a visible focus ring on every interactive element. |
| NFR-UX-5 | All motion respects `prefers-reduced-motion`. |
| NFR-UX-6 | Every list surface has designed empty, loading (skeleton) and error states. |
| NFR-UX-7 | Forms report errors inline, preserve input on failure, and never lose an in-progress upload. |
| NFR-UX-8 | The site is installable as a PWA with an offline fallback page. |

## 9. Cost

| ID | Requirement |
|---|---|
| NFR-COST-1 | Target infrastructure spend ≤ $60/month at launch, ≤ $250/month at 50k MAU. |
| NFR-COST-2 | Media egress must be zero-rated or near-zero (Cloudflare R2 + CDN); never serve media from the application server. |
| NFR-COST-3 | Video transcoding must be able to run on the existing worker (ffmpeg) before paying for a transcoding service; the decision point is documented in [10](10-media-storage.md). |
| NFR-COST-4 | No managed Redis, no Elasticsearch, no message broker until the trigger conditions in [21](21-caching-strategy.md) and [17](17-search-and-discovery.md) are met. |

## 10. Legal constraints that act as requirements

1. **No Clash of Clans account buying, selling, trading, renting or transfer** may be facilitated,
   advertised or brokered. This is enforced in listing validation, moderation policy, report
   reasons and the ToS.
2. **No real-money trading of in-game currency** (gems, resources, event passes).
3. **No paid boosting that requires account access** (sharing credentials violates the game ToS).
4. **Clash of Clans assets** — troop, hero, spell, equipment, Town Hall and clan-related imagery —
   may be used **solely to display or identify Clash of Clans content**, where Supercell's Fan
   Content Policy permits, and **must not be modified**. They never appear in platform branding,
   navigation, UI components, our badges, illustrations or marketing.
5. **The platform's visual identity is original**: logo, wordmark, colour system, typography
   choices, icon set, badges and illustrations are created for this platform. No Supercell fonts,
   logos, UI sprites or redrawn art ([18 §2](18-design-system.md)).
6. **User-uploaded screenshots of in-game content** are permitted under fan content norms; uploads
   of other creators' work without credit are handled as a copyright report path.
