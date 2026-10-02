---
id: P2-11
title: Build the attach account flow (tag, confirmation, token, success) on the P2-02 services
phase: 2
status: done
depends_on: [P2-02, P2-12]
---

# Attach flow UI

## Spec refs
- Core: specs/18 §6 "Attach account flow" (three steps, error states), §4 (Toast `reward`, Alert, Card, Empty state, Progress); specs/13 §3 (steps 1–8), §4 (conflict card, path A), §9 (API down while verifying); specs/09 §9 (token flow, token never stored)
- Plus: specs/04 §2–4 (attach and verify own; `coc-attach`, `coc-verify`); specs/11 (token as a secret in request handling, logs and error tracking); specs/19 §4 (`/accounts/attach`, `routes/web/accounts.php`); specs/18 §6 "Player profile" (own empty state); specs/16 §2 (takeover notice link, from P2-12)
- FR: FR-COC-1, FR-COC-2, FR-COC-5, FR-COC-6
- Edge cases: specs/23 §2 (tag valid here but 404 upstream: "this tag doesn't exist"); specs/13 §9 (API down: verification disabled with a message, nothing half-written)

## Scope
- **Routes** (`routes/web/accounts.php`, `auth` + `account.active`, writes behind `throttle:global-write`). The services keep their own limiters.
  - `GET /accounts/attach` (step 1; `?tag=` prefills the field)
  - `POST /accounts/attach/preview`: `preview()`, back with the result flashed
  - `POST /accounts/attach`: `attach()`, then to step 2
  - `GET /accounts/{ulid}/verify` (step 2, the user's own unverified row, else 404)
  - `POST /accounts/{ulid}/verify`: `verify()`
  - `POST /accounts/attach/verify-tag`: the conflict card, `verifyTag()`
  - `GET /accounts/{ulid}/verified` (step 3, own verified row)
- **Form Requests:** the tag uses `CocTagFieldRules`. The token is required, a string, `max:64`, and trimmed.
- **Token secrecy:**
  - The field never goes back into the session or a validation redirect (`dontFlash`).
  - The Sentry scrubber and the request logs drop it.
  - It is never a prop.
- **Pages:**
  - `Accounts/Attach`: step 1 with a confirmation card, plus the conflict card with its own token field.
  - `Accounts/Verify` (step 2): numbered in-game text steps with our own icons, and the paste field (Open question 3).
  - `Accounts/Verified` (step 3).
  - A three-step progress indicator. A new `UiSteps` goes into `/dev/components`.
- **Error states** (18 §6, 13 §3):
  - not found
  - already attached by you (link to step 2)
  - verified by someone else (the conflict card)
  - invalid token ("tokens expire in a few minutes, copy a fresh one")
  - API unavailable (retry later, with the wait when known; the tag stays in the field)
  - rate limited (the wait)
  - stale preview ("data from {time}")
- **Who can attach:** a user the policy refuses (email not confirmed) sees why, with a link to confirm. They don't get a 403. The decision comes from a `can.attach` flag.
- **Success:** a `reward` toast on the first verified account only (18 §4 limits it to genuine achievements). "This is now your featured account" when `featured`, and no switch prompt (P2-14; Open question 4). Links: attach another, your profile.
- **Profile:** the own profile's Accounts tab, when the user has no rows, gets an "Attach an account" CTA (18 §6, from P1-04). Otherwise it shows a plain list of their rows (name, tag, status, a "Verify" link on unverified rows), own profile only, through a new `AccountReadModel::ownAccounts()` (Open question 2).
- **Holder privacy:** `AttachAccountService` names the holder only when the viewer can see their profile and `show_coc_accounts` is on, else "another Clash Commons player" (Open question 1).
- **Notice link (from P2-12):** the takeover notice links to `/accounts/attach?tag=#TAG`, and its email gets a "Verify it again" button.
- **Config keys:** none.

## Out of scope
- The account page `/accounts/{ulid}`, PlayerCard, and accounts listed on profiles (P2-04). The verified notice's link and the non-security email's button label move there with it.
- The dispute path on the conflict card (P2-03, which adds its button). Featured switching and detach (P2-14). The API status banner (P2-10).

## Acceptance criteria
- **Functional:** the FR ids above, end to end through the existing services.
- **Authorization:**
  - Guests are sent to sign in.
  - Unconfirmed emails see the reason.
  - Suspended and banned accounts are stopped by `account.active`.
  - Another user's ulid on steps 2 and 3 gives a 404.
- **Edge cases:** the specs/23 and 13 §9 rows above. The token never appears in the session, flash, props, logs or Sentry.
- **States:** every error state above, a loading button on every submit, and a usable layout at 375 px.

## Tests
- **Feature (`assertInertia`):** each page and prop shape. Each outcome through the routes: preview (all seven), attach, verify ok / invalid / unavailable / rate limited, verifyTag from the conflict card. The prefill, the first-account reward flag, and the profile CTA.
- **Authorization:** guest, unconfirmed email, suspended, restricted (allowed), IDOR on steps 2 and 3.
- **Validation:** bad tag, empty or long token.
- **Security:** a token absent after a failed and a successful submit (session, old input, logs). Holder privacy: a private profile, `members` for a guest is n/a (signed in), `show_coc_accounts` off.
- **Profile list:** own rows only, never on another user's profile; the CTA only with no rows.
- **Vitest:** the step indicator, and the conflict card's two-field form.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Synced into this file's Scope and tasks/BOARD.md (P2-03, P2-04, P2-14).
1. **Naming the holder:** named only when the viewer can see their profile (`PrivacyPolicyResolver::canView`) and `show_coc_accounts` is on; otherwise "another Clash Commons player". `AttachAccountService` changes. synced → specs/13 §4.
2. **Own accounts on the profile:** the empty state and CTA only when the user has no rows; otherwise a plain own-only list (name, tag, status, "Verify" link on unverified rows) until P2-04's PlayerCards replace it.
3. **Token steps:** numbered text steps with our own icons; original illustrations are a later design item. synced → specs/18 §6.
4. **Featured prompt:** "This is now your featured account" when it became featured; the switch prompt joins with P2-14.

### Decisions and divergences (implement, 2026-10-02)
1. **Routes:** the step pages are `/accounts/{ulid}/verify` and `/accounts/{ulid}/verified` (names `accounts.verify`, `accounts.verified`); the writes are `accounts.attach.preview`, `accounts.attach.store`, `accounts.attach.verify-tag` and `accounts.verify.store`. synced → specs/19 §4.
2. **The last lookup stays in the session** (`accounts.attach.preview`) while the user is on that tag (`?tag=`), so the confirmation or conflict card survives a reload and a failed token. A successful attach or verification clears it. A refused attach replaces it with the attach's own answer. The token outcome is flashed once (`verifyResult`). No spec change.
3. **The token field is `api_token`.** It is in the exception handler's `dontFlash`, the pages clear it after every submit, and the Sentry scrubber already drops request bodies and frame variables (no change there). Tested for a valid token, a refused token, the conflict card's two paths, and a validation failure on another field. synced → specs/09 §9.
4. **A new `AttachBlock` enum** (`email_unverified`, `account_blocked`) from `AttachAccountService::block()` replaces a raw `can` flag, so the page can say why. Suspended accounts never reach the page (`EnforceAccountStatus`); restricted ones attach. synced → specs/05 §2.
5. **Holder privacy** (Open question 1) uses Auth `UserLookupService::findListed` (banned and pending-deletion holders are never named) plus `PrivacyPolicyResolver`. synced → specs/13 §4.
6. **`AccountReadModel`** (`own`, `ownRow`, owner-scoped) and `OwnCocAccountData` feed the steps and the own profile list. The list leaves out `released` rows. `ProfileShowPageData.ownAccounts` is null for everyone but the owner. synced → specs/05 §2, specs/18 §6.
7. **The reward toast** shows on the redirect right after the first verification only: the verification that made the account featured, flashed as `firstAccount`. Revisits show no toast. No spec change.
8. **A second token submit for a row that is already verified** goes to the success screen instead of a 403. Step 2 for a verified row redirects there too. No spec change.
9. **Token steps** are numbered text (Open question 3). The numbered markers are plain numerals in circles, with no icons: the steps are a sequence, and a numeral says that without decoration (R-31). synced → specs/18 §6.
10. **New UI:** `UiSteps` (in `/dev/components`) and `useThTier` (TH level to pill tone). The takeover notice links to `/accounts/attach?tag=…`, and its email has a "Verify it again" button. synced → specs/16 §2, specs/18 §4 (`Steps`).
11. **Board fix:** an earlier edit had left P2-11's board row with a broken column; repaired.

### Review fixes (verify, 2026-10-02)
- Security (low): the conflict card's holder name was saved in the session with the lookup, so it stayed visible after the holder went private or was banned. The session copy no longer holds the name; `AttachAccountService::holderUsername()` re-checks it on every view. Tested. synced → specs/13 §4.
- Security (low): the route-level access checks (another user's rows, holder naming, the own-accounts list) moved from Feature to `tests/Security/PlayerAccounts/AttachFlowAccessTest.php`.
- Spec (medium): the stale preview had no test; added a Feature test (round trip with `fetchedAt`) and a Vitest case ("saved data from …").
- Spec (low/medium): a `suspended` row on the own profile read "Unverified". `OwnCocAccountData` now carries `statusLabel` from the enum, and the pill tone follows the status. Tested.
- Spec (low/medium): the token-secrecy test now covers the conflict card's valid and refused tokens.
- Spec (low), handed on: 13 §9 wants verification disabled with a message while the API is down. Today the form stays enabled and a refused attempt says the game cannot be reached, with nothing saved. Disabling it up front needs `CocApiStatus` on the page, which P2-10 brings (board row updated).
- Spec (low), not changed: the controller's "already verified, go to the success screen" check stays in `VerificationController`. It picks a redirect from read-model state, and the service's refusal of a second verify is tested as a rule (P2-02).
- Found while testing: the profile's "Verify" link announced "VerifyAlt" (Vue dropped the space before the screen-reader text); it now has an `aria-label`.
- antislop audit-029: no findings.

- Owner report: "Yes, attach this account" sent an empty tag. The attach and claim forms took the tag when the page mounted, before any lookup, and Inertia keeps the page mounted across the lookup. Both now read the current card's tag at submit time, and the card shows a tag error if one comes back. Vitest regression added.
- scripts/check.sh: all green (sqlite + postgres).
