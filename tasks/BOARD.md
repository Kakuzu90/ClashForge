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
| P0-05 | Media pipeline (tables, intent/complete, processing, sweeper; split queue workers per specs/20 §1) | todo | P0-02 | |
| P0-06 | GameAssets module + asset policy plumbing | todo | P0-02, P0-03 | |
| P0-07 | Ops: health, logging, error tracking, workers | todo | P0-01 | |

## Phase 1 — Identity

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P1-01 | Registration, login, verification, reset | todo | P0-* | |
| P1-02 | Roles, status, policy scaffold | todo | P1-01 | |
| P1-03 | Profiles + avatar upload | todo | P1-02, P0-05 | |
| P1-04 | Privacy settings + public profile | todo | P1-03 | |
| P1-05 | Settings area incl. sessions, deletion | todo | P1-02 | |
| P1-06 | Admin v1 + audit log | todo | P1-02 | |
| P1-07 | Notifications v1 | todo | P1-01 | |

## Phase 2 — Verified CoC accounts

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P2-01 | API client, decorators, key pool | todo | P0-02 | |
| P2-02 | Attach + token verification flow | todo | P2-01, P1-02 | |
| P2-03 | Conflicts, disputes, ownership transfer | todo | P2-02, P1-06 | |
| P2-04 | PlayerCard, account detail, progression | todo | P2-02, P0-06 | |
| P2-05 | Asset pack v1 | todo | P0-06 | |

## Phase 3 — Bases + moderation (MVP)

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P3-01 | Publishing + composer | todo | P2-02, P0-05 | |
| P3-02 | Video processing | todo | P0-05 | |
| P3-03 | Feed, trending, landing pages | todo | P3-01 | |
| P3-04 | Likes, bookmarks, comments, counters | todo | P3-01 | |
| P3-05 | Search v1 | todo | P3-01 | |
| P3-06 | Moderation v1 | todo | P3-01, P1-06 | |
| P3-07 | SEO surfaces | todo | P3-03 | |

## Phases 4–6

| Id | Task | Status | Depends on | File |
|---|---|---|---|---|
| P4-01 | Clans + clan sync | todo | P2-01 | |
| P4-02 | Recruitment posts + applications | todo | P4-01 | |
| P5-01 | Follows, activity, fan-out | todo | P3-* | |
| P5-02 | Notifications v2, appeals, anomaly detection | todo | P5-01 | |
| P6-01 | Marketplace (conditional — see specs/15) | blocked | P3–P5 + legal preconditions | |
