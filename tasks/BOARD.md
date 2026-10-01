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
| P1-05 | Settings area incl. sessions, deletion (+ new-device sign-in email, from P1-01) | todo | P1-02 | |
| P1-06 | Admin v1 + audit log (incl. the `audit_logs` entry for role changes from `RoleAssignmentService`, from P1-02) | todo | P1-02 | |
| P1-07 | Notifications v1 | todo | P1-01 | |
| P1-08 | Registration + email verification: username rules + reserved list, disposable-email blocklist, HIBP, Turnstile (register and `/forgot-password`, specs/11), honeypot + min fill time, existing-email notice, signed 60-min link, resend limiter, `UserRegistered`/`EmailVerified` (split from P1-01) | todo | P1-01 | |
| P1-09 | Username change + `username_history`: once per 30 days, old names reserved 90 days, `/u/{old}` redirects (FR-PROFILE-7, specs/23 §1; split from P1-03) | todo | P1-04, P1-08 | |

## Phase 2 — Verified CoC accounts

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P2-01 | API client, decorators, key pool | todo | P0-02 | |
| P2-02 | Attach + token verification flow (+ the attach CTA in the own profile's Accounts empty state, from P1-04) | todo | P2-01, P1-02 | |
| P2-03 | Conflicts, disputes, ownership transfer | todo | P2-02, P1-06 | |
| P2-04 | PlayerCard, account detail, progression | todo | P2-02, P0-06 | |
| P2-05 | Asset pack v1 | todo | P0-06 | |

## Phase 3 — Bases + moderation (MVP)

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P3-01 | Publishing + composer (+ the publish CTA in the own profile's Bases empty state, from P1-04) | todo | P2-02, P0-05 | |
| P3-02 | Video processing | todo | P0-05 | |
| P3-03 | Feed, trending, landing pages | todo | P3-01 | |
| P3-04 | Likes, bookmarks, comments, counters (+ `user_stats` listeners and nightly recompute calling `CacheInvalidator::profile()`, StatBlock count-up, from P1-04) | todo | P3-01 | |
| P3-05 | Search v1 | todo | P3-01 | |
| P3-06 | Moderation v1 (incl. 30-day quarantine purge with `audit_logs` entry, specs/10 §9; from P0-08) | todo | P3-01, P1-06 | |
| P3-07 | SEO surfaces | todo | P3-03 | |

## Phases 4–6

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P4-01 | Clans + clan sync | todo | P2-01 | |
| P4-02 | Recruitment posts + applications | todo | P4-01 | |
| P5-01 | Follows, activity, fan-out | todo | P3-* | |
| P5-02 | Notifications v2, appeals, anomaly detection | todo | P5-01 | |
| P6-01 | Marketplace (conditional — see specs/15) | blocked | P3–P5 + legal preconditions | |
