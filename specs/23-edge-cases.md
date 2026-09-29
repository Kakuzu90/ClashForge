# 23 — Major Edge Cases

Grouped by domain. Each has a defined behaviour; anything marked **decide** is an open question for
[24](24-risks-and-assumptions.md).

## 1. Accounts & authentication

| Case | Behaviour |
|---|---|
| User registers with an email already in use | Generic success message; an email is sent to the existing address saying someone tried to register. No enumeration |
| User never verifies their email | Can log in, cannot write. Reminder at day 3; account purged after 30 days with a final warning email |
| Email provider bounces permanently | Address marked `bouncing`, all non-security email stops, an in-app banner asks for a new address. Writes still allowed — we do not punish the user for a dead mailbox they may still be able to change |
| Password reset requested for a non-existent account | Identical response and timing; no email sent |
| Reset link used twice | Second use fails with "this link has already been used" |
| User changes email to one already registered | Rejected with a generic error; both addresses notified |
| Username released and immediately re-registered by someone else | Blocked for 90 days via `username_history`; old profile URLs redirect until then, and afterwards return 404 rather than the new person's profile |
| User deletes their account, then registers again with the same email | Allowed after the 30-day window; no data is restored |
| User requests deletion while a dispute or an open order involves them | Deletion queued but held until those resolve; the user is told why and can cancel |
| Session hijack suspected (IP/UA changes drastically) | Not auto-invalidated (mobile networks change IPs constantly); a new-device email is sent and the session is listed for manual revocation |
| Staff member loses 2FA device | Recovery codes only. No support-driven bypass — the role can be re-granted by a super admin after re-enrolment |

## 2. CoC account claiming

Primary set in [13 §9](13-claiming-workflow.md). Additional:

| Case | Behaviour |
|---|---|
| A user attaches 200 tags to farm badges | Attach rate limit (5/hour) plus an anomaly flag at 20+ accounts; unverified accounts grant nothing, so the incentive is minimal |
| Tag valid in our normaliser but rejected by the API | Trust the API. Show "this tag doesn't exist" and log the mismatch to tune the normaliser |
| Player renames in game | IGN updates on sync; the tag is the identity, so nothing else changes. Display shows the current name with no history (avoids exposing former names, which can be sensitive) |
| Account's clan is private / war log hidden | Store what the API gives; render missing fields as "not shared" rather than blank |
| Two accounts in our database have the same IGN | Fine. IGNs are not unique in game; tags are. Search shows the tag alongside every IGN |
| Verified account's owner is banned | Account hidden with the owner; tag released after 30 days |
| A verified account has been inactive for a year | Still verified. Sync drops to the frozen tier. No auto-expiry of ownership — inactivity is not abandonment |

## 3. Bases

| Case | Behaviour |
|---|---|
| Base link points to a different TH than the selected one | We cannot parse the layout contents from the link, so we cannot validate this. A community report reason (`wrong_category`) handles it, and repeat offenders lose publishing rights |
| Base link expires or is revoked in game | Nothing we can detect. Users can report a dead link; three reports auto-flag the base with a "link may be broken" notice |
| Two users publish the same layout hash | Both published; the later one is flagged for a Low-priority review; trending ranking penalises duplicate clusters so the feed does not fill with the same base |
| A user republishes their own base to game the feed | Blocked by `UNIQUE (user_id, layout_hash)`. Deleting and republishing is detected by the same hash and rate-limited |
| Video finishes processing after the base was deleted | The processing job checks the parent's existence on completion and discards the output |
| Screenshot uploaded but the user never publishes | Media expires in 24 h and is swept |
| Base published, then the author detaches the credited CoC account | Base stays; the credit chip disappears; authorship is unchanged |
| Author is banned | Bases hidden, not deleted (so an overturned appeal restores them); likes and comments preserved |
| A base goes viral and gets 50k views in an hour | View events dedupe per visitor per day; aggregation is hourly; the counter is a column. No hot-row contention because the counter update is one row per hour, not per view |
| Comment thread on a deleted base | Cascade-deleted with the base |
| Author deletes a comment that has replies | Body replaced with a tombstone; replies remain readable |
| Moderator hides a base with 200 bookmarks | Bookmarks remain; the bookmark list shows "this base is unavailable" |

## 4. Media

| Case | Behaviour |
|---|---|
| Upload completes but `complete` is never called | Row expires in 24 h; the sweeper deletes the object |
| The same file is uploaded twice | Allowed; `checksum_sha256` lets us detect it, but deduplicating across users would create a shared-object deletion problem for no real benefit at this scale |
| A 100 MB video that is 59 seconds of black frames | Passes validation. Not our problem to judge content quality; reports handle abuse |
| Video with no audio stream | Accepted; transcode outputs video-only |
| Video with 10 audio tracks or subtitles | First audio track kept, everything else dropped by the re-mux |
| Image with an enormous canvas (30000×30000, small file) | Rejected by the dimension check **before** decode — this is the decompression-bomb defence |
| Animated WebP or APNG in an image collection | Rejected; animation belongs in the video slot |
| R2 returns success but the object is not readable | Post-upload `HEAD` check catches it; the job fails and the user is asked to retry |
| Storage reconcile finds an object with no database row | Logged on the first pass, deleted on the second consecutive detection a week later — never deleted on first sight, because an in-flight upload looks exactly like an orphan |
| A user hits the 500 MB per-user soft cap | Warned at 80%, blocked at 100% with a prompt to delete old media |
| Quarantined media from a user who then deletes their account | Quarantine is retained for the full 30 days regardless; deletion of the account does not erase evidence |

## 5. CoC API

| Case | Behaviour |
|---|---|
| API returns 200 with an empty or malformed body | Treated as a failure; nothing is written; logged with the raw body |
| API adds a new field | Ignored until we map it; `raw_payload` retains it for backfill |
| API removes a field we depend on | Mapper treats missing as null; the UI renders "not available". A monitoring alert fires when a previously-populated field is null across many accounts |
| A game update adds TH18 | Works without a deploy: `th_level` has no hardcoded max, the badge ramp reads a config map (add one line), and unit lists are `jsonb` |
| Maintenance break during peak hours | Circuit opens for the announced duration; site-wide banner; everything reads from snapshots |
| Our egress IP changes without warning (VPS migration) | All keys go invalid simultaneously. Detected within 5 minutes by the health check; the rotation job creates new keys; if rotation is unavailable, an urgent alert and a documented manual runbook |
| The API key quota is exhausted by a bug | The self-imposed rate budget makes this nearly impossible; if it happens, the circuit breaker degrades the site rather than hammering the API |
| A tag returns 404 intermittently | Three consecutive failures before any user-visible change; verification status is never changed by a sync |

## 6. Recruitment

Primary set in [14 §9](14-recruitment-workflow.md). Additional:

| Case | Behaviour |
|---|---|
| A clan's recruitment post outlives the poster's platform account | Post closed on account deletion; another qualifying co-leader may create a new one |
| A player post whose account gets banned | Post closed with the ban |
| Clan changes its tag (not possible in game, but a clan is disbanded and recreated) | Treated as a new clan; the old post closes when the old tag 404s |

## 7. Moderation

| Case | Behaviour |
|---|---|
| Content is edited after being reported | The case shows the `target_snapshot` alongside the current version with an explicit "edited after reporting" warning |
| The reported content is deleted before review | The snapshot keeps the case actionable; sanctions can still be applied to the author |
| A moderator reports content and is then assigned the case | Assignment service excludes cases containing the moderator's own report |
| Two moderators open the same case | Soft lock for 30 minutes with a visible "being reviewed by X" indicator; the second action is rejected with a reload prompt |
| A user mass-reports a rival | Reporter trust score drops, report limit tightens, and coordinated reporting is itself sanctionable |
| A banned user's content is still cached at the CDN | Moderation actions purge the relevant CDN paths and delete the application cache keys immediately, not on TTL |
| An appeal reaches the moderator who made the original decision | Blocked by the assignment service; escalates to an admin if no other reviewer is available |
| A sanction expires while the user is mid-session | The status middleware re-checks per request; the user regains access on the next page load |

## 8. Marketplace (Phase 6)

Primary set in [15 §7](15-marketplace-workflow.md). Additional:

| Case | Behaviour |
|---|---|
| A seller lists a service, then the category is prohibited by a policy change | Listing paused, seller notified with an explanation and a grace period to adapt |
| A buyer claims payment was sent off-platform and the seller denies it | Recorded in the dispute; the platform explicitly does not adjudicate off-platform payments and says so in the outcome |

## 9. Platform-wide

| Case | Behaviour |
|---|---|
| Deploy during an active upload | Presigned URLs remain valid (they are signed against R2, not our app); `complete` is retried by the client |
| Deploy with a pending migration | Migrations are backward-compatible for one release; the deploy runs migrations before switching traffic |
| Clock skew between the app and Postgres | All timestamps are generated by the application in UTC; nothing relies on database `now()` for business logic |
| Daylight saving transitions | Everything stored in UTC; user timezones are a display concern; scheduled digests compute per-user local time explicitly |
| A user in a timezone where "daily" digest time never occurs (DST spring forward) | Digest falls back to the next valid hour |
| Two requests toggle the same like simultaneously | `UNIQUE (base_layout_id, user_id)` makes the duplicate insert fail; the service treats the constraint violation as "already liked" and returns the correct state |
| Counter drift after a failed transaction | The nightly reconcile repairs every denormalised counter |
| The `cache` table is truncated in production | Everything rebuilds; slower for a few minutes, no incorrect results — this is the property that justifies the caching rules |
| A queue worker is killed mid-media-job | The job returns to the queue after `retry_after`; the job is idempotent and starts over; temp files are cleaned by the boot sweep |
| Postgres failover | Connections drop; the app returns 503 from the health endpoint; workers retry; no data loss within the RPO |
| A search query returns hidden content because of a stale index | Cannot happen on Postgres FTS (same transaction). On Meilisearch later, moderation actions delete from the index synchronously **and** the result set is re-filtered against the database before rendering |
