# 16 — Notifications

## 1. Strategy

In-app first, email for anything security- or money-adjacent, aggregation for anything social.
Laravel's notification system with a database channel and a mail channel; **no broadcast channel**
in the MVP (it would require Redis + a websocket server).

Delivery model: a domain event → a queued listener → `Notifier::send()` → one `notifications` row
(+ optionally a mail job). Nothing user-facing is written synchronously in a request that the user
is waiting on.

## 2. Catalogue

`I` = in-app, `E` = email, `E*` = email always, not user-disablable.

| Category | Event | Channels | Group key | Priority |
|---|---|---|---|---|
| **Security** | Email verification link | E* | — | immediate |
| | Password reset | E* | — | immediate |
| | Password changed | I + E* | — | immediate |
| | Email address changed (to old + new) | E* | — | immediate |
| | New sign-in from an unrecognised device | I + E* | — | immediate |
| | 2FA enabled/disabled | I + E* | — | immediate |
| | Account suspended / banned | I + E* | — | immediate |
| | Sanction lifted / expired | I + E | — | immediate |
| **Ownership** | CoC account verified | I + E | — | immediate |
| | Your verified account was claimed by someone else | I + E* | — | immediate |
| | Dispute opened against you | I + E | — | immediate |
| | Dispute response reminder (day 3, day 6) | I + E | — | scheduled |
| | Dispute decision | I + E | — | immediate |
| | Re-verification requested | I + E | — | immediate |
| | Account not found for 3 syncs | I | — | batched |
| | Tag released | I | — | immediate |
| **Bases** | Comment on your base | I + E (opt) | `base:{id}:comments` | batched 5 min |
| | Reply to your comment | I + E (opt) | `comment:{id}:replies` | batched 5 min |
| | Like milestone (10, 50, 100, 500, 1000) | I | `base:{id}:likes` | batched hourly |
| | Your base is trending | I | — | daily max 1 |
| | Base published (processing finished) | I | — | immediate |
| | Media processing failed | I + E | — | immediate |
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
| Unsubscribe | One-click list-unsubscribe header on every non-security email, linking to preferences |
| Bounces | Webhook → mark the address `bouncing` after a hard bounce → stop sending, show an in-app banner asking the user to update their email |
| Complaints | Spam complaint → disable all non-security email for that user immediately |
| Auth | SPF, DKIM, DMARC configured before launch. DMARC starts at `p=none`, moves to `p=quarantine` after two weeks of clean reports |
| Never emailed | CoC API tokens, passwords, report evidence, another user's email address, reporter identities |

## 5. Preferences (Phase 5)

`notification_preferences.channel_prefs` is a `jsonb` map of category → `{in_app, email}`.
Security categories are present but locked. Defaults: everything in-app on; email on for ownership,
moderation, marketplace and recruitment decisions; email off for social.

Digest options: `none` (default), `daily`, `weekly` — a single email summarising unread activity.

## 6. In-app UX

- Bell icon with an unread count, capped at "99+", read from a cached count keyed
  `notif:unread:{user}` with a 60-second TTL and explicit invalidation on read/write.
- Dropdown shows the 10 most recent; a full page paginates.
- Mark-as-read on click; "mark all read" available.
- Grouped notifications show avatars of up to 3 actors plus a count.
- Notifications link directly to the target anchor (e.g. the specific comment).
- Deleted or hidden targets render as "this content is no longer available" rather than 404ing.
- Polling: the bell refreshes on navigation and via a 60-second `wire:poll` **only when the tab is
  visible**. This is the deliberate low-cost substitute for websockets; revisit with Redis + Reverb
  if real-time becomes a requirement.

## 7. Retention and volume control

- Read notifications older than 90 days are pruned nightly; unread older than 180 days too.
- A hard cap of 500 notification rows per user; the oldest read rows are trimmed beyond that.
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
