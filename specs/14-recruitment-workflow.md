# 14 — Recruitment Workflow

Phase 4. Two directions, one data model, one search surface.

## 1. Why one table for both directions

`recruitment_posts` carries a `type` discriminator (`player` | `clan`). The alternative — two tables
— duplicates ~15 shared fields (TH, trophies, country, languages, activity, war preference, status,
expiry, bump) and forces the combined browse page into a UNION. One table with a `CHECK`
constraint per type keeps search, moderation and expiry as single code paths.

## 2. Looking For Clan (player → clans)

### Creating a post
```
Preconditions: verified email, active status, ≥1 verified CoC account,
               no existing open post for the chosen account
1. User picks a verified CoC account (dropdown shows IGN, TH, trophies)
2. Auto-filled from the account, read-only:  TH level, trophies, league, current clan, war stars
   (this is the whole point — recruitment posts cannot lie)
3. User fills:   country, languages (≤3), activity level, war preference, CWL interest,
                 play style, preferred clan level, preferred league, note (≤500)
4. Validation + moderation pre-screen (prohibited terms, contact-info patterns)
5. Post created: status=open, expires_at = now + 30 days, bumped_at = now
```

**Constraint:** `UNIQUE (coc_account_id) WHERE type='player' AND status='open'` — one open post per
account, multiple accounts allowed. A player with four TH levels can legitimately look for four
different clans.

### Auto-maintenance
- **Auto-close on join:** the account sync detects a clan change; if the account is now in a clan
  and the post did not specify "looking anyway", the post auto-closes with a notification and a
  one-click reopen.
- **Stat refresh:** the displayed TH/trophies always render from the live account row, not from a
  snapshot copied at post time. A post can never show stale, inflated stats.
- **Expiry:** 30 days → `expired`, with a renewal email at day 27 and one-click renewal.
- **Bump:** once every 48 hours, sets `bumped_at` (the default sort key). Rate-limited so the
  listing is not a bump war.

## 3. Clan Recruitment (clan → players)

### Creating a post — authorisation is the hard part
```
Preconditions: verified email, active status
1. User picks one of their verified CoC accounts
2. We read that account's synced clan_tag and clan_role
3. AUTHORISATION: clan_role ∈ {leader, coLeader}   ← from the API, not self-declared
      ├─ fails ──▶ "only clan leaders and co-leaders can post for a clan.
      │             Refresh your account if you were recently promoted."
      └─ passes ─▶ continue
4. Clan record ensured (fetch /clans/{tag}, create or refresh `clans` row, tracked_reason='recruitment')
5. Auto-filled read-only from the clan: name, badge, level, members, war league, capital hall,
   war frequency, war win streak, clan points, location, required TH/trophies
6. User fills: description (≤2000), required activity, language(s), timezone focus,
   what the clan offers, recruitment status
7. Post created: status=open, expires_at = now + 30 days
```

**Constraint:** `UNIQUE (clan_id) WHERE type='clan' AND status='open'` — one active post per clan,
regardless of how many co-leaders are members. A second co-leader attempting to post is offered
co-management of the existing post instead.

**Ongoing authorisation:** a nightly job re-checks that the post author still holds a leadership role
in that clan. Lost role → post auto-paused, author notified, any other qualifying co-leader on the
platform offered ownership of the post.

### Auto-maintenance
- **Full clan:** clan sync reports `members_count = 50` → post auto-paused ("your clan is full"),
  auto-resumed when a slot opens.
- **Clan data refresh:** hourly for clans with an open recruitment post (`tracked_reason='recruitment'`
  makes them a hot-tier sync target).
- **Clan disbanded / 404:** post closed, author notified.
- Expiry and bump as for player posts.

## 4. Applications and interests

Two symmetric objects, deliberately separate tables because the actors and permissions differ.

### Player applies to a clan post (`recruitment_applications`)
```
1. Player picks which verified account they are applying with
2. Message (≤500). Contact details (Discord etc.) are allowed ONLY here, not in public post bodies
3. Checks: one pending application per player per post; ≤10 pending applications total;
           ≤20 applications/day; account meets nothing automatically — requirements are
           advisory, not enforced (a TH14 may apply to a TH15 clan and explain why)
4. Application created (pending) → recruiter notified (in-app + optional email)
5. Recruiter sees the applicant's full verified stat card, not a self-description
6. Recruiter accepts / declines (optional short note) → applicant notified
7. Accept also reveals the clan's join instructions / Discord link to the applicant
8. Auto-expire after 14 days without a response; applicant may withdraw any time
```

### Recruiter shows interest in a player post (`recruitment_interests`)
Mirror image: recruiter picks the clan (must still hold a leadership role), writes a pitch, and the
player accepts or declines. One pending interest per clan per post. Interests count against a
per-clan daily limit (20/day) — this is the surface most likely to be spammed.

### Application states
`pending → accepted | declined | withdrawn | expired`. Terminal states are final; re-applying to the
same post requires 7 days to pass after a decline.

## 5. Discovery

### Clan browse (for players)
Filters: required TH (≤ my TH), clan level, war frequency, CWL league, capital hall level, language,
country, members available, clan type, "has open post".
Sorts: recently bumped (default), clan level, war win streak, members available, newest.

### Player browse (for recruiters)
Filters: TH level range, trophy range, activity level, war preference, CWL interest, play style,
language, country, "verified only" (always on — unverified accounts cannot post).
Sorts: recently bumped (default), TH desc, trophies desc, newest.

### Saved searches & alerts (Phase 5)
A recruiter saves a filter set and receives a daily digest of new matching player posts. The single
most requested feature in every recruitment tool; deliberately deferred until the notification
digest infrastructure exists.

## 6. Matching quality (no ML, just sensible ranking)

Results are ranked by a transparent score, not a black box:

```
score =  freshness(bumped_at)            (decay over 14 days, weight 40)
       + requirement_fit                  (TH within range, trophies within range, weight 25)
       + language_overlap                 (weight 15)
       + country_or_timezone_match        (weight 10)
       + responsiveness(author)           (median response time to applications, weight 10)
       - spam_penalty                     (posts with many declined applications)
```

`responsiveness` is the anti-ghosting mechanism: clans that never answer applications sink. It is
computed nightly from `recruitment_applications` response times and stored on the post.

## 7. Anti-abuse

| Risk | Control |
|---|---|
| Fake clan posts by non-leaders | API-verified `clan_role`, re-checked nightly |
| Inflated player stats | All stats auto-filled from verified accounts; no free-text stat fields |
| Recruitment spam to many players | Per-clan daily interest cap, per-user application cap, bump cooldown |
| Contact-info harvesting | Public post bodies are screened for contact patterns; contact details belong in applications only |
| Off-platform scams ("pay me to join our CWL clan") | Prohibited-term screening, `scam` report reason, clear policy that paid clan slots are not allowed |
| Ghost posts | 30-day expiry, auto-pause when full, responsiveness penalty |
| Account-trading disguised as recruitment | Prohibited-term lexicon includes account-handover phrasing; Critical report reason |
| Duplicate posts across accounts | One open post per account and per clan, enforced by partial unique indexes |

## 8. Notifications

| Event | To | Channel |
|---|---|---|
| New application on your clan post | recruiter (all qualifying co-leaders on the platform) | in-app + email (optional) |
| Application accepted / declined | applicant | in-app + email |
| New interest in your player post | player | in-app |
| Interest accepted / declined | recruiter | in-app |
| Post expiring in 3 days | author | in-app + email |
| Post auto-paused (clan full / role lost) | author | in-app |
| Post auto-closed (you joined a clan) | player | in-app |
| Daily digest of matching posts (P5) | subscribers | email |

## 9. Edge cases

| Case | Behaviour |
|---|---|
| Player joins a clan while applications are pending | Other applications auto-withdraw with a note; the player is asked to confirm |
| Recruiter loses co-leader role with pending applications | Post pauses; pending applications stay and transfer with post ownership |
| Clan hits 50 members with 8 pending applications | Post pauses; applicants notified "the clan is currently full"; applications stay pending |
| Applicant's account is transferred in a dispute | Application auto-withdraws; the new owner is not bound by it |
| Player post's account becomes unverified | Post auto-pauses until re-verified |
| Clan tag changes hands in game (leadership transfer) | Our role check follows the API; the old leader loses posting rights at the next nightly check |
| Two co-leaders both try to post | Second is offered co-management of the existing post |
| Clan is 404 after being disbanded | Post closed, author notified, `clans` row retained for history |
| Player applies with a TH far below requirements | Allowed — requirements are advisory; the UI shows a "below stated requirements" hint to both sides |
| Recruiter mass-declines to farm responsiveness | Responsiveness measures *response time*, not acceptance rate, precisely to avoid rewarding gatekeeping |
