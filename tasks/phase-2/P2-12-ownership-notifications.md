---
id: P2-12
title: Notify the owner when a CoC account is verified, and the previous holder when it is taken over
phase: 2
status: done
depends_on: [P2-02]
---

# Ownership notifications

## Spec refs
- Core: specs/13 §8 (first two rows), §3.1 step 2 (the takeover wording), §9 (two valid tokens seconds apart: both are notified; same person, two website accounts); specs/16 §1 (delivery model, render on read), §2 (Ownership: "CoC account verified" I + E, "Your verified account was claimed by someone else" I + E*), §4 (email cap, receipts, never emailed), §5 (preferences), §8 (testing)
- Plus: specs/05 §2 (PlayerAccounts may use Notifications; `CocAccountVerified` / `CocAccountOwnershipTransferred` → Notifications); specs/19 (internal `Jobs`/`Listeners` stay in their module); specs/11 (game names are third-party text in email)
- FR: FR-COC-5, FR-COC-6 (the outcomes these notices report); no FR of their own
- Edge cases: specs/13 §9 rows above; specs/23 has no ownership-notification row

## Scope
- **Domain (Notifications):**
  - `NotificationType::CocAccountVerified` and `CocAccountTakenOver`, category Ownership.
  - Each renders on read from `{tag, name, method}`. A missing parameter still renders.
  - The takeover copy is Open question 1's wording. No link for now (Open question 2).
  - A public `EmailDeliveryService::queue(userId, type, eventKey, params)` dispatches `SendEmailNotificationJob`, since PlayerAccounts may not reach another module's `Jobs` (specs/19).
- **Domain (PlayerAccounts):** the queued listener `SendOwnershipNotice` (`high`) handles both events.
  - **`CocAccountVerified`:** the verifier gets an in-app row through `Notifier`, plus the email through `EmailDeliveryService::queue`. That email honours preferences, the daily cap and the receipt.
    - The event gains `claimId`, the `succeeded` claim row, which is the email's event key. A retried job sends once, and a later re-verification of the same tag gets its own email.
  - **`CocAccountOwnershipTransferred`:** the previous holder gets `CocAccountTakenOverNotification`, which lists `mail` beside `InAppChannel` (E*, like the sanction notices, specs/05 §2). That email is not subject to preferences or the cap.
  - **Skipped:** a deleted or anonymised recipient, and an account row that no longer exists.
  - **Never sent:** the verifier's identity, a token, or an email address (16 §4).
- **Policy + Form Request:** none. No new route or write path, so specs/04 is unchanged.
- **UI:** `EmailCategoryData` gains a nullable `hint`. `/settings/notifications` shows "Takeover alerts are always sent." under the Accounts toggle (Open question 3). Regenerate the TS types.
- **Config keys added:** none.

## Out of scope
- Links to the attach page and the account page, which arrive with P2-11 and P2-04. The read-time render means old rows pick them up then.
- Dispute notices, and takeover copy for a dispute decision (`method` = `admin`). These come with P2-03; the notice stores `method` now.
- "Tag released" notices (P2-14); "account not found for 3 syncs" (P2-09); in-app preferences and the bell dropdown (P5-02).

## Acceptance criteria
- **Functional:** a successful verification gives the verifier one in-app notice and one email. A supersede also gives the previous holder one in-app notice and one email. When two valid tokens arrive seconds apart, both users are notified (13 §9).
- **Preferences:** turning off Accounts email, or all non-security email, stops the "verified" email but never the takeover email (16 §2 E*). Both in-app notices are always written.
- **Authorization:** nothing new. Each notice goes only to the user named in the event.
- **Edge cases:** a double submit dispatches no event, so no second notice. The same person on two website accounts gets both notices, one on each account.
- **States:** the "no longer available" path for unknown types is unchanged.

## Tests
- **Feature:**
  - Each event gives the right recipient the right channels (`Notification::fake()` and `Queue::fake()`).
  - The rendered titles and bodies, including old rows with missing params.
  - Preferences off → in-app only for "verified"; the takeover email is still sent.
  - Daily cap, and a receipt that blocks a second send for the same claim.
  - The listener sends nothing before commit.
- **Security:**
  - A game name containing Markdown or HTML (`[x](https://evil)`, `<b>`) arrives as plain text in both emails and in-app.
  - No token, verifier username or email address in any row, mail or job payload.
- **Unit:** `NotificationType` category and colour for the new cases.
- **Feature (settings):** the Accounts category carries its hint and the others carry none (`assertInertia`).

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Synced into this file's Scope.
1. **"Contact support":** there is no support channel, so the takeover copy drops it: "Someone verified #TAG (name) with an in-game API token, so it is no longer verified on your Clash Commons account. If that was not you, someone else can get into your game account: secure it in game, then verify it again with a new token." The dispute path is added with P2-03. synced → specs/13 §3.1.
2. **Links:** no link and no email button for now; P2-11 adds them (read-time render reaches old rows).
3. **Settings page:** one hint line under the Accounts email toggle, "Takeover alerts are always sent.", through a new `hint` on `EmailCategoryData`. synced → specs/16 §5.

### Decisions and divergences (implement, 2026-10-02)
1. One source for the words: `NotificationType::render()` writes both in-app notices, and both emails reuse it (the takeover email through `toMail`). Titles: "Your Clash of Clans account is verified" and "Someone else verified one of your accounts". The verified notice reads "#TAG (name)", the tag alone, or a fallback when params are missing; the takeover notice names the tag only (review fix below). synced → specs/13 §3.1, specs/16 §2.
2. The email button label stays the fixed "View upload settings": neither new notice has a link, so it is never shown for them. Moved to P2-11, where the first link appears (board row updated; spec review). No spec change.
3. A retried listener writes the in-app row again, like `WriteInAppNotice`; the verified email sends once, through its receipt keyed by the claim id. `CocAccountVerified` gains `claimId`. synced → specs/05 §2, specs/13 §3.1.
4. Game names in email rely on the app-wide `Markdown::withSecuredEncoding()` (already on); a Markdown link or HTML in a name stays text, which is tested. No spec change.
5. `EmailDeliveryService::queue()` is the public way for another module to send preference-checked mail. synced → specs/05 §2, specs/16 §5.
6. The notification centre gains an "Accounts" tab now that Ownership has types. On `/settings/notifications`, the Accounts toggle carries the hint, and the footnote names what Accounts covers. synced → specs/16 §5.

### Review fixes (verify, 2026-10-02)
- Security (low): whoever takes an account over can rename it in game first, and the takeover email is always sent, so the in-game name could carry their text ("help: evil.gg") into our security notice. The takeover notice now stores and shows the tag only, which changes the approved wording from "#TAG (name)" to "#TAG". Control and direction-override characters (`\p{Cc}`, `\p{Cf}`) are stripped from names when they are rendered. Tested. synced → specs/13 §3.1, specs/16 §2.
- Spec (low): the Scope still listed the email button label that decision 2 moved to P2-11. The bullet is removed, and the P2-11 board row now carries it.
- Spec (low): tests added for the daily cap on the verified email, the hostile name in-app, the takeover email being delivered with all non-security email off (real mailer), and the verifier's identity absent from the jobs the listener queues.
- antislop audit-028: no findings.

- scripts/check.sh: all green (sqlite + postgres).
