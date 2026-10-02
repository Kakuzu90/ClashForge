# Task Board

Source: `specs/25-development-phases.md` §4. One row per phase task; `File` is filled when
`/next-task` writes the task file under `tasks/phase-N/`.
Status: `todo` · `in-progress` · `review` · `done` · `blocked`.
Phase end (before the next phase starts): accessibility pass + antislop R-35 click-through on the phase's main flows (`docs/ai/workflows/verify.md` §3).

## Phase 0 — Foundation

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P0-01 | Project setup, CI, static analysis (incl. Inertia/Vue/TS, SSR entry, laravel/boost; CI runs `scripts/check.sh`; `git config core.hooksPath .githooks`) | done | — | tasks/phase-0/P0-01-project-setup.md |
| P0-02 | Domain skeleton + `Support` primitives | done | P0-01 | tasks/phase-0/P0-02-domain-skeleton.md |
| P0-03 | Design tokens + `Ui*` Vue primitives | done | P0-01 | tasks/phase-0/P0-03-design-tokens.md |
| P0-04 | App shell, layouts, navigation | done | P0-03 | tasks/phase-0/P0-04-app-shell.md |
| P0-05 | Media pipeline (tables, intent/complete, image processing; split queue workers per specs/20 §1) | done | P0-02 | tasks/phase-0/P0-05-media-pipeline.md |
| P0-06 | GameAssets module + asset policy plumbing | done | P0-02, P0-03 | tasks/phase-0/P0-06-game-assets.md |
| P0-07 | Ops: health, logging, error tracking, workers | done | P0-01 | tasks/phase-0/P0-07-ops.md |
| P0-08 | Media lifecycle jobs (orphan sweeper, purge-deleted, retry-failed, `DeleteMediaObjectsJob`, worker boot temp sweep, `media:reconcile-storage` with its `game/` exclusion test; split from P0-05/P0-06) | done | P0-05 | tasks/phase-0/P0-08-media-lifecycle.md |
| P0-09 | Staging + backups: deploy runbook, staging deployed, backup restore drill, Sentry project + alerts + frontend error tracking, R2 + Cloudflare (CDN, `game/` resizing off, CORS, `quarantine/` lifecycle) | blocked | P0-07 + hosting/R2/Sentry accounts | |

## Phase 1 — Identity

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P1-01 | Login, logout, remember-me, password reset; `users` table in its specs/07 shape (registration + verification split to P1-08) | done | P0-* (P0-09 waived by owner, 2026-09-30) | tasks/phase-1/P1-01-login-and-password-reset.md |
| P1-02 | Roles, status, policy scaffold | done | P1-01 | tasks/phase-1/P1-02-roles-status-policy-scaffold.md |
| P1-03 | Profiles + avatar upload (avatar uploads must stay open to restricted accounts: make `account.active:content` on `uploads/*` collection-aware, from P1-02) | done | P1-02, P0-05 | tasks/phase-1/P1-03-profiles-and-avatar.md |
| P1-04 | Privacy settings + public profile (+ `user_stats`, from P1-03) | done | P1-03 | tasks/phase-1/P1-04-privacy-and-public-profile.md |
| P1-05 | Security settings: password change, session list/revoke, 30-day absolute cap, 15-min re-confirmation page (+ new-device sign-in email, from P1-01) | done | P1-02 | tasks/phase-1/P1-05-security-settings-and-sessions.md |
| P1-06 | Audit log + admin viewer: `Domain/Audit` (`audit_logs`, `AuditLogger`, append-only), the `audit_logs` entry for role changes from `RoleAssignmentService` (from P1-02), `/admin/audit` with filters, admin nav (split: user admin → P1-12, dashboard → P1-13) | done | P1-02 | tasks/phase-1/P1-06-audit-log-and-viewer.md |
| P1-12 | Admin user list + detail (FR-ADMIN-2 users, read): search, filters, detail with status and audit trail, `view-users` ability (admin+), `admin.user_viewed` security log (split from P1-06; sanctions → P1-14) | done | P1-06 | tasks/phase-1/P1-12-admin-user-list-and-detail.md |
| P1-14 | Sanctions (FR-ADMIN-3, FR-MOD-5/6/8): suspend / ban / lift via `SanctionService`, `user_sanctions` + `moderation_actions`, status applied in the same transaction, `moderation:expire-sanctions`, suspended / banned / lifted emails, sanction `audit_logs` entries, history + action panel on the user detail (split from P1-12) | done | P1-12 | tasks/phase-1/P1-14-sanctions.md |
| P1-13 | Admin dashboard v1 (FR-ADMIN-5): new signups, failed jobs, media storage usage; open reports, disputes and API health added as their modules land (split from P1-06) | done | P1-12 | tasks/phase-1/P1-13-admin-dashboard.md |
| P1-07 | Notifications v1 (+ in-app copies of "Password changed" and "New sign-in", from P1-05) | done | P1-01 | tasks/phase-1/P1-07-notifications-v1.md |
| P1-08 | Registration + email verification: username rules + reserved list, disposable-email blocklist, HIBP, Turnstile (register and `/forgot-password`, specs/11), honeypot + min fill time, existing-email notice, signed 60-min link, resend limiter, `UserRegistered`/`EmailVerified` (split from P1-01) (the `known_devices` item from P1-05 became a first-sign-in email, see the task file) | done | P1-01 | tasks/phase-1/P1-08-registration-and-email-verification.md |
| P1-10 | Email change (FR-AUTH-8): re-confirmation, verification link to the new address, notice to the old one, the same answer when taken (split from P1-05) | done | P1-05, P1-08 | tasks/phase-1/P1-10-email-change.md |
| P1-11 | Account deletion + Danger zone (FR-AUTH-9, NFR-PRIV-2): request with re-confirmation, `pending_deletion` + `deletion_requested_at`, cancel on sign-in, `platform:anonymize-deleted` for Phase 1 data with an `audit_logs` entry (specs/08 §6; split from P1-05) | done | P1-05, P1-06 | tasks/phase-1/P1-11-account-deletion.md |
| P1-09 | Username change + `username_history`: once per 30 days, old names reserved 90 days, `/u/{old}` redirects (FR-PROFILE-7, specs/23 §1; split from P1-03) | done | P1-04, P1-08 | tasks/phase-1/P1-09-username-change.md |
| P1-15 | Non-security email (specs/16 §4): `SendEmailNotificationJob` on `low`, 10 per user per day cap, List-Unsubscribe header and an unsubscribe page, bounce / complaint handling once the mail provider exists (P0-09); first user: the "Media processing failed" email (from P1-07) | done | P1-07 | tasks/phase-1/P1-15-non-security-email.md |
| P1-16 | Unverified accounts (specs/23 §1): reminder at day 3, final warning before the purge, never-verified accounts purged at 30 days (from P1-08) | done | P1-08 | tasks/phase-1/P1-16-unverified-account-lifecycle.md |

## Phase 2 — Verified CoC accounts

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P2-01 | API client: `CocApiClient` + HTTP client, DTO mappers, `PlayerTag`, key pool, fake + fixtures, use-case services, `coc:check-health` (decorators split to P2-07, rotation to P2-08) | done | P0-02 | tasks/phase-2/P2-01-coc-api-client.md |
| P2-07 | Client decorators: throttle (rate budgets, interactive/background buckets), circuit breaker (incl. maintenance), cache + negative cache + stale-while-error, `coc_api_requests` log + prune, `CocApiStatus` (split from P2-01; banner and dashboard panel split to P2-10) | done | P2-01 | tasks/phase-2/P2-07-coc-client-decorators.md |
| P2-10 | API status UI: site-wide banner while the CoC circuit is open or in maintenance (shared prop from `CocApiStatus`) + the dashboard's API sync health panel (from P1-13) (split from P2-07) | todo | P2-07, P1-13 | |
| P2-08 | Key rotation via the developer portal (`coc:rotate-keys`, egress IP detection) + `/leagues` `/locations` reference data (`coc:refresh-reference-data`) (split from P2-01) | todo | P2-01 | |
| P2-02 | Attach + token verification backend: `coc_accounts`, `coc_account_claims`, attach and verify services, supersede, policy, limits (UI → P2-11, notifications → P2-12, clans stub → P2-13, detach/featured → P2-14) | done | P2-01, P2-07, P1-02 | tasks/phase-2/P2-02-attach-and-verify.md |
| P2-03 | Conflicts, disputes, ownership transfer (+ the dashboard's pending disputes panel, from P1-13) | todo | P2-02, P1-06 | |
| P2-04 | PlayerCard, account detail, progression | todo | P2-02, P2-13, P0-06 | |
| P2-05 | Asset pack v1 | todo | P0-06 | |
| P2-06 | System health page (specs/20 §5–6, NFR-OBS-6): queue depth, oldest pending job, failed jobs grouped by class with retry / delete (audited), CoC key pool and sync success rate; linked from the dashboard's failed-jobs panel (from P1-13) | todo | P2-01, P2-07, P1-13 | |
| P2-09 | Tiered sync, snapshots, manual refresh (specs/09 §6, FR-COC-9/10/14): `sync_states`, `coc:sync-accounts`, `SyncCocAccountJob`, snapshot-on-change, stale-data fallback (board gap: listed in specs/25 §4, from P2-01) | todo | P2-02, P2-07 | |
| P2-11 | Attach flow UI (`/accounts/attach`: tag → confirmation card → token → success, error states for not found / conflict (with the token path through `VerifyOwnershipService::verifyTag`, specs/13 §4 A) / invalid token / API unavailable; the own profile's Accounts empty-state CTA, from P1-04) (split from P2-02) | todo | P2-02, P2-12 | |
| P2-12 | Ownership notifications: CoC account verified (I + E), your verified account was claimed by someone else (I + E*), listening to `CocAccountVerified` / `CocAccountOwnershipTransferred` (specs/13 §8, specs/16) (split from P2-02) | todo | P2-02 | |
| P2-13 | Clans stub: `clans` table (specs/07, read-only stub in M), ensure-clan listener on `CocAccountVerified`, `coc_accounts.clan_id` (split from P2-02) | todo | P2-02 | |
| P2-14 | Detach, release and featured account (FR-COC-12/13, specs/13 §6): password re-confirmation, `released`, reuse on re-attach, featured switch; release a deleted account's tags at the end of the deletion window, and a banned owner's after 30 days (from P2-02 security review) (split from P2-02) | todo | P2-02 | |

## Phase 3 — Bases + moderation (MVP)

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P3-01 | Publishing + composer (+ the publish CTA in the own profile's Bases empty state, from P1-04) | todo | P2-02, P0-05 | |
| P3-02 | Video processing | todo | P0-05 | |
| P3-03 | Feed, trending, landing pages | todo | P3-01 | |
| P3-04 | Likes, bookmarks, comments, counters (+ `user_stats` listeners and nightly recompute calling `CacheInvalidator::profile()`, StatBlock count-up, from P1-04) | todo | P3-01 | |
| P3-05 | Search v1 | todo | P3-01 | |
| P3-06 | Moderation v1 (incl. 30-day quarantine purge with `audit_logs` entry, specs/10 §9; from P0-08) (+ the dashboard's open reports panel, from P1-13; the queue fills `/moderation/reports`, the moderators' page outside /admin, owner decision 2026-10-02) | todo | P3-01, P1-06 | |
| P3-07 | SEO surfaces | todo | P3-03 | |

## Phases 4–6

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P4-01 | Clans + clan sync | todo | P2-01 | |
| P4-02 | Recruitment posts + applications | todo | P4-01 | |
| P5-01 | Follows, activity, fan-out | todo | P3-* | |
| P5-02 | Notifications v2, appeals, anomaly detection (+ the bell dropdown with the 10 latest, specs/16 §6, from P1-07) | todo | P5-01 | |
| P6-01 | Marketplace (conditional — see specs/15) | blocked | P3–P5 + legal preconditions | |
