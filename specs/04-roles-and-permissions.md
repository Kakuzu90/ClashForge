# 04 — Roles, Permissions & Auth Strategy

## 1. Roles

Four roles, stored as a single `users.role` enum column. **Deliberately not a package.**
Spatie's permission package is excellent but adds four tables and a mental model we do not need
for four fixed roles; if per-user granular permissions become necessary (e.g. "media-only
moderator"), we migrate then, not now.

| Role | Value | Who | Granted by |
|---|---|---|---|
| User | `user` | Every registered account | Automatic |
| Moderator | `moderator` | Trusted community volunteers | Admin |
| Admin | `admin` | Staff | Super admin |
| Super Admin | `super_admin` | Founders / platform owners | Seeded, changeable only via console command |

Roles are hierarchical: each level includes everything below it, except where a rule explicitly
says otherwise (e.g. moderators cannot suspend users; admins cannot change roles).

### Account status (orthogonal to role)

`users.status`: `active`, `restricted`, `suspended`, `banned`, `pending_deletion`.

| Status | Can log in | Can read | Can write | Notes |
|---|---|---|---|---|
| `active` | yes | yes | yes | Normal |
| `restricted` | yes | yes | no publishing/commenting/messaging | Soft sanction, time-boxed |
| `suspended` | yes | own data only | no | Every page except the notice, logout, `/settings/*` and `/notifications` redirects to a suspension notice with reason and end date; the appeal link is added with appeals (P5-02) |
| `banned` | no | no | no | Every session ends and the remember token cycles when the ban is applied; content hidden (the public profile 404s), tags released after 30 days |
| `pending_deletion` | yes (fresh sign-in cancels deletion) | yes | no | 30-day window; requesting deletion ends all sessions and remember-me; the public profile 404s |

Additional flags gating capabilities: `email_verified_at` (required for content writes: publishing,
commenting, attaching CoC accounts, uploads; FR-AUTH-4),
`has_verified_coc_account` (required to publish bases, recruit, or sell).

## 2. Permission matrix

`○` = own resource only. `✓` = any resource. `–` = no.

| Capability | User | Moderator | Admin | Super Admin |
|---|---|---|---|---|
| View public content | ✓ | ✓ | ✓ | ✓ |
| Edit own profile / privacy | ○ | ○ | ○ | ○ |
| Attach / verify / detach CoC account | ○ | ○ | ○ | ○ |
| Publish / edit / delete base | ○ | ○ | ○ | ○ |
| Like / bookmark / comment | ✓ | ✓ | ✓ | ✓ |
| Report content | ✓ | ✓ | ✓ | ✓ |
| Create recruitment post | ○ | ○ | ○ | ○ |
| Apply to recruitment post | ○ | ○ | ○ | ○ |
| View report queue | – | ✓ | ✓ | ✓ |
| Claim / assign a report case | – | ✓ | ✓ | ✓ |
| Hide content | – | ✓ | ✓ | ✓ |
| Remove content permanently | – | – | ✓ | ✓ |
| Warn user | – | ✓ | ✓ | ✓ |
| Restrict user (≤7 days) | – | ✓ | ✓ | ✓ |
| Suspend user | – | – | ✓ | ✓ |
| Ban user | – | – | ✓ | ✓ |
| Unban / lift sanction | – | – | ✓ | ✓ |
| Review media quarantine queue | – | ✓ | ✓ | ✓ |
| Resolve ownership dispute | – | – | ✓ | ✓ |
| Force ownership transfer | – | – | ✓ | ✓ |
| Approve marketplace seller | – | – | ✓ | ✓ |
| Resolve marketplace dispute | – | – | ✓ | ✓ |
| Manage tags / categories | – | – | ✓ | ✓ |
| View moderation log | – | ○ own actions | ✓ | ✓ |
| View audit log | – | – | ✓ | ✓ |
| View user accounts (admin list and detail, with email) | – | – | ✓ | ✓ |
| View platform stats (dashboard sign-ups, failed jobs, media storage) | – | – | ✓ | ✓ |
| Change user roles | – | – | – | ✓ |
| Manage feature flags / settings | – | – | – | ✓ |
| Hard-delete a user | – | – | – | ✓ |
| Impersonate a user | – | – | – | – (never) |

**Two structural rules:**
1. A staff member can act only on content or accounts of someone they strictly outrank: a moderator
   never acts on a moderator-or-above, an admin never on another admin. Same-level cases escalate;
   super admins are changed only through the console command.
2. Every row above `Report content` requires the actor to record a reason; the reason is mandatory
   at the service layer, not merely in the form.

## 3. Authorization strategy

### Policies as the only source of truth

- One Policy per authorizable model: `UserPolicy`, `ProfilePolicy`, `PrivacySettingsPolicy`, `CocAccountPolicy`,
  `BaseLayoutPolicy`, `BaseCommentPolicy`, `RecruitmentPostPolicy`, `ApplicationPolicy`,
  `ListingPolicy`, `OrderPolicy`, `ReportPolicy`, `MediaPolicy`, `NotificationPolicy` (a notification is
  its recipient's alone; staff have no reach into it).
- Every request that reads or writes a resource runs its policy. **No implicit trust from route
  grouping alone** — route middleware is defence in depth, not the check. Controllers call
  `authorize()` / `Gate::authorize()` when they hold what the policy needs; because Http may not
  reference domain models ([19 §2](19-module-structure.md)), a service or action that loads a
  model by its public id authorizes it with `Gate::forUser($user)->authorize()` straight after the
  owner-scoped lookup (e.g. `UploadIntentService`, `CompleteUpload`). Policy tests call the Gate
  and the service directly, so removing the call fails a test.
- Vue never decides authorization. Controllers pass per-resource ability flags computed by
  policies (e.g. `base.can = { update, delete, report }`) and a global `auth.can` map via shared
  props; components use them only to show or hide UI. The server re-checks on action. Hiding a
  button is not authorization.
- Profile visibility has no staff bypass: `/u/{username}` applies the same rules to staff, who see
  hidden accounts through the admin user detail instead.
- The admin user list and detail never show the viewer's own account or any super admin (the
  detail 404s), so staff cannot look up themselves or the platform owners there.
- Staff abilities live in Gates, one per staff row of §2 (`App\Domain\Auth\Enums\StaffAbility`,
  registered in `App\Providers\AuthorizationServiceProvider`), including `access-admin`
  (moderator+), `manage-roles`, `resolve-disputes` and `view-audit-log`.
- Super admin holds every staff ability through the role hierarchy, except `impersonate`, which no
  role holds. There is no `Gate::before` hook, so ownership policies (the `○` rows above) and rule 1
  still apply to super admins and the matrix holds exactly.
- A staff ability also needs the account's status to allow it: the read abilities (`access-admin`,
  `view-users`, `view-platform-stats`, `view-report-queue`, `view-moderation-log`, `view-audit-log`) stay open to restricted and
  pending-deletion staff, every other staff ability needs an active account, and a suspended
  account has none. A timed sanction stops counting once `status_expires_at` passes.

### IDOR prevention

- P1-15 email preferences are the recipient's alone, with no staff bypass. Signed-in owners may
  edit them even when unverified, restricted, suspended or pending deletion; these housekeeping
  writes skip `account.active` and retain `throttle:global-write`. A signed unsubscribe capability
  also works without sign-in, including for banned recipients, and authorizes only disabling
  non-security mail for the bound account. The policy checks the capability's signature, expiry
  and current-email binding; it cannot authorize reads of private preferences or re-enabling mail
  ([16 §5](16-notifications.md); owner decision, 2026-10-01).

- All owned-resource queries are scoped at the query level (`->whereBelongsTo($user)`), so a wrong
  id 404s before a policy ever runs.
- Public identifiers: users are addressed by `username`, bases by `ULID` slug
  (`{ulid}-{slug}`), never by autoincrement id in any URL.
- Route model binding uses explicit scoped bindings for nested resources
  (`/bases/{base}/comments/{comment}`), so a comment from another base cannot be targeted.

### Write gating middleware

Three middlewares, applied in order, each with a dedicated denial page:
1. `EnsureEmailIsVerified` (Laravel's `verified`) — blocks content writes for an unverified email
   (FR-AUTH-4): uploads check it in `MediaPolicy`, later content routes add the middleware.
   Account, settings and notification writes stay open, so a mistyped address can be fixed;
   the one exception is a username change (`UserPolicy::changeUsername`), since each change holds a
   name for 90 days (owner decision, 2026-10-01, P1-09).
2. `EnsureAccountIsActive` — `account.active` blocks `suspended`, `banned` and `pending_deletion`
   writes; `account.active:content` also blocks `restricted` on content writes (uploads, publishing,
   commenting, applying, messaging). Profile and settings writes stay open to restricted users.
   Reads (GET/HEAD) pass, so the gate can sit on a whole route group such as `/admin`. The
   notification centre's writes (mark one or all read) skip it: they touch only the account's own
   rows, so unverified, restricted, suspended and pending-deletion accounts keep them (owner
   decision, 2026-10-01); `throttle:global-write` still applies.
3. `EnsureHasVerifiedCocAccount` — blocks publishing, recruiting and selling.

## 4. Authentication strategy

### Mechanism
- **Session-based authentication** (Laravel's built-in guard), database session driver. Inertia
  requests use the same session cookie; no token juggling.
- No API tokens or Sanctum in the MVP — there is no public API and no separate frontend. When a
  public read API arrives (Phase 7), it will be Sanctum-issued, scoped, per-user tokens with their
  own rate limits.
- Remember-me is enabled. The recaller cookie lasts 30 days (the absolute cap below), and its token is
  cycled on logout, password reset, password change and session revocation. A cycle never re-issues
  this browser's cookie, so remember-me cannot be extended past the password sign-in. A sign-in
  from the recaller inherits the time of that password sign-in from the encrypted `remember_since`
  cookie; without it the session counts as expired.
- Backend: Laravel Fortify's controllers and actions, behind our own Inertia pages, copy and routes
  (`routes/web/auth.php`). The reset-link request is ours (a queued job, see [11](11-security.md)),
  and so are registration and email verification (`RegisterController`, `RegistrationService`,
  `EmailVerificationController`): Fortify's registration signs the new account in, which the
  taken-email path could not copy.

### Registration
Email + username + password (+ confirmation) + Turnstile, plus a hidden honeypot field and a
single-use encrypted form token ([11](11-security.md) "Spam and fake accounts"). Disposable-domain
blocklist. Usernames are lowercased and trimmed, then 3 to 20 of `[a-z0-9_]`, unique ignoring case
against every account (deleted ones included), and not on the reserved list
(`platform.auth.reserved_usernames`: `admin`, `mod`, `support`, `api`, `u`, `base`, ...), and not held
in `username_history` (a deleted account's name forever, a changed-away name for 90 days).
Registration never signs anyone in: every outcome ends on "Check your email". A verified email is
required for content writes (FR-AUTH-4). The verification link is signed for 60 minutes
(`platform.auth.verification_link_minutes`) and addressed by ULID and a hash of the email; it opens
a page naming the account whose button confirms (POST), and confirming ends every other session of
the account and cycles its remember token.

### Email change
From `/settings/security`, with the current password typed in the same form (`EmailChangeService`);
sending the link again takes it too, so a copied cookie cannot finish a stale change later. Both
share the `password-confirm` limiter.
The new address (the registration email rules, disposable blocklist included) waits in
`users.pending_email` and gets a link signed for `platform.auth.verification_link_minutes`,
addressed by ULID and a hash of the pending address, so a newer request or a cancel kills the older
link. The link needs the account signed in on that browser (a guest signs in and comes back);
opening it shows the account and the masked new address, and its button (POST) changes the email,
sets `email_verified_at`, ends every other session, cycles the remember token and gives this
browser a new session id. A taken address is stored and answered like a free one ([11](11-security.md)
"Account enumeration").

### Username change
From `/settings/profile` (`PUT /settings/profile/username`, `UsernameChangeService`), with the current
password typed in the same form and the registration username rules. Once per
`platform.auth.username_change_days` (30, from `users.username_changed_at`; the first change is open at
once); active and restricted accounts with a verified email may change, suspended ones cannot. The old
name goes into `username_history`, held for `platform.auth.username_reservation_days` (90) against every
other account; the releaser may take it back. During the hold `/u/{old}` answers 301 to the current name
with `Cache-Control: no-store`, only when that profile would render for the viewer; otherwise the same
404 as an unknown name. The change locks the account row, authorizes that row, and re-checks the hold
after its update (specs/08 §6). Records `user.username_changed` in `audit_logs` and
`auth.username_changed` in the security log; no email or in-app notice. Approved by the owner,
2026-10-01 (P1-09).

### Account deletion
Own-account deletion requires the current password entered inline on every submission, checked against the locked account row; it does not redirect through the password-confirmation page. Guesses share the `password-confirm` limiter. Active and restricted
accounts may request it, including those with unverified email; suspended and banned accounts
cannot. The request sets `pending_deletion` and `deletion_requested_at`, ends every session
(including this browser), cycles the remember token and clears this browser's remember-me cookies.
Only a fresh sign-in cancels the pending request. Cancellation clears the deletion request and
restores any still-effective sanction, otherwise `active`; deletion never lifts a sanction.
The request saves `deletion_previous_status` and keeps the sanction reason/end. Password sign-in
and anonymisation both lock the account row before changing it. `deleted_at` is set only on the
anonymised tombstone; the grace-period account is hidden by its status.
After 30 days, the nightly pipeline anonymises the retained account and its Phase 1 data
([08 §6](08-entity-relationships.md)). These decisions were approved by the owner, 2026-10-01 (P1-11).

### Password policy
- Minimum 10 characters, no composition rules (they harm more than help).
- Rejected if present in the Have-I-Been-Pwned range API (`Password::uncompromised()`), checked
  synchronously inside validation with a 2 s timeout. If the service cannot be reached the password
  is accepted and `auth.hibp_unavailable` is logged (fail-open, never silent).
- Hash: bcrypt cost 12, rehash on login when the cost changes.

### Session security
- Regenerate the session id on login, logout, privilege change, password change, email change and
  "sign out every other device" (a copy of this browser's cookie is another device too).
- Absolute session lifetime 30 days from the password sign-in (`platform.auth.absolute_session_days`,
  clock kept in the session as `auth.signed_in_at`), idle lifetime 14 days.
- Session records store device label, hashed IP, CDN country and last-active for the
  session-management UI (`/settings/security`); listing and counts skip rows past the idle lifetime.
- Password change, email change and 2FA change invalidate all other sessions.
- Sensitive actions re-confirm the password within 15 minutes (`auth.password_timeout`) through the
  `password.confirm` page; the password, email, username and account-deletion forms ask for the current password inline instead.

### Rate limits (named limiters)

| Limiter | Limit | Key |
|---|---|---|
| `login` | 5 / min, then 20 / hour; 30 / min per ip | ip + email; ip |
| `register` | 3 accepted / hour (counted after validation, so typos are free); every attempt 20 / hour (`platform.auth.register_attempts_per_hour`) | ip |
| `password-reset` | 3 / hour; 20 / hour per ip (link requests only) | ip + email; ip |
| `verify-email-resend` | 3 / hour; a breach is a flash error on the notice page | user |
| `email-change` | 3 accepted requests and resends / hour (`platform.auth.email_change_per_hour`), counted after validation (`EmailChangeLimit`), so typos are free | user |
| `coc-attach` | 5 / hour | user |
| `coc-refresh` | 1 / 10 min | user + account |
| `base-publish` | 5 / day, 20 / week | user |
| `comment` | 10 / hour, 60 / day | user |
| `report` | 20 / day | user |
| `upload-intent` | 30 / hour | user |
| `search` | 60 / min | ip |
| `global-write` | 120 / min (`platform.rate_limits.global_write_per_minute`) | user |
| `password-confirm` | 5 / min, 20 / hour (`platform.auth.password_confirm_per_*`); confirm page, password form, email form, username form and account-deletion form share it | user |
| `admin-search` | 60 / min (`platform.rate_limits.admin_search_per_minute`); admin user list and audit log share it, deferred rows count; a breach is a bare 429 shown inline | user |

All limiters are defined centrally and use the `Cache` facade so they move to Redis unchanged. Their
numbers are config keys (`config/platform.php` `auth.*` for the auth limiters). On an Inertia form
a breach comes back as a field error with the wait time, not a bare 429, and is logged to the
`security` channel. The new-password form (`POST /reset-password`) has no limiter: its single-use
token already stops guessing, and counting typos would lock people out of the link they were sent.

### Two-factor (Phase 2, mandatory for staff)
TOTP with 8 single-use recovery codes. Until 2FA ships, staff roles are granted without it (P1-02);
from Phase 2, moderator+ accounts cannot be granted a staff role until 2FA
is enabled — enforced in the role-assignment service.

### Account recovery
Email-based reset only. **No security questions, no support-driven manual resets in the MVP**: a
manual reset path is the single most-abused social-engineering vector on community platforms. If a
user loses email access, their CoC accounts can be re-claimed on a new account via in-game token
verification — which is stronger proof than anything support could verify.
