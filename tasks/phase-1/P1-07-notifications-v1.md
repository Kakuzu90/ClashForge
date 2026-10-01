---
id: P1-07
title: Add in-app notifications with the bell, the notification centre and the Phase 1 events
phase: 1
status: done
depends_on: [P1-01]
---

# Notifications v1

## Spec refs
- Core: specs/16 §1 (delivery model), §2 (catalogue: Security rows, Media processing failed), §6 (in-app UX), §7 (retention); specs/07 `notifications`
- Plus: specs/20 §2 Notifications (`SendNotificationJob`, `PruneNotificationsJob`), §3 (`02:15 notifications:prune`); specs/21 §3 `notif:unread:{id}`; specs/05 §2 (Notifications: `Notifier`, `NotificationReadModel`; Auth and Media are edge modules), §3 events; specs/18 §6 Notifications, §8 (count live region); specs/10 §9 (`MediaRetriesExhausted`, listener: P1-07); specs/04 §3 (IDOR, write gating); specs/11 "Data exposure via page props"
- FR: FR-NOTIF-1 (centre, unread count, mark-read, mark-all-read, pagination); FR-NOTIF-2 and -3 rows reachable in Phase 1 (the rest join with their modules)
- Edge cases: specs/23 has no notification rows. Covered here: another user's notification id 404s; a sanction lifted before its notice ran writes nothing (as the email does)

## Scope
- **Schema:** `notifications` per 07 (uuid PK, type, notifiable morph, `data` jsonb, `read_at`, `group_key`, timestamps; the three indexes). `Domain/Notifications/Models/Notification` (internal), factory.
- **Domain (`Domain/Notifications`):** `NotificationType` enum (category, copy and target URL rendered at read time from `data.params`, never stored HTML); `InAppChannel` writing the row; `Notifier::send()`; `NotificationReadModel` (cursor page per category, latest first; unread count through `notif:unread:{id}`, 60 s, cleared on write and read); `NotificationService` (mark one, mark all). Delivery wiring per Open question 1.
- **Phase 1 events, in-app:** password changed, new sign-in from an unrecognised device, account suspended, account banned, sanction lifted / expired, media processing failed (Open question 2).
- **Policy:** owner-scoped lookup (`whereMorphedTo` the viewer) so another user's id 404s, then `NotificationPolicy::update`; registered centrally.
- **HTTP + UI:** `GET /notifications` (`Notifications/Index`: tabs All · Security · Bases, unread with the gold left border, cursor pages, mark all read; empty "You're all caught up"). `POST /notifications/{id}/read` then redirect to the target; `POST /notifications/read` (mark all). Shared `unreadCount` (null for guests). Header bell: count capped "99+", a live region on change, links to `/notifications` (Open question 3); 60 s `only: ['unreadCount']` poll, stopped while the tab is hidden.
- **Jobs / schedule:** `notifications:prune` daily 02:15 (read > 90 d, unread > 180 d, 500 rows per user), chunked.
- **Config keys:** `platform.notifications.{per_page, unread_cache_ttl, poll_seconds, prune_read_days, prune_unread_days, max_per_user}`.

## Out of scope
- Grouping / aggregation (FR-NOTIF-5), preferences and the Settings › Notifications page (FR-NOTIF-4), digests (P5)
- Non-security email rules: daily cap, List-Unsubscribe, bounce and complaint handling (Open question 2)
- Bell dropdown with the 10 latest (Open question 3); events of modules not built yet; broadcast / websockets (16 §1)

## Acceptance criteria
- Functional: FR-NOTIF-1; each Phase 1 event above writes one row with the right type, copy and link; existing security emails unchanged.
- Authorization: guests to sign in; own notifications only (others' ids 404); marking read open per Open question 4; suspended accounts keep `/notifications` (already allowed).
- Data: props carry rendered text, category, link, read state and time; no other user's data, no raw `data` blob.
- States: list empty / loading / error; badge hidden at 0; 375 px and desktop.

## Tests
- Feature (`assertInertia`): page props per tab, cursor paging, mark one (redirects to its target), mark all, unread count shared and cache cleared on both; each event produces its row (`Notification::fake()` plus a real channel run); query budget ≤ 25.
- Security: IDOR on mark-read (other user's id, malformed id), props exposure, rate limit on the writes, role × status on the routes.
- Unit: `NotificationType` rendering for every case (missing params tolerated); prune selection (90 / 180 days, 500 cap).
- Vitest: bell (99+, hidden at 0, poll stops while hidden), notification list item states.

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended). Q2 and Q3 synced → tasks/BOARD.md (P1-15, P5-02 note); Q4's write gating is synced into specs/04 §3 at implement.
1. **Who sends the in-app copy?** Recommended: the Notifications module owns in-app only. Moderation (it depends on Notifications, 05 §2) adds `InAppChannel` to its sanction notifications. Auth and Media are edge modules, so they dispatch events (`PasswordChanged` and `UnrecognisedDeviceSignedIn` new, `MediaRetriesExhausted` exists) that a queued Notifications listener turns into rows. Security emails stay where they are, on `high`. Alternative: move every email into Notifications now (05 §3 says sanction emails move "with Notifications v1"); more churn, same result for the user.
2. **Media processing failed is I + E. Send the email now?** It is the first non-security email, so it needs the 16 §4 rules (10 a day, List-Unsubscribe, preferences link). Recommended: in-app only here; new board row P1-15 "Non-security email: `SendEmailNotificationJob` on `low`, 10 / day cap, List-Unsubscribe header and an unsubscribe page, bounce / complaint handling once the mail provider exists (P0-09)", depending on P1-07, before P2-02 needs it.
3. **Bell dropdown now?** 16 §6 wants a dropdown of the 10 latest. Recommended: v1 bell is a link to `/notifications` with the count (works the same at 375 px); the dropdown joins P5-02 "Notifications v2" (board row note).
4. **Who may mark notifications read?** Recommended: any signed-in account, including unverified, restricted, suspended and pending-deletion ones (own housekeeping, no content), so the routes skip `account.active`; `throttle:global-write` still applies. Banned accounts are already signed out.

### Decisions and divergences (implement, 2026-10-01)
1. `Domain/Notifications`: `notifications` per 07 (uuid PK, generated as UUIDv7 by `HasUuids`; partial unread index through raw DDL on both drivers), `NotificationType` (6 Phase 1 types) and `NotificationCategory` (the 8 catalogue categories), `InAppChannel` (a Laravel channel class), `Notifier::send()`, `NotificationReadModel`, `NotificationService`, `NotificationPolicy`, `NotificationPruner`. synced → specs/05 §2, specs/04 §3 (policy list).
2. A row stores the type value in `type` and `{params}` in `data`; title, body and link are rendered on read from the enum, so copy fixes reach old rows and nothing rendered is stored. A type value the code no longer knows renders as "This notification is no longer available" with no link. The centre's index is `(notifiable_type, notifiable_id, created_at DESC, id DESC)` to match its order. synced → specs/16 §1, specs/07.
3. Delivery (Open question 1): Moderation's three sanction notices list `InAppChannel` beside `mail` and implement `InAppNotification`. Auth now dispatches `PasswordChanged` and `UnrecognisedDeviceSignedIn` after its own email; the Notifications listener `WriteInAppNotice` (queued, `high`) turns those and Media's `MediaRetriesExhausted` into rows. Emails unchanged. Both Auth events dispatch after commit. synced → specs/05 §3, specs/16 §2, specs/10 §9.
4. Tabs show only categories with at least one type: All · Security · Bases in v1. The task's "Moderation" tab has no type yet: the catalogue files suspended / banned / lifted under Security, and content decisions arrive with P3-06. synced → specs/18 §6.
5. Unread count: shared `unreadCount` (null for guests) from `notif:unread:{id}` (60 s, `platform.notifications.unread_cache_ttl`), dropped on every write and read. Prune does not drop it; the TTL catches up. synced → specs/21 §3.
6. Bell (`Components/shell/NotificationBell.vue`, in `SiteHeader` before the account controls when signed in): a link with a gold count badge capped at "99+", `aria-label` with the count, a polite live region. The poll is Inertia `usePoll` (async partial reload of `unreadCount`) started and stopped on `visibilitychange`; the 60 s interval is a client constant from 16 §6 (`useUnreadPoll`), not server config. synced → specs/18 §5, specs/16 §6.
7. Clicking a row posts `/notifications/{id}/read` and redirects to its target, or back with the scroll kept when it has none; a read row with no target is plain text. Targets are built server-side from route names, never from stored data. Mark all: `POST /notifications/read`, flash "All notifications marked as read."
8. Routes sit behind `auth` and `throttle:global-write` only (Open question 4); `EnforceAccountStatus` already lets suspended accounts reach `notifications.*`. A throttled mark-read redirects back with the limiter's flash error, as every other write. Mark all is authorized through `NotificationPolicy::markAllRead`. synced → specs/04 §3.
9. Pagination by `(created_at, id)`; `CursorShape::parameters()` added so the request validates this two-column cursor shape too.
10. `notifications:prune` (daily 02:15) runs inline as a command, not as the `PruneNotificationsJob` of 20 §2: a few chunked deletes (1,000 ids per statement). The 500 cap removes only read rows, oldest first, and counts only rows the age rules keep, so `--dry-run` reports what a real run removes. synced → specs/20 §2, specs/16 §7, specs/19 §7.
11. `NotificationType` tests are Feature tests: links come from the router. New UI: `NotificationItem` (gallery entry added), page `Notifications/Index` (R-31: celebrate register for the page title and empty-state illustration, plain list rows; the illustration is an original bell with a check).

### Review fixes (verify, 2026-10-01)
- Security + spec (medium): a forged cursor with a non-date `created_at` passed validation and was a 500 on Postgres; the cursor's time must now have the paginator's shape (Decision 9), tested.
- Security (note): mark all now goes through `NotificationPolicy::markAllRead` too (Decision 8).
- Spec (low): `notifications:prune --dry-run`, counting what the real run removes (Decision 10), tested.
- Spec (low): list index `created_at DESC, id DESC` (Decision 2).
- Spec (low): task Scope corrected (`POST /notifications/read`, tabs All · Security · Bases).
- Spec (low): tests added for the cache drop on mark all, staff roles on another account's notification (404), the sanction service path writing rows, and a sanction lifted before its notice ran writing none.
- Spec (low): `PasswordChanged` and `UnrecognisedDeviceSignedIn` implement `ShouldDispatchAfterCommit` (Decision 3).
- antislop audit-017: no findings.
- Owner (after review): the tab row scrolled vertically because each tab's 44 px hit area overflowed the `overflow-x-auto` row; the row now wraps instead.

