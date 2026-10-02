---
id: P2-10
title: Show the CoC API's state site-wide and on the admin dashboard, and pause verification while it is down
phase: 2
status: done
depends_on: [P2-07, P1-13]
---

# API status UI

## Spec refs
- Core: specs/09 §7 (maintenance and breaker rows: "site-wide banner"; degradation contract); specs/18 §4 (Alert / Banner `maintenance`, dismissible), §6 Admin "Dashboard" (one deferred panel per FR-ADMIN-5 surface, skeleton, empty, inline error)
- Plus: specs/23 §5 (maintenance break at peak: circuit opens, site-wide banner); specs/13 §9 (API down while verifying: disabled with a message, from P2-11); specs/05 §2 (`CocApiStatus` for other modules); specs/04 (`view-platform-stats`); specs/21 §3 (`coc:*` keys); specs/20 §6 (CoC metrics)
- FR: FR-ADMIN-5 ("API sync health"); NFR-AVAIL-2
- Edge cases: specs/23 §5 rows above

## Scope
- **Shared prop:** `cocApi`, null while the API is available (closed or half-open), else `{ reason: failures | maintenance }`, from `CocApiStatus::state()`. It is shared with every visitor, guests and SSR pages included: one cache read per request.
- **UI: banner.** The layouts (public, app) show `UiAlert` `maintenance` above the content:
  - **Maintenance:** "Clash of Clans is down for maintenance."
  - **Failures:** "Clash of Clans is not answering right now."
  - **Both then say:** "Game data may be a little out of date, and verifying accounts is paused until it is back."
  - It is dismissible for the rest of the tab's session while the outage lasts, and the next outage shows it again (Open question 2). It is announced with `role="status"`.
  - It shows no end time (Open question 3).
  - The `maintenance` Alert variant goes into `/dev/components`.
- **UI: verification paused** (from P2-11, 13 §9). While `cocApi` is set:
  - The token step (`Accounts/Verify`) and the conflict card disable the token field and button, with "Verification is paused while Clash of Clans is unavailable. Nothing you entered is lost."
  - Lookup and attach stay on, since attach works from cached data (13 §9).
- **Dashboard panel "Clash of Clans API"** (FR-ADMIN-5): its own deferred prop and group behind `view-platform-stats`, with skeleton, empty state and inline error like the others (18 §6). It shows (Open question 1):
  - breaker state and reason;
  - healthy keys of the total (ids only);
  - the last 24 h from `coc_api_requests`: calls, cache hits, failures, failure rate and the most common error code;
  - `openUntil` while it is open (admins only).
- **Domain (CocIntegration):** a public `CocApiHealthReport::summary()` → `CocApiHealthData`. It reads the request log and the key pool, which stay internal.
- **Config:** `coc.health.window_hours` (24).

## Out of scope
- Sync success rate (specs/20 §6) and per-account sync failures: P2-09 adds them to this panel (board row note).
- Admin-set site banners and feature flags (FR-ADMIN-7, P2); the System Health page (P2-06); the stale banner on the account page (P2-04).

## Acceptance criteria
- **Functional:** the banner appears while the breaker is open (both reasons) and disappears when it closes or goes half-open. The dashboard panel shows the figures above.
- **Degradation:** an open breaker never makes a page fail. The banner prop and the panel never call the API.
- **Authorization:** the panel only for `view-platform-stats` (admin+), and it is left out of the response for everyone else. The banner shows no key ids, error codes or times.
- **States:** the panel's loading, empty (no calls in the window) and error states. The banner at 375 px and desktop.

## Tests
- **Feature:** the shared prop for closed / open-failures / open-maintenance / half-open, for a guest and a user. The dashboard panel for an admin, absent for a moderator, and its numbers from seeded `coc_api_requests`. The window from config.
- **Security:** no key id, token or error detail in the shared prop.
- **Vitest:** the banner per reason, dismiss and come back, verification disabled while `cocApi` is set, the panel states.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Synced into this file's Scope and tasks/BOARD.md (P2-09).
1. **Panel contents:** the breaker state, the key pool and a 24 h summary of `coc_api_requests`; P2-09 adds the sync success rate to this panel. synced → specs/18 §6, specs/20 §6.
2. **Dismissing:** hidden for the rest of the tab's session (`sessionStorage`) while the outage lasts; the flag clears once `cocApi` is null, so the next outage shows it again. synced → specs/18 §4.
3. **No end time in the banner;** admins see `openUntil` on the dashboard panel. synced → specs/09 §7.

### Decisions and divergences (implement, 2026-10-02)
1. **Shared prop:** `cocApi` is `CocApiNoticeData { reason }` while the breaker is open, else null. It costs one cache read per request and never calls the API. The shared-props allowlist test now includes it. synced → specs/09 §7.
2. **Banner placement:** `CocApiBanner` (shell) sits at the top of `<main>` in the public, app and admin layouts. `UiAlert` gains a `maintenance` kind and a `dismissible` prop that emits `dismiss`. The dismissal lives in `sessionStorage` (`coc-api-banner-dismissed`), cleared when `cocApi` goes back to null. Storage errors are ignored, so the banner just comes back. R-31: the maintenance kind gets its own wrench outline, so the kind reads from the shape as well as the colour, like the other alert kinds. synced → specs/18 §4.
3. **Paused verification:** while `cocApi` is set, the token step and the conflict card show "Verification is paused", disable the field and the button, and send nothing. Lookup and attach stay on (13 §9: attach works from cached data). synced → specs/13 §9.
4. **Dashboard panel:** the prop is `cocApiHealth`, since `cocApi` is the shared prop. It comes from a new public `CocApiHealthReport::summary()` → `CocApiHealthData`.
   - `calls` counts the requests that left for the API; cache hits are counted apart.
   - A failure is what the breaker counts: an uncached row with a timeout (no status) or a 5xx. 403 and 429 belong to the key pool and the budget, and a 404 is an answer (review fix). A malformed 200 is logged as a 200, so the panel cannot count it.
   - The most common error code is taken among failures.
   - "Next try" (`openUntil`) shows only to admins (Open question 3).
   synced → specs/18 §6, specs/20 §6, specs/05 §2.
5. **Config:** `coc.health.window_hours` (24). synced → specs/09 §11.

### Review fixes (verify, 2026-10-02)
- Spec (medium): a dismissal survived a full page load after the outage ended, so the next outage could stay hidden. The flag now clears on mount whenever `cocApi` is null. Vitest added.
- Spec (low): the panel counted 403 and 429 as failures, which the breaker does not (specs/09 §7). It is aligned to timeouts and 5xx. Tested with 403 and 429 rows in the window.
- Spec (low): the conflict card's paused message now says "Nothing you entered is lost", like the token step. The dashboard controller's comment names the API panel.
- Spec (low), accepted: on server-rendered pages a dismissed banner renders, then hides on mount. Starting it hidden would trade that for a late appearance during an outage, which is worse.
- Security (low): the dashboard's security matrix (`tests/Security/Admin/DashboardAccessTest.php`) now includes `cocApiHealth`, and the exposure test checks that no configured token or key id reaches the panels.
- antislop audit-030: no findings.
- scripts/check.sh: all green (sqlite + postgres).

