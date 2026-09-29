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

## 3. Happy path: attach and verify

```
1. User enters "#2PP" ──▶ normalise (uppercase, strip #, O→0, charset & length check)
2. Rate check: ≤5 attach attempts/hour
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
1. Re-check the conflict inside the transaction with `SELECT ... FOR UPDATE` on the tag —
   the check in step 5 is advisory; this one is authoritative.
2. If another row now holds `verified` for this tag:
   - demote it to `unverified`, detach its `user_id` reference to a `previous_user_id` audit field,
     mark its open claim rows `superseded`;
   - notify the previous holder: *"Someone verified ownership of #TAG with an in-game token. If this
     was not you, your account may be compromised — secure it and contact support."*
3. Promote this row: `status='verified'`, `verified_at`, `verification_method='api_token'`.
4. Increment `users.verified_accounts_count`; set as featured if it is the user's first.
5. Write the claim row as `succeeded`.
6. Write `audit_logs` (`coc_account.verified`, before/after user ids).
7. Commit, then dispatch `CocAccountVerified` → full profile sync, clan tracking, notification,
   search indexing.
8. Auto-resolve any open dispute where this user is the claimant (`auto_resolved`).

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

**A. Token verification** (self-service, instant, preferred) → §3.1, automatic transfer.

**B. Dispute** (manual, slow, evidence-based) → §5.

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
- Requires password re-confirmation (sensitive action).
- `coc_accounts.user_id → null`, `status='released'`, featured flag cleared, `verified_accounts_count`
  decremented, snapshots retained, `audit_logs` written.
- Bases credited to that account keep their `user_id` (authorship) and lose the credit link.
- If the user re-attaches later, the **same row is reused** (matched on `tag_normalized` +
  `released`) so snapshot history is continuous.

**Released tags are immediately claimable** by anyone with a token. That is correct: account sales
are prohibited but account *handovers* within families and clans happen, and the token is the truth.

**On ban:** the user's tags move to `released` after 30 days (delay so an overturned appeal can
restore them), with an audit entry.

**On account deletion:** tags are released immediately at the end of the 30-day deletion window.

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
| Dispute decision | both parties | in-app + email |
| Tag released (detach, ban, deletion) | owner | in-app |
| Re-verification requested | holder | in-app + email |
| Account not found for 3 consecutive syncs | owner | in-app |

## 9. Edge cases

| Case | Behaviour |
|---|---|
| Two users submit valid tokens seconds apart | Row-level lock serialises them; the later one wins and supersedes; both get notifications. Real-world meaning: they are sharing the account, which we do not police, but the audit trail shows both |
| User attaches a tag that then gets renamed in game | Sync updates the IGN; the tag is immutable in-game, so ownership is unaffected |
| Player deletes their CoC account / tag returns 404 | 3 consecutive failures → `stale` display, owner notified, stays verified (tags have returned before) |
| Claimant verifies while their own dispute is open | Dispute auto-resolves as `auto_resolved`; transfer happens through the normal verification path |
| Holder is banned mid-dispute | Dispute continues; a banned holder cannot win — transfer proceeds if the claimant's evidence is adequate, otherwise the tag is suspended |
| Tag verified by a user who is later banned for fraud | Tag `released` after 30 days; any prior disputants are notified that it is claimable |
| CoC API is down when a user wants to verify | Verification is disabled with an explicit message; nothing is half-written; attach can still create an `unverified` row from cached data if we have it |
| Same person, two website accounts, one tag | The second verification supersedes the first; allowed, logged, and visible to admins as a duplicate-account signal |
| Verified account's user deletes their website account | Tag `released` at the end of the deletion window |
| Dispute evidence contains a real-world ID document | Moderator policy: do not accept, delete the media, instruct the claimant to use a token or an in-game screenshot. We do not want to hold identity documents |
