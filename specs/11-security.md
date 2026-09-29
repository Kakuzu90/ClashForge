# 11 — Security Requirements

Security is a build-time constraint here, not a hardening phase. Each control below names the
mechanism and the test that proves it.

## 1. Threat model summary

| Asset | Threat | Impact |
|---|---|---|
| Verified CoC account ownership | Fraudulent claim, dispute abuse, social engineering | Destroys the platform's core trust proposition |
| User accounts | Credential stuffing, session theft, account takeover | Impersonation, content theft |
| Uploaded media | Malware, polyglots, stored XSS via SVG, illegal content | Malware distribution, legal exposure |
| Moderator/admin accounts | Privilege escalation, lateral movement | Mass data damage, platform takeover |
| Marketplace | Scams, off-platform payment fraud, account-trading listings | Financial harm to users, ToS violation, legal exposure |
| CoC API keys | Leakage, abuse | Key revocation, platform-wide feature outage |
| User PII (email, IP) | Enumeration, scraping, leaks | Privacy harm, regulatory exposure |
| Content corpus | Mass scraping, duplicate reposting | Competitive loss, degraded quality |

**Principal attacker profiles:** (1) the opportunistic scammer selling accounts or boosting;
(2) the aggrieved player trying to take over a rival's tag; (3) the automated scraper/bot farm;
(4) the commodity web attacker running scanners against a PHP site.

## 2. Controls by category

### Injection (SQL / command / header)
- Eloquent and the query builder with bound parameters everywhere. Raw SQL is permitted only for
  search vectors and reconcile queries, always with bindings — **never** string interpolation.
- Static analysis flags `DB::raw` usage; each occurrence needs an inline justification comment.
- Sort/filter parameters map through an allowlist array to column names; user input is never
  concatenated into `ORDER BY`.
- ffmpeg/ffprobe are invoked with `Process` and an argument array, never a shell string. Paths are
  app-generated ULIDs, never user input.
- **Test:** feature tests assert that `?sort=id;DROP` yields a validation error, not a query.

### XSS
- Blade `{{ }}` escaping everywhere; `{!! !!}` is banned outside a single sanitised-markdown helper.
- If markdown is ever enabled for descriptions, it goes through an HTML sanitiser with a strict
  allowlist — no raw HTML pass-through.
- User URLs are validated to `http/https`, rendered with `rel="nofollow ugc noopener"` and
  `target="_blank"`. `javascript:`/`data:` schemes rejected at validation.
- CSP: `default-src 'self'; script-src 'self' 'nonce-...'; object-src 'none'; base-uri 'self';
  frame-ancestors 'none'; img-src 'self' https://cdn.<domain> data:; media-src 'self' https://cdn.<domain>`.
  No `unsafe-inline` for scripts; Alpine is used in a CSP-compatible way or with nonced inline
  blocks. Report-only first, enforced before launch.
- SVG uploads rejected ([10](10-media-storage.md)); media served from a separate origin.
- **Test:** a stored-XSS test posts `<img src=x onerror=...>` into every text field and asserts the
  rendered output is escaped.

### CSRF
- Laravel's VerifyCsrfToken on every state-changing route; Livewire carries the token automatically.
- `SameSite=Lax` session cookies; `Secure` and `HttpOnly` set.
- No route is exempted. If a webhook ever needs exemption, it authenticates by signature instead.
- Sensitive actions (email change, password change, account deletion, ownership transfer) require
  password re-confirmation within the last 15 minutes.

### IDOR / broken object-level authorization
- Public identifiers are ULIDs or natural keys; sequential ids never appear in URLs.
- Owned resources are fetched with an ownership-scoped query, so a foreign id 404s before policy
  evaluation.
- Nested routes use scoped bindings (`/bases/{base}/comments/{comment}`).
- Every controller/Livewire action calls `authorize()`; a test helper enumerates routes and fails
  the build if a state-changing route has no authorization call.
- Signed media URLs are short-lived and bound to the object key.
- **Test:** for each owned resource, a test asserts user B gets 403/404 on user A's object.

### Broken authorization / privilege escalation
- Policies and Gates only ([04](04-roles-and-permissions.md)); `Gate::before` limited to super admin.
- Role changes are only possible through a dedicated service that (a) requires super admin,
  (b) requires 2FA on the target account for staff roles, (c) writes an audit log, (d) cannot be
  invoked from a web form that takes the role from the request without an explicit allowlist.
- `users.role` is **guarded** against mass assignment and is not in `$fillable`.
- Admin routes sit behind a role middleware **and** a Gate check in each component.
- No impersonation feature exists.
- **Test:** a matrix test iterates every role × every admin ability and asserts the permission
  matrix in [04](04-roles-and-permissions.md) exactly.

### Mass assignment
- Models define `$fillable` explicitly; `$guarded = []` is banned.
- Sensitive columns (`role`, `status`, `email_verified_at`, `verified_at`, counters, `user_id` on
  owned models) are never fillable and are set through services.
- Form Requests return only validated data; services accept typed DTOs, not request arrays.
- **Test:** a test posts `role=admin` and `status=active` into every profile/base update endpoint
  and asserts no change.

### Authentication attacks
- Rate limits per [04 §4](04-roles-and-permissions.md).
- Identical responses and timing for unknown-email vs wrong-password; the same generic message on
  password reset ("if an account exists, we've sent a link").
- Registration rejects existing emails with the same generic behaviour — the account-existence
  signal is delivered by email, not by the form.
- Session fixation prevented by regeneration on login and privilege change.
- Password resets invalidate all sessions; reset tokens are single-use and expire in 60 minutes.
- Compromised-password check via HIBP k-anonymity on registration and password change.
- 2FA mandatory for moderator and above.
- Login notifications ("new sign-in from a new device") emailed for unrecognised device
  fingerprints.

### Account enumeration
- Generic messages on login, register, reset and email change.
- Username availability check is rate-limited (10/min) and returns only a boolean.
- Profile visibility settings respected in search and in direct URL access (`private` → 404, not
  403, so existence is not confirmed).

### File upload attacks
Fully specified in [10-media-storage.md](10-media-storage.md). Summary of controls:
magic-byte MIME detection, extension+MIME allowlist, mandatory re-encode, SVG rejected,
non-public quarantine prefix, app-generated storage keys, separate cookieless media origin,
sandbox CSP on media, size/dimension/duration caps enforced before decode, optional ClamAV behind
an interface.

### API abuse, scraping and bots
- Named rate limiters on every write and on search; a global per-user write limiter as a backstop.
- Cloudflare in front: bot fight mode, managed rules, and a rate-limiting rule on
  `/u/*`, `/bases/*` and `/search` for anonymous traffic.
- Turnstile on registration, password reset and (conditionally, on reputation signals) on
  base publishing and reporting.
- Pagination is capped (max 50 per page) and deep pagination beyond page 100 requires auth.
- No public JSON endpoints in the MVP; Livewire endpoints require a valid session and a component
  checksum.
- `robots.txt` allows indexing of content pages, disallows search, filter and pagination URLs.
- Scraping detection: per-IP request-rate anomaly job flags candidates for Cloudflare rules.
- **Accepted reality:** public content is scrapable by a determined actor. The controls target
  cost-of-scraping and infrastructure protection, not prevention.

### Spam and fake accounts
- Email verification required for every write.
- Disposable-email domain blocklist, refreshed monthly.
- New-account trust ramp: for the first 24 h and until a verified CoC account exists, publishing,
  commenting and reporting are limited to a fraction of normal quotas.
- Link limits in comments (0 links for accounts under 7 days old, 1 thereafter).
- Duplicate-content detection on comments (same body posted 3+ times → auto-hide + report case).
- Honeypot fields plus a minimum form-fill time on registration.

### Marketplace fraud (Phase 6)
- Seller applications reviewed manually; verified CoC account ≥30 days.
- Listing text screened for prohibited terms (account sale/trade, gems, credentials, "login to my
  account") with an auto-reject + review queue.
- Off-platform payment solicitation is a reportable offence with an explicit reason code; the UI
  states clearly that the platform does not process or protect payments.
- Order-scoped messaging only; no arbitrary DMs from the marketplace.
- Review integrity: reviews only on completed orders, one per order, mutual-review-ring detection
  on the anomaly job.

### Session security
- `Secure`, `HttpOnly`, `SameSite=Lax` cookies; `session.encrypt = true`.
- Idle 14 days / absolute 30 days lifetimes.
- Session listing and remote revocation in settings.
- Logout revokes the remember token.
- Sensitive-action re-authentication window of 15 minutes.

### Transport & headers
HSTS (1 year, includeSubDomains, preload), TLS 1.2+, `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY` / `frame-ancestors 'none'`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` denying camera/microphone/geolocation/payment, `Cross-Origin-Opener-Policy: same-origin`.

### Secrets & configuration
- All secrets in environment variables; `.env` never committed; `.env.example` holds keys with empty
  values only.
- Production `APP_DEBUG=false`, `APP_ENV=production` asserted by a deploy-time check.
- CoC API keys, R2 credentials and mail credentials are never logged; a log-scrubbing processor
  redacts known secret patterns and request fields (`password`, `token`, `secret`).
- Encrypted at rest in the database: 2FA secrets and recovery codes.
- Key rotation runbook for: `APP_KEY` (with a re-encrypt migration), CoC API keys, R2 keys.

### Dependency & supply chain
- `composer audit` and `npm audit` on every PR; criticals block merge.
- Dependabot/Renovate for weekly updates.
- Composer lockfile committed; `--no-dev` in production builds.
- CI runs with a restricted token; no third-party actions without a pinned SHA.

## 3. Logging, monitoring and response

**Security events logged** (structured, to a dedicated channel):
failed and successful logins, password/email changes, 2FA changes, role changes, permission denials,
CoC claim attempts and verification failures, ownership transfers, sanctions, admin data access,
rate-limit breaches, upload quarantines, CSP violation reports.

**Alerts:** >50 failed logins from one IP in 10 min; any role change; any ownership transfer;
>10 quarantined uploads in an hour; CSP violation spike; permission-denial spike from one user;
all CoC API keys unhealthy.

**Not logged:** passwords, CoC API tokens, session payloads, full IP addresses beyond 30 days
(hashed with a rotating salt thereafter).

**Incident response:** a documented runbook covering — revoke sessions for an account or globally;
rotate `APP_KEY`, CoC keys and R2 keys; put the site in maintenance mode; mass-hide content by
author; disclosure obligations and timelines. Rehearsed once before launch.

## 4. Security testing requirements

| Test type | Requirement |
|---|---|
| Authorization matrix | Automated test covering every role × every ability |
| IDOR suite | Per owned resource, cross-user access denied |
| Mass assignment suite | Privileged fields rejected on every update endpoint |
| Upload suite | Polyglot file, MIME mismatch, oversized, SVG, zip-bomb image, 0-byte, wrong magic bytes |
| Rate limit suite | Each named limiter enforced and correctly keyed |
| XSS suite | Payloads in every user-controlled field, output asserted escaped |
| Auth suite | Enumeration, fixation, reset-token reuse, session invalidation on password change |
| Static analysis | PHPStan L6/L8, plus a rule banning `DB::raw` interpolation and `$guarded = []` |
| Dependency scan | Every PR |
| Manual review | Checklist review before each phase ships: new routes have policies, new models have `$fillable`, new uploads use the media pipeline |
| Pre-launch | One external penetration test or a structured self-assessment against the OWASP ASVS L2 checklist |

## 5. Privacy-adjacent security requirements

- IP addresses are stored hashed (HMAC with a server-side salt rotated every 90 days) except in the
  live session row.
- Email addresses appear only to the owner and to admins; never in public pages, never in
  notification payloads to third parties.
- Data export and deletion flows exist (NFR-PRIV-1/2).
- Report evidence is private media, visible only to staff, with every access audit-logged.
- Moderator actions on a user are visible to that user in aggregate (what and why), never the
  identity of the reporter.
