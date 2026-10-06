# 13 — CoC Account Claiming & Ownership Workflow

The most important workflow on the platform. Every other trust signal derives from it.

## 1. Rules

1. A player tag has **at most one verified owner** at any time, platform-wide.
2. The **only automatic path** to verified ownership is the in-game API token.
3. An in-game token beats everything else — including an existing unverified claim, a pending
   dispute, and an admin's opinion.
4. Unverified claims are **advisory only**. They grant no badge, no publishing rights, no
   recruitment rights, no marketplace rights.
5. Every state change is recorded. Ownership history is never deleted, even when a user is.
6. Losing a tag never deletes the previous owner's published content.

## 2. State machine

```
                 attach tag
                     │
                     ▼
              ┌─────────────┐   token verified    ┌────────────┐
              │ unverified  │────────────────────▶│  verified  │
              └──────┬──────┘                     └─────┬──────┘
                     │                                  │
       conflict with │                     detach / ban │ dispute filed
       a verified    │                     / 30-day     │ against holder
       holder        │                     release      │
                     ▼                                  ▼
              ┌─────────────┐                     ┌────────────┐
              │  (blocked)  │                     │  disputed  │
              │  + dispute  │                     └─────┬──────┘
              └──────┬──────┘                           │
                     │ admin transfer                   │ admin decision
                     └──────────────┬───────────────────┘
                                    ▼
                          ┌──────────────────┐
                          │  verified (new)  │   or  denied → back to verified (holder)
                          └──────────────────┘

   any state ──admin──▶ suspended (fraud) ──▶ released (claimable by anyone)
   verified ──user detaches──▶ released
```

| State | Badge | Publishing rights | Background sync | Claimable by others |
|---|---|---|---|---|
| `unverified` | no | no | no | yes (verification wins) |
| `verified` | yes | yes | yes | only via dispute |
| `disputed` | shown as "under review" | yes (holder keeps rights) | yes | no new disputes |
| `suspended` | no | no | no | no |
| `released` | n/a | n/a | no | yes |

A `suspended` row takes the whole tag out of play: preview, attach and token verification refuse
it (`tag_suspended`) until staff release it (P2-03; the release action comes with the admin pages).

## 3. Happy path: attach and verify

```
1. User enters "#2PP" ──▶ normalise (uppercase, strip #, O→0, charset & length check)
2. Rate check: ≤5 distinct tags/hour (`coc-attach`; returning to the same tag is free)
3. Already attached to this user?  ──▶ "you've already added this account"
4. GET /players/{tag}
      ├─ 404  ──▶ "no player with that tag" (negative-cached 10 min)
      └─ 200  ──▶ show confirmation card: IGN, TH, trophies, clan
5. Conflict check on tag_normalized where status='verified'
      ├─ none               ──▶ create coc_accounts row (unverified) + claim row
      └─ verified holder    ──▶ §4 conflict path
6. Instruct: "In game → Settings → More Settings → API Token → Copy"
7. User pastes token
8. POST /players/{tag}/verifytoken
      ├─ invalid ──▶ claim row (failed/invalid_token) + precise retry guidance
      ├─ error   ──▶ claim row (failed/api_error) + "try again shortly"; nothing changes
      └─ ok      ──▶ §3.1
```

### 3.1 Verification success (single transaction)
The token is checked first, outside any transaction (`coc-verify`: 5 attempts/hour per user, every
attempt counts). On `ok`:
1. Lock every row of the tag with `SELECT ... FOR UPDATE`, in id order — the check in step 5 is
   advisory; this one is authoritative. Then lock the `users` rows of the verifier and of every
   holder, in id order, so two verifications by one user, or two crossing supersedes, serialise.
2. If another row now holds the tag (`verified` or `disputed`):
   - demote it to `unverified`; it keeps its `user_id`, so the previous holder still sees it and can
     verify again, and it loses its featured flag, which moves to their earliest-verified remaining
     `verified` / `disputed` account (owner decision 2026-10-05, P2-14); mark that holder's own
     `pending` claim rows `superseded` (owner decision 2026-10-02, P2-02);
   - after commit, notify the previous holder, in-app and by an email that is always sent (§8):
     *"Someone verified #TAG with an in-game API token, so it is no longer verified on your Clash
     Commons account. If that was not you, someone else can get into your game account: secure it
     in game, then verify it again with a new token."* The notice names the tag only: whoever took
     the account over chooses its in-game name. There is no support channel to point to; the
     dispute path joins the wording with P2-03 (owner decision 2026-10-02, P2-12).
3. Promote this row: `status='verified'`, `verified_at`, `verification_method='api_token'`.
4. Recount `users.verified_accounts_count` for the verifier and each previous holder; set as featured
   if the user has no featured account.
5. Write the claim row as `succeeded` and close the verifier's `pending` rows as `succeeded`.
6. Write `audit_logs` (`coc_account.verified`, `verified_user_ids` before and after).
7. Commit, then dispatch `CocAccountVerified` (with the `succeeded` claim id) → full profile sync,
   clan tracking, the verifier's notice ("#TAG (name) is now verified", §8), search indexing.
8. Close the tag's running disputes in the same transaction: the holder's own token (they may verify
   their `disputed` row) gives `resolved_denied`, anyone else's `auto_resolved` (P2-03). A tag with a
   `suspended` row takes no token: refused before the API call and again under the lock.

**Why token verification silently supersedes an existing verified holder:** possession of a current
in-game token is proof of present control of the account. Any other outcome would let a former owner
(or a thief) hold a tag hostage against the person who actually has the account.

## 4. Conflict path: the tag is already verified by someone else

The user is **not** allowed to attach. They see:

> **#2PP0LJQ is already verified by @player123.**
> If this is your account, verify it with your in-game API token — that will transfer it
> automatically.
> If you cannot get the token (e.g. you no longer have access to the device), you can open an
> ownership dispute for a human to review.

Two paths, in this order of preference:

**A. Token verification** (self-service, instant, preferred) → §3.1, automatic transfer. The user
has no row for the tag (attach was refused), so `VerifyOwnershipService::verifyTag()` checks the
token first and only then creates their row and promotes it in the same transaction; a failed
token leaves nothing but a `failed` claim row.

**B. Dispute** (manual, slow, evidence-based) → §5. The card's dispute button joins with P2-03.

The card names the holder only where their own profile would show the tag: a listed account
(not banned or pending deletion) whose profile the viewer can see, with "show connected accounts"
on. Otherwise it reads "already verified by another Clash Commons player". The name is checked
again on every view of the card, so a privacy change or a ban applies at once (owner decision
2026-10-02, P2-11).

If the existing holder is `unverified` rather than `verified`, there is no conflict at all: both
rows may coexist, and whoever verifies first wins.

## 5. Dispute workflow

Disputes exist for one realistic scenario: **the claimant genuinely owns the account but cannot
produce a token** (lost device, account recovered by Supercell support, shared family device,
previous owner attached the tag and left the platform).

```
1. Claimant opens a dispute
     - reason (≤1000 chars)
     - evidence: up to 3 images (private media), plus free text
     - one open dispute per claimant per tag
     - rate-limited: 2 open disputes per user at a time
     - refused as "not held" while the holder's account deletion is pending (P2-24, §6)
2. Dispute created (status=open); coc_accounts.status = 'disputed'
   Current holder is notified and has 7 days to respond.
3. Holder response options:
     a. Verify with an in-game token ──▶ dispute auto-closed as resolved_denied. Ends it. Instantly.
     b. Submit a counter-statement and evidence ──▶ status=awaiting admin
     c. Voluntarily release the tag ──▶ resolved_transfer
     d. No response in 7 days ──▶ status escalates; admin decides on the claimant's evidence alone,
        but non-response is NOT by itself sufficient for transfer
4. Admin review (admin+ only):
     - side-by-side evidence, both users' account history, prior disputes by either party,
       claim history for the tag, snapshot history (when did the account's clan/name change?)
     - decision: transfer / deny / request more information / suspend the tag
5. Decision executed in a transaction:
     - transfer: holder's row → unverified (or released), claimant's row → verified
       (verification_method='admin'), both notified, audit logged with the decision note
     - deny: coc_accounts back to 'verified', claimant notified with the reason
     - suspend: tag set to 'suspended' when both parties look fraudulent; neither gets it
6. moderation_actions + audit_logs rows written. Immutable.
```

As built (P2-03, owner decisions 2026-10-02):
- **Statuses:** `open` is the holder's window. `awaiting_admin` follows the holder's answer, or the
  end of the window (`coc:process-disputes`, hourly). `awaiting_claimant` / `awaiting_holder` mean
  an admin asked that party for more. The outcomes are `resolved_transfer`, `resolved_denied`,
  `resolved_suspended`, `withdrawn` and `auto_resolved`. A disputed account takes no second dispute.
- **Transfer and suspend only from `awaiting_admin`;** deny and asking can come at any time.
  - The claimant must still be in good standing (not banned, suspended or pending deletion).
  - A banned holder cannot be given a deny.
- **Release (3c)** releases the holder's row and verifies it for the claimant by `admin` at once,
  recorded as a voluntary release.
- **The holder's token (3a)** works on their own `disputed` row.
- **Withdrawals:**
  - The claimant may withdraw only while the dispute is `open`, before the holder answers, and may
    not dispute the same tag again for `reopen_cooldown_days` (30).
  - The sweep withdraws a dispute that has waited on the claimant for 30 days. Such a withdrawal
    counts toward the 2-denials bar.
- **Each party** may attach at most 3 evidence images over the whole dispute.
- **A third denial** logs `coc.dispute_false_claim` for review; the report reason joins with P3-06.
- **Moderation rows** are written for admin decisions only ([07](07-database-schema.md)
  `moderation_actions`).

As built for the admin side (P2-17, owner decisions 2026-10-05):
- **Queue** `/admin/disputes`: running disputes, the longest wait first, sliced by who it waits on
  (admins, holder, claimant) or closed; "assigned to me". No statement, evidence or note in it.
- **Review** `/admin/disputes/{ulid}`: both statements and every submission side by side, each
  party's account age, status, verified accounts, sanctions and other disputes, the tag's claim
  history and its snapshots with clan and Town Hall changes. In-game names have no history
  ([23 §2](23-edge-cases.md)), so a rename cannot be shown. The dispute's own audit trail sits below.
- **Evidence** images are signed private URLs, only for media attached to that dispute. Each review
  render that shows any writes `coc_dispute.evidence_viewed` (with the media ids) before the URLs
  are built.
- **Parties:** an admin with a stake in the tag gets a 404 on the review and does not see the
  dispute in the queue or the dashboard counts. A stake is being the claimant or the holder, a row
  of theirs on the tag, any claim attempt on it, or a side in any dispute over it (owner decision
  2026-10-06), so an admin who lost an earlier claim cannot read the holder's evidence later.
- **Rank** ([04 §2](04-roles-and-permissions.md) rule 1): deciding also needs to strictly outrank
  both parties. With an admin party the page says only a super admin can decide it; with a super
  admin party nobody can decide it in the app.
- **Decision form:** only the decisions the service would accept now are offered, the others with
  their reason; the internal note is required; transfer, suspend and deny confirm with the outcome.

### Evidence the admin weighs

| Signal | Weight | Notes |
|---|---|---|
| In-game token from either party | **Decisive** | Ends the dispute immediately |
| Email/receipt linking a Supercell ID | High | Hard to fake, easy to verify format |
| Screenshots of the account from inside the game showing settings only the owner can see | Medium | Fakeable, but combined with other signals it counts |
| Consistency with snapshot history (claimant describes changes matching our snapshots) | Medium | We hold the data; an outsider cannot |
| Account age and history on the platform | Low | Context, not proof |
| Social proof (clanmates vouching) | Low | Trivially coordinated |
| "I just know it's mine" | None | — |

**Decision bias:** in the absence of decisive evidence, the **current holder keeps the tag**. The
harm of a wrongful transfer (identity theft, reputation theft) exceeds the harm of a wrongly denied
claim (the claimant can still verify later with a token).

### Guardrails
- A claimant who files 2 disputes that are denied is barred from filing further disputes for 90
  days, and a third denied dispute is a sanctionable offence (`false_ownership` report reason).
- Admins cannot resolve a dispute in which they are a party (enforced in the service).
- All dispute evidence is private media, staff-only, and each access is audit-logged.
- Disputes auto-close as `withdrawn` after 30 days of claimant inactivity.

## 6. Detach, release and reclaim

**User detaches an account:**
- Requires password re-confirmation (sensitive action): the current password typed in the dialog,
  sharing the `password-confirm` limiter (P2-14).
- Only the owner's `unverified` or `verified` rows: a `disputed` row is given up through its dispute
  (§5 3c), and a `suspended` row stays with staff (owner decision 2026-10-05, P2-14).
- `coc_accounts.user_id → null`, `status='released'`, `verified_at` and `verification_method`
  cleared, featured flag cleared, `verified_accounts_count` recounted, the user's own `pending`
  claim rows for it `superseded`, snapshots retained, `audit_logs` written (`coc_account.released`,
  with the reason).
- The featured flag moves to the user's earliest-verified remaining `verified` / `disputed` account,
  as after a supersede, a dispute transfer, a holder's release or a suspension (owner decision
  2026-10-05, P2-14). A candidate row another transaction holds is skipped; if that leaves none,
  `RestoreFeaturedAccountJob` sets it after commit.
- After commit `CocAccountReleased`; the user gets the in-app "Tag released" notice (§8).
- Bases credited to that account keep their `user_id` (authorship) and lose the credit link.
- If the user (or anyone) attaches the tag later, the **latest released row is reused** (matched on
  `tag_normalized` + `released`) so snapshot history is continuous.

**Released tags are immediately claimable** by anyone with a token. That is correct: account sales
are prohibited but account *handovers* within families and clans happen, and the token is the truth.

**On ban:** the user's tags move to `released` after 30 days (delay so an overturned appeal can
restore them), with an audit entry. As built (P2-24): `coc:release-banned-tags` (daily 04:15, inline)
counts `coc.accounts.ban_release_days` (30) from the start of the active ban, so a lifted ban never
releases and a second ban starts the wait again; it re-checks the ban under the user's lock. It
releases `unverified` and `verified` rows and sends the in-app "Tag released" notice; a `disputed`
row waits for its dispute (§9) and goes on a later pass; a `suspended` row stays with staff.

**On account deletion:** tags are released immediately at the end of the 30-day deletion window,
inside the anonymisation transaction (P2-24): every row of the user except a `suspended` one, which
stays with staff so deleting the account is no way out of a suspension. No notice: the account's
notifications go with it. A running dispute with the user on either side holds the deletion
([23 §1](23-edge-cases.md)), and a dispute cannot be opened against a holder whose deletion is
pending: their tag is about to be released anyway.

## 7. Re-verification

- Verified accounts are re-verified **only on demand**, never automatically — background sync
  refreshes *data*, not *ownership*.
- Re-verification is required when: a dispute is opened against the holder, the account has not
  synced successfully in 90 days, or an admin requests it during a fraud investigation.
- A holder who fails a requested re-verification within 14 days drops to `unverified` (not
  `released` — they may simply be inactive).

## 8. Notifications

| Event | To | Channel |
|---|---|---|
| Verification succeeded | claimant | in-app + email |
| Your account was verified by someone else (supersede) | previous holder | in-app + **email** (security-relevant) |
| Dispute opened against you | holder | in-app + email |
| Dispute response required (reminder at day 3, day 6) | holder | in-app + email |
| An admin asks for more | the asked party | in-app + email |
| Dispute decision | both parties | in-app + email |
| Tag released (detach, ban, deletion) | owner | in-app |
| Re-verification requested | holder | in-app + email |
| Account not found for 3 consecutive syncs | owner | in-app |

As built (P2-18, owner decisions 2026-10-06):
- **Reminders** go out on day 3 and day 6 of every holder wait (`open`, and `awaiting_holder` after
  an admin asks), from the hourly `coc:process-disputes`, each once (`holder_reminders_sent`). A run
  that missed a day sends only the latest one due.
- **Endings:** an admin's transfer, deny or suspend reaches both parties, each with their outcome.
  A holder's release reaches the claimant; a claimant's withdrawal the holder; the sweep's
  withdrawal both. The holder's own token tells the claimant; the claimant's token tells nobody here
  (the verified and takeover notices already do); anyone else's token tells both.
- **Content:** the tag and what to do, never the other party or their statements. Every notice
  carries the dispute; the holder's link to their account page, and the claimant's gain a link to
  the dispute page with P2-16.
- **Outcomes** (`coc_dispute_closed`): `transferred_to_you` and `released_to_you` link the claimant
  to their account, and replace the generic "account verified" notice for that ending;
  `transferred_away` ("Verify it again"), `denied` and `denied_token` ("Verify with a token") link
  to the attach flow; `kept` and `withdrawn` link the holder to their account; `suspended`,
  `withdrawn_inactive` and `verified_by_other` have no link. An ending by someone else's token also
  sends the previous holder the takeover notice.

## 9. Edge cases

| Case | Behaviour |
|---|---|
| Two users submit valid tokens seconds apart | Row-level lock serialises them; the later one wins and supersedes; both get notifications. Real-world meaning: they are sharing the account, which we do not police, but the audit trail shows both |
| User attaches a tag that then gets renamed in game | Sync updates the IGN; the tag is immutable in-game, so ownership is unaffected |
| Player deletes their CoC account / tag returns 404 | 3 consecutive failures → `stale` display, owner notified, stays verified (tags have returned before) |
| Claimant verifies while their own dispute is open | Dispute auto-resolves as `auto_resolved`; transfer happens through the normal verification path |
| Holder is banned mid-dispute | Dispute continues; a banned holder cannot win — transfer proceeds if the claimant's evidence is adequate, otherwise the tag is suspended |
| Tag verified by a user who is later banned for fraud | Tag `released` after 30 days; any prior disputants are notified that it is claimable |
| CoC API is down when a user wants to verify | Verification is disabled with an explicit message ("Verification is paused", on the token step and the conflict card, while the shared `cocApi` prop is set; P2-10); nothing is half-written; attach can still create an `unverified` row from cached data if we have it |
| Same person, two website accounts, one tag | The second verification supersedes the first; allowed, logged, and visible to admins as a duplicate-account signal |
| Verified account's user deletes their website account | Tag `released` at the end of the deletion window |
| Dispute evidence contains a real-world ID document | Moderator policy: do not accept, delete the media, instruct the claimant to use a token or an in-game screenshot. We do not want to hold identity documents |
