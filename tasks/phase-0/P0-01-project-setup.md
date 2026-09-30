---
id: P0-01
title: Scaffold Laravel 12 + Inertia/Vue/TS in src/ with docker, CI and static analysis
phase: 0
status: done
depends_on: []
---

# Scaffold Laravel 12 + Inertia/Vue/TS in src/ with docker, CI and static analysis

## Spec refs
- Core: specs/19 §1 (layout), §2 (Deptrac rules), §4 (routes), §6 (tests); specs/25 Phase 0 "Project setup"
- Plus: specs/06 §1, §2 Decision, §4 (database drivers), §5 (build, testing); specs/05 §6 (environments); specs/03 NFR-MAINT-2/3, NFR-PERF-6; specs/11 (dependency audit, pinned CI actions); specs/20 §1 (worker commands); specs/10 §2.1 (MinIO profile)
- FR: none (foundation)
- Edge cases: none

## Scope
- **Docker** (repo root): `docker-compose.yml` + `docker/php/Dockerfile` (PHP 8.3-FPM, pdo_pgsql, gd, intl, exif, ffmpeg, Node 22 for SSR), `docker/nginx/default.conf`. Services: `app`, `web`, `node` (Vite), `queue` (`--sleep=1 --rest=0.2 --max-time=3600`), `scheduler`, `db` (postgres:16), `mailpit`; profiles `ssr`, `storage` (minio), `tools` (adminer) — specs/05 §6, specs/20 §1.
- **Laravel 12** in `src/`: PHP 8.3; `.env.example` with `DB_CONNECTION=pgsql`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` (specs/06 §4); default cache/jobs/sessions migrations kept.
- **Inertia 2 + Vue 3 + TS**: `inertia-laravel`, `@inertiajs/vue3`, `resources/js/app.ts` + `ssr.ts`, root view `resources/views/app.blade.php` (`@vite`, `@inertiaHead`, `@inertia`), `HandleInertiaRequests` middleware; Tailwind CSS 4 via Vite; one placeholder `Home/Index` page proving client + SSR render (specs/06 §2, specs/19 §1).
- **PHP ↔ TS contracts**: `spatie/laravel-data`, `spatie/laravel-typescript-transformer` → `resources/js/types/generated.d.ts`; Laravel Wayfinder → `resources/js/routes/`, `actions/` (specs/06 §1).
- **Quality tooling**: Pint (Laravel preset), Larastan L6 (+ L8 path config for `app/Domain`), Deptrac `deptrac.yaml` with the layers of specs/19 §2, Pest 3 + arch preset; ESLint (`eslint-plugin-vue`, `vue/no-v-html`), Prettier, `vue-tsc`, Vitest + Vue Test Utils. npm scripts: `typecheck`, `lint`, `test`, `build` (client + SSR).
- **Tests**: `tests/{Feature,Unit,Security,Contract,Architecture,Fixtures,Support}` + `tests/js/`; one feature test asserting `Home/Index` via `assertInertia`; arch tests for no `dd/dump/ray`, no `env()` outside `config/`; `inertia.testing.ensure_pages_exist = true`.
- **Agent tooling**: verify every command in `scripts/check.sh`, `.claude/settings.json` and `AGENTS.md` actually works; fix any that differ. Install `laravel/boost` (dev) without overwriting `AGENTS.md`/`CLAUDE.md`.
- Config keys added: none beyond framework defaults.

## Out of scope
- `app/Domain/*` skeleton and `Support` primitives (P0-02)
- Design tokens, fonts, `Ui*` components, `/dev/components` (P0-03)
- Layouts, navigation, meta/OG shared props beyond the root view (P0-04)
- Media tables, MinIO bucket wiring, processing (P0-05)
- Sentry, health endpoint, structured logging, staging deploy (P0-07)
- CI workflow (deferred by owner 2026-09-30; `scripts/check.sh` is CI-ready when added)

## Acceptance criteria
- Functional: `docker compose up -d` serves `Home/Index` at http://localhost:8080, client-rendered and SSR-rendered (`--profile ssr`).
- Authorization: n/a (no write paths).
- Edge cases: SSR container down → page still renders client-side.
- States: n/a (placeholder page).
- `scripts/check.sh` passes end to end locally; Pest passes on both SQLite and Postgres.
- Pre-commit hook runs `check.sh --fast` on staged `src/` files.

## Tests
- Feature (`assertInertia`): `/` renders `Home/Index`.
- Architecture: no debug calls, no `env()` outside config, Deptrac layers enforced.
- Vitest: one smoke test so the runner is proven.
- Security: n/a.

## Notes

### Decisions
- Docker stack: port `~/Documents/playground/CoCCommunity` `docker/` + `docker-compose.yml`, infra only, adapted to this project (owner, 2026-09-30).
- CI: skipped for now (owner, 2026-09-30). Divergence from specs/25 Phase 0 "CI green" → sync at Finish.
- OrbStack must be running for implementation.

### Implementation notes
- Versions: Laravel 12.69, PHP 8.3.35, Inertia 2.0 (laravel) / 2.3 (vue3), Vue 3.5, TypeScript 6, Vite 7, Tailwind 4, Pest 3.8, Larastan 3.12 (L6), Deptrac 4.7, spatie/laravel-data 4, typescript-transformer 3, Wayfinder 0.1, Boost 2.10.
- SSR exclusions live in `config/inertia.php` → `ssr.except`, applied in `HandleInertiaRequests::handle`.
- SSR bundle is self-contained (`ssr.noExternal`), so the renderer never resolves `node_modules`.
- Wayfinder's Vite plugin is not used (the `node` container has no PHP); regenerate with `php artisan wayfinder:generate` (also run by `check.sh`).
- TS types: `App\Providers\TypeScriptTransformerServiceProvider` writes `resources/js/types/generated.d.ts`.
- Postgres test DB `clashforge_test` (created by `docker/postgres/init-test-db.sql` on fresh volumes); `check.sh` runs Pest on SQLite and, when `db` is up, on Postgres.
- `phpstan-domain.neon` (L8) runs automatically once `app/Domain` exists.
- Deptrac has layer-level rules now; per-module isolation is P0-02.
- Boost: package + MCP only (`.mcp.json`, via docker). Guidelines/skills not installed (token cost, overlap with `docs/ai/rules`).
- `.claude/settings.json` env deny rules narrowed so `.env.example` is readable.
- **Risk:** client JS baseline is 90.5 KB gzipped (Vue + Inertia) against the 120 KB public-page budget (NFR-PERF-6). Watch every dependency.

### Spec sync
- synced → specs/25 Phase 0 (CI deferred; `check.sh` as entry point)
- synced → specs/05 §6 (CI row)
- synced → specs/19 §1 (`wayfinder/` output dir)
- board → P0-05 carries the specs/20 §1 queue split
- antislop audit-001: findings 1–2 deferred by owner (placeholder page, replaced in P0-04).
