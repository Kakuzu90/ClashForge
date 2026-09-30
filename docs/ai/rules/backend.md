---
paths:
  - "src/app/**/*.php"
  - "src/routes/**/*.php"
  - "src/config/**/*.php"
---

# Backend rules

- `app/Domain/<Module>/` owns its models, services, actions, events, policies, queries, DTOs
  (`specs/19` §1). Other modules use only its `Contracts|Services|Data|Events|Enums`.
- Controllers (`app/Http/Controllers/<Area>/<Resource>Controller`): validate (Form Request) →
  `authorize()` → call a service/action → `Inertia::render('<Area>/<Action>', $props)` or redirect.
  Http cannot reference domain models, so when the policy needs a model the service/action that
  loads it (owner-scoped, 404 first) calls `Gate::forUser($user)->authorize()` (specs/04 §3).
  No queries beyond route-model binding, no business rules.
- Props are `Data` DTOs (spatie/laravel-data style, readonly) — never models, collections of
  models, or `toArray()`. Include per-resource `can` flags from the policy.
- Services own transactions (`DB::transaction`); events are dispatched after commit. An action
  never opens a second transaction.
- Models: relationships, casts, scopes, accessors only. `$fillable` always; never `role`/status.
- Status columns are PHP backed enums with `label()` and `color()`.
- Value objects (`PlayerTag`, `BaseLink`, `LayoutHash`, `ThLevel`) hold validation for their
  invariant.
- `Cache::`/`cache()`, `dispatch()`/`Queue::`, `RateLimiter`, `Cache::lock()` only. No `Redis::`.
- Time: `CarbonImmutable`, `Date::now()`. Money (P3): integer minor units + currency.
- Every limit/weight/window is a config key in `config/{coc,media,moderation,bases,recruitment,platform,assets}.php`.
- No `env()` outside `config/`. No `dd`/`dump`/`ray`.
- Mutations are POST/PATCH/DELETE only (the deduped `/bases/{slug}/copy` is the sole exception).
- SSR is disabled for `/admin/*`, `/settings/*`, `/dashboard`, `/notifications`, `/account/*`. Render pages with
  `PageMeta::page($component, $props, new PageMeta(...))` so the root view gets title/description/
  canonical/OG/JSON-LD and the client gets `meta.title`.
