# 16 — Notifications

## 1. Strategy

In-app first, email for anything security- or money-adjacent, aggregation for anything social.
Laravel's notification system with a database channel and a mail channel; **no broadcast channel**
in the MVP (it would require Redis + a websocket server).

Delivery model: a domain event → a queued listener → `Notifier::send()` → one `notifications` row
(+ optionally a mail job). Nothing user-facing is written synchronously in a request that the user
is waiting on. Two routes in (P1-07): a module that depends on Notifications lists `InAppChannel`
beside `mail` in its Laravel notification (Moderation's sanction notices); an edge module (Auth,
Media) dispatches an event that the queued `WriteInAppNotice` listener (`high`) turns into a row.
A row stores its `NotificationType` and parameters; the words and the link are rendered when it is
read, so copy fixes reach old rows. A type the code no longer knows reads "This notification is no
longer available", with no link.

## 2. Catalogue

`I` = in-app, `E` = email, `E*` = email always, not user-disablable.

| Category | Event | Channels | Group key | Priority |
|---|---|---|---|---|
| **Security** | Email verification link | E* (P1-08) | — | immediate |
| | Never-verified reminder / final warning | E* (P1-16; day 3 / day 27) | — | scheduled, `high` |
| | Email confirmed | I (P1-08) | — | immediate |
| | Someone tried to sign up with your email | E* (P1-08; at most one an hour per account) | — | immediate |
| | Password reset | E* | — | immediate |
| | Password changed | I + E* (email since P1-05; in-app since P1-07) | — | immediate |
| | Email change link (to the new address) | E* (P1-10) | — | immediate |
| | Email address changed (to old + new) | E* (P1-10; the new address masked) | — | immediate |
| | Someone tried to use your email on another account / the change could not be made | E* (P1-10; to the taken address's owner and to the requester, at most one an hour each) | — | immediate |
| | New sign-in from an unrecognised device | I + E* (email since P1-05; in-app since P1-07). An account's first sign-in gets a "first sign-in" email instead, with no in-app copy (P1-08) | — | immediate |
| | 2FA enabled/disabled | I + E* | — | immediate |
| | Account suspended / banned | I + E* (email since P1-14; in-app since P1-07) | — | immediate |
| | Sanction lifted / expired | I + E (email since P1-14; in-app since P1-07) | — | immediate |
| **Ownership** | CoC account verified | I + E (P2-12; names the tag and in-game name; links to the account page `/accounts/{ulid}` from the `account` param, and the email's button reads "View your account", P2-04; notices written before P2-04 have no link) | — | immediate |
| | Your verified account was claimed by someone else | I + E* (P2-12; names the tag only, never the new holder or the in-game name; links to `/accounts/attach?tag=…`, the email's button reads "Verify it again", P2-11) | — | immediate |
| | Dispute opened against you | I + E | — | immediate |
| | Dispute response reminder (day 3, day 6) | I + E | — | scheduled |
| | Dispute decision | I + E | — | immediate |
| | Re-verification requested | I + E | — | immediate |
| | Account not found for 3 syncs | I (P2-09; once, on the sync that makes the account stale; names the tag and in-game name; says it stays verified) | — | immediate |
| | Tag released | I | — | immediate |
| **Bases** | Comment on your base | I + E (opt) | `base:{id}:comments` | batched 5 min |
| | Reply to your comment | I + E (opt) | `comment:{id}:replies` | batched 5 min |
| | Like milestone (10, 50, 100, 500, 1000) | I | `base:{id}:likes` | batched hourly |
| | Your base is trending | I | — | daily max 1 |
| | Base published (processing finished) | I | — | immediate |
| | Media processing failed | I + E (in-app since P1-07; email with P1-15, the first non-security email) | — | immediate |
| **Moderation** | Your content was hidden/removed | I + E | — | immediate |
| | Warning issued | I + E | — | immediate |
| | Report outcome (to reporter) | I | — | immediate |
| | Appeal decision | I + E | — | immediate |
| **Recruitment** (P2) | New application on your clan post | I + E (opt) | `post:{id}:applications` | batched 15 min |
| | Application accepted / declined | I + E | — | immediate |
| | New interest in your player post | I | `post:{id}:interests` | batched 15 min |
| | Post expiring in 3 days | I + E | — | scheduled |
| | Post auto-paused / auto-closed | I | — | immediate |
| | Saved-search digest (P5) | E | — | daily |
| **Marketplace** (P3) | New order request | I + E | — | immediate |
| | Order accepted / declined / cancelled | I + E | — | immediate |
| | Order delivered | I + E | — | immediate |
| | Order auto-completing in 48 h | I + E | — | scheduled |
| | New message in an order | I + E (opt, if unread 15 min) | `conv:{id}` | batched |
| | New review received | I | — | immediate |
| | Dispute opened / resolved | I + E | — | immediate |
| **Social** (P2) | New follower | I | `followers` | batched daily |
| | Someone you follow published a base | I | `follow:{user}:bases` | batched daily |
| **Staff** | New Critical report case | I + E | — | immediate |
| | SLA breach | I | — | hourly digest |
| | All CoC API keys unhealthy | E* | — | immediate |

## 3. Aggregation

Social notifications are the ones that destroy a notification centre if sent one-per-event.

**Mechanism:** each notification carries a `group_key`. When a new notification arrives with a
group key that already has an **unread** row for that user younger than the batch window, the
existing row is updated instead of a new one being created:

```
data: { type: 'base_liked', base_id, actors: [u1,u2,u3], actor_count: 12, last_actor_at }
render: "player1, player2 and 10 others liked your base 'TH16 Anti-3'"
```

Batch windows: comments 5 min, applications 15 min, likes 1 hour, follows 24 h.
Once read, the next event starts a fresh row — so a user who checks their bell sees new activity.

Implementation detail that matters: the update uses a `SELECT ... FOR UPDATE` on the grouped row, or
an atomic `jsonb` update, so concurrent likes do not lose actors.

## 4. Email

| Aspect | Decision |
|---|---|
| Provider | Postmark (best transactional deliverability) or Amazon SES (cheapest at volume). Behind Laravel's mailer, so it is an env change |
| Streams | Separate transactional and broadcast streams; digests never share a stream with password resets |
| Templates | One responsive base layout (Markdown mail), plain-text alternative for every message, brand-light — dark-themed game styling renders badly in email clients |
| Sending | Always queued on the `low` queue; never in the request cycle |
| Rate | Max 10 emails per user per day excluding security mail; a cap counter in the cache prevents notification storms |
| Unsubscribe | List-Unsubscribe header on every non-security email, linking to a signed recipient-bound unsubscribe page; no sign-in required. GET shows the page; POST disables all non-security email. Preferences remain available to the signed-in owner (§5) |
| Bounces | Webhook → mark the address `bouncing` after a hard bounce → stop sending, show an in-app banner asking the user to update their email |
| Complaints | Spam complaint → disable all non-security email for that user immediately |
| Auth | SPF, DKIM, DMARC configured before launch. DMARC starts at `p=none`, moves to `p=quarantine` after two weeks of clean reports |
| Never emailed | CoC API tokens, passwords, report evidence, another user's email address, reporter identities |

P1-15 delivery uses `SendEmailNotificationJob` on `low` (3 attempts, 60/300/900-second backoff,
60-second timeout). It locks the recipient's account, re-checks current preferences and reserves
the daily cap before calling the mailer. Days are UTC, with `platform.notifications.email_per_day`
(10) and `email_counter_ttl` (86400 seconds); a cache miss recovers the count from that day's
completed receipts. Deleted recipients are skipped. A unique `(recipient, type, event_key)`
receipt prevents replay after a completed send; the first event key is the media ULID
([07 Notifications](07-database-schema.md)). SMTP acceptance followed by a worker crash before
the receipt commits can still duplicate a delivery on retry; SMTP has no atomic transaction with
our database. Provider-specific idempotency remains a mail-provider follow-up with P0-09.

## 5. Preferences (email in P1-15; in-app and digests in Phase 5)

`notification_preferences.channel_prefs` is a `jsonb` map of category → `{in_app, email}`.
Security categories are present but locked. Defaults: everything in-app on; email on for ownership,
moderation, marketplace and recruitment decisions; email off for social.

Digest options: `none` (default), `daily`, `weekly` — a single email summarising unread activity.

Owner decision, 2026-10-01 (P1-15): bring `notification_preferences` and email controls forward.
`/settings/notifications` offers a global non-security email toggle and per-category email toggles;
Security is shown as always on. In-app controls and digest selection remain Phase 5; persist their
defaults without exposing them as editable fields. Bases email defaults on for the media-failure
notice. Future catalogue entries use these same stored preferences.

Unsubscribe sets `non_security_email_enabled=false` without changing in-app or Security settings.
This global flag suppresses future categories as well as current ones; only an explicit owner
preference update can re-enable it. Delivery re-checks both this flag and the category preference.
The signed link binds the account ULID and a hash of its current email, expires after a configured
lifetime and becomes invalid when that email changes or the account is anonymised. Its capability
authorizes only disabling non-security mail, even when the browser is signed in to another account.
GET never mutates preferences; the browser's POST retains CSRF protection and repeated submissions
are harmless. Invalid links show a generic error without exposing an address.
The link lifetime is `platform.notifications.unsubscribe_link_days` (30); the address binding is
an HMAC-SHA256 with the application key, so the URL carries no address or plain address hash.
Missing preference rows read defaults; GET does not create them. Saving email controls preserves
stored in-app and digest choices.

A category's toggle carries a hint when some of its mail is E* and the toggle cannot stop it:
Accounts reads "Takeover alerts are always sent." (P2-12). A module that is not an edge module queues
its preference-checked mail with `EmailDeliveryService::queue()`; its E* mail goes out as a Laravel
notification listing `mail` beside `InAppChannel`.

## 6. In-app UX

- Bell icon with an unread count, capped at "99+", read from a cached count keyed
  `notif:unread:{user}` with a 60-second TTL and explicit invalidation on read/write.
- Dropdown shows the 10 most recent; a full page paginates. In v1 (P1-07) the bell is a link to the
  page, the same at 375 px; the dropdown joins with P5-02.
- Mark-as-read on click; "mark all read" available.
- Grouped notifications show avatars of up to 3 actors plus a count.
- Notifications link directly to the target anchor (e.g. the specific comment).
- Deleted or hidden targets render as "this content is no longer available" rather than 404ing.
- Polling: the bell refreshes on navigation (shared prop) and via a 60-second Inertia `usePoll`
  partial reload (`only: ['unreadCount']`, `useUnreadPoll`, started and stopped on
  `visibilitychange`), **stopped while the tab is hidden**. This is the deliberate low-cost substitute for websockets; revisit with Redis + Reverb
  if real-time becomes a requirement.

## 7. Retention and volume control

- Read notifications older than 90 days are pruned nightly; unread older than 180 days too
  (`notifications:prune`, 02:15, inline chunked deletes, `--dry-run`; limits in
  `platform.notifications.*`).
- A hard cap of 500 notification rows per user; the oldest read rows are trimmed beyond that, never
  unread ones.
- Fan-out is always chunked: a job that would create more than 500 notifications splits into
  batches of 200 (relevant for follower fan-out in Phase 5 — an author with 10k followers must not
  enqueue 10k jobs at once on a database queue).
- Fan-out for followers is **pull-based, not push-based**, above 1000 followers: instead of writing
  a row per follower, write one activity row and let the follower's feed query read it. The
  threshold and the switch are implemented from day one of the follow feature, not retrofitted.

## 8. Testing

- `Notification::fake()` assertions for every catalogue entry.
- Grouping tests: N events within the window produce one row with N actors; after read, a new row.
- Preference tests: a disabled category produces no mail but still produces in-app where required.
- Security-category tests: assert they cannot be disabled.
- Volume test: 1000 likes on one base produce ≤24 notification rows in a day.
- Email content tests: no secret, no token, no third-party email address in any rendered template.
