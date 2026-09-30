---
paths:
  - "src/tests/**"
---

# Test rules

- Pest 3. Feature tests are the default and mirror the domain tree (`tests/Feature/<Module>/`).
- Every Inertia page: `assertInertia(fn ($page) => $page->component('<Area>/<Action>')->has(...))`
  asserting the component and prop shape — and that private fields are absent.
- Every new surface: happy path, authorization (each role that must be denied), validation.
- `tests/Security/` for input, files and trust boundaries: IDOR (user B vs user A's object),
  mass assignment, stored XSS payloads, upload abuse, rate limits, props exposure.
- External edges always faked: `Http::fake()` (CoC API, with fixtures from `tests/Fixtures`),
  `Storage::fake()`, `Queue::fake()`/`Bus::fake()`, `Mail::fake()`, `Notification::fake()`.
- Freeze time with `Date::setTestNow()` / `travelTo()`; tests run on the `array` cache store.
- Assert query counts on list/detail pages (≤ 25, `specs/03` NFR-PERF-7).
- Limits come from config in tests — never hardcode the number the config defines.
- Vitest + Vue Test Utils under `tests/js/` for composables and components with logic.
