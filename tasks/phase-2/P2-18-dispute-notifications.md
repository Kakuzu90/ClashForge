---
id: P2-18
title: Notify both parties of a dispute: opened, reminders, requests for more, and the outcome
phase: 2
status: done
depends_on: [P2-03]
---

# Dispute notifications

## Spec refs
- Core: specs/16 §2 Ownership rows (dispute opened against you: I + E; response reminder day 3 and day 6: I + E, scheduled; decision: I + E), §1 (event → queued listener → `Notifier`), §4 (preference-checked email through `EmailDeliveryService::queue()`, daily cap, receipts, never another user's email); specs/13 §8 (who is told), §5 (holder's 7-day window, statuses, outcomes)
- Plus: specs/05 §2 events (`CocAccountDisputeOpened`, `CocAccountDisputeInfoRequested`, `CocAccountDisputeClosed`: "opened to the holder, the request to the asked party, the outcome to both"; the token-takeover notice skipped for an admin transfer); specs/16 §5 (Ownership email category, default on); specs/20 §3 (`coc:process-disputes`, hourly); specs/07 `coc_account_disputes`, `notifications`
- FR: FR-NOTIF-2 (dispute opened / resolved), FR-COC-8
- Edge cases: specs/13 §9 (claimant verifies while their dispute is open; holder banned mid-dispute); specs/23 §1 (deleted recipients skipped)

## Scope
- **Notification types** (`NotificationType`, category Ownership, rendered at read time): `coc_dispute_opened`, `coc_dispute_reminder` (days left), `coc_dispute_info_requested` (Open question 2), `coc_dispute_closed` (outcome per recipient, Open question 3). Params carry the tag and the dispute ULID only (Open question 4).
- **Listener** `SendDisputeNotice` (queued, `high`), subscribed to the three dispute events: `Notifier::send()` for in-app, `EmailDeliveryService::queue()` for email with event keys `{dispute}:{kind}`, so a retry never sends twice.
- **Reminders** (scheduled): `coc:process-disputes` also sends the day-3 and day-6 reminders to a holder whose answer is due. Each reminder is sent once (Open question 1).
- **Migration** (Open question 1): `coc_account_disputes.holder_reminders_sent` smallint default 0, reset whenever a new holder wait starts.
- **Copy** in `NotificationType::render()` and the shared email template: what happened, what to do, and by when. It never names the other party.
- Config: `coc.disputes.reminder_days` ([3, 6]).

## Out of scope
- The dispute pages for the parties, and links to them (P2-16 adds the link through the stored dispute ULID); admin-side notices (staff digest, P3-06)
- Re-verification requests (13 §7, later row)

## Acceptance criteria
- Functional: 16 §2 rows; each event reaches the right party once, in-app and by email (preferences and the daily cap apply); reminders on day 3 and day 6 of the holder's window only while the dispute waits on them.
- Authorization: no route; the recipient is always a party; a deleted or anonymised recipient is skipped.
- Edge cases: a token that ends the dispute does not double up with the takeover / verified notices (Open question 3); a dispute closed before a reminder is due sends none; a sweep rerun sends nothing twice.
- States: notifications render with and without the dispute param (old rows).

## Tests
- Feature: each event → recipients, types and params; email queued with its event key; reminders at the day edges (time frozen), once each, reset on a new holder wait; none after closing; outcome mapping per close reason.
- Security: no other party's username, email or statement in params, in-app text or email; claimant never sees holder evidence.
- Unit: `render()` for each type and outcome, with missing params.

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended); specs synced at implement → Finish.
1. **Reminders: which waits, and how to send each once?** 16 §2 names reminders on day 3 and 6 for the holder. The holder also waits when an admin asks them for more (`awaiting_holder`), and the sweep escalates both after 7 days. Recommended: remind in `open` and `awaiting_holder`, counted from `awaiting_since`. A `holder_reminders_sent` column (reset when a new holder wait starts) keeps each reminder to one send. No reminders for the claimant's 30-day wait (not in the specs).
2. **A notice when an admin asks for more?** 05 §2 lists "the request to the asked party", but 16 §2 has no row for it. Recommended: yes, `coc_dispute_info_requested`, I + E in Ownership, to whichever party was asked. 16 §2 would gain the row.
3. **Who hears about each ending?** "Decision to both parties" covers admin decisions. For the other endings, recommended:
   - Holder's release (3c): the claimant only, since the holder did it.
   - Claimant withdraws: the holder only.
   - Sweep withdraws after 30 days: both.
   - The holder's own token (`resolved_denied`): the claimant only.
   - The claimant's token (`auto_resolved`): nobody. The holder already gets the takeover notice and the claimant the verified one.
   - Someone else's token: both.
   - Admin transfer, deny or suspend: both, with the outcome. These replace the takeover notice, which is already skipped for `admin`.
4. **What do the notices reveal?** Recommended: the tag and what to do, never the other party's username or statements (the holder knows only that someone claims the account, as with the takeover notice). The holder's notices link to their account page, which shows "Ownership is under review". The claimant's carry the dispute ULID and get their link with P2-16.

### Decisions and divergences (implement, 2026-10-06)
1. Events:
   - `CocAccountDisputeClosed` gains `closedBy` and `by`, from `DisputeLedger::close`, so the listener can tell the endings apart.
   - The sweep dispatches the new `CocAccountDisputeReminderDue` (`number`, `daysLeft`) after commit.
   - `SendDisputeNotice` (queued, `high`) subscribes to all four events. Each send writes the in-app row and queues the preference-checked email.
   synced → specs/05 §2.
2. Reminders:
   - `coc:process-disputes` reminds holders in `open` and `awaiting_holder` on each of `coc.disputes.reminder_days` ([3, 6]) before the window ends.
   - `holder_reminders_sent` is updated under the dispute's lock, and `ask()` resets it to 0. A run that missed a day sends only the latest reminder due.
   - `sweep()` also returns `reminded`, and the command prints it.
   synced → specs/07, specs/13 §8, specs/16 §2, specs/20 §3.
3. Types (Ownership category):
   - `coc_dispute_opened`, `coc_dispute_reminder`, `coc_dispute_info_requested` and `coc_dispute_closed`, whose `outcome` param is one of: `transferred_to_you`, `released_to_you`, `transferred_away`, `kept`, `denied`, `denied_token`, `suspended`, `withdrawn`, `withdrawn_inactive`, `verified_by_other`.
   - Params: `tag`, `dispute`, `days` where there is a deadline, and `account` for links. The holder's notices link to their account page. A claimant who now holds the tag links to their new row. Losing outcomes link to the attach flow ("Verify it again").
   synced → specs/16 §2, specs/13 §8.
4. Email event keys:
   - `{dispute}:opened` and `{dispute}:closed`.
   - `{dispute}:info:{awaiting_since}`.
   - `{dispute}:reminder:{awaiting_since}:{n}`.
   Each new wait therefore gets its own email, and a retry never sends one twice. No spec change.

### Review fixes (verify, 2026-10-06)
- antislop audit-038: the one low finding was approved by the owner (2026-10-06) and fixed: the label reads "More info needed".
- Spec (medium): the 13 §8 "As built" block had split the notifications table. It now sits below the table.
- Spec (low): the outcomes, their links and params are now in 13 §8. Losing outcomes for a claimant who never held the tag read "Verify with a token", not "Verify it again", since "again" would be wrong. synced → specs/13 §8.
- Spec (low): on an admin transfer or a holder's release the claimant got both "account verified" and the dispute outcome. `SendOwnershipNotice::handleVerified` now skips claims with method `admin` or `dispute`, so the outcome notice stands alone. synced → specs/16 §2, specs/13 §8.
- Spec (low): the email keys came from the dispute row when the job ran. `CocAccountDisputeReminderDue` and `CocAccountDisputeInfoRequested` now carry `awaitingSince`, and a late reminder for a wait that has ended is dropped. `CocAccountDisputeClosed::$closedBy` is required.
- Spec (low): tests added for no reminder after closing, every email event key, a retried event sending one email, the Ownership opt-out, and the content check across every ending.
- Spec (trivial): the specs/20 schedule list is back in order.
- Security (low): the stale reminder and the default `closedBy` were fixed with the spec findings above.
- Security (low): a retried event could write a second in-app notice. The new `Notifier::sendOnce` stores the event key in the params and writes nothing when a row with it exists, checked under the account lock (the listener may not read Notifications' models). Tested. synced → specs/05 §2.
- Security (low), accepted: a holder's old notice links to the account page of the row they released, which the claimant then reuses. That page never names its owner, and it follows the new owner's privacy settings (`CocAccountPolicy::view`), so it reveals nothing beyond the game account the holder already knows.
- Security (low), handed on: open/withdraw cycles could flood a holder with notices once the routes exist. Added to P2-16's board row: a withdraw throttle, and quick withdrawals counted toward the bar or kept from notifying.
