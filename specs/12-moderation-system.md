# 12 — Moderation System

## 1. Design stance

Moderation is designed for **a two-person volunteer team handling a few hundred reports a week**,
not for an enterprise trust-and-safety org. That constraint drives three decisions:

1. **Group, don't queue individually.** Ten reports about one base are one case.
2. **Decide with context in one screen.** The case view carries the content snapshot, the reporter
   history, the author history and the actions — no tab-hopping.
3. **Automate the obvious, escalate the rest.** Auto-hide on strong signals, but never auto-ban.

## 2. Reportable surfaces and reasons

| Surface | Reportable | Notes |
|---|---|---|
| User profile | ✓ | impersonation, offensive bio/avatar |
| CoC account | ✓ | false ownership is the key reason code |
| Base layout | ✓ | stolen content, misleading category, offensive imagery |
| Comment | ✓ | highest volume surface |
| Recruitment post | ✓ | spam, scam, off-platform solicitation |
| Marketplace listing | ✓ | account trading, prohibited service |
| Message | ✓ | scam, harassment |

### Reason codes (fixed taxonomy)

| Code | Applies to | Auto-priority |
|---|---|---|
| `account_trading` | listing, post, comment, message, profile | **Critical** |
| `scam` | listing, message, post | High |
| `false_ownership` | coc_account | High |
| `nsfw` | media-bearing surfaces | High |
| `hate` | any | High |
| `harassment` | comment, message, profile | High |
| `impersonation` | profile, coc_account | Medium |
| `stolen_content` | base | Medium |
| `off_platform_payment` | listing, message | Medium |
| `spam` | any | Low |
| `wrong_category` | base | Low |
| `other` | any | Low |

`account_trading` is Critical because it is the one category that can cost the platform its legal
standing with Supercell. It pages, it does not wait in a queue.

## 3. Report lifecycle

```
user submits report
   │  (rate-limited 20/day; one open report per user per target)
   ▼
reports row created + target_snapshot captured
   │
   ├── existing open case for this target? ──yes──▶ attach to case, bump counters & priority
   │                                          no
   ▼
report_cases row created (status=open, priority from reason + signals)
   │
   ├── auto-action rules evaluated (§5)
   │     ├─ strong signal ──▶ content auto-hidden, case priority raised
   │     └─ otherwise      ──▶ no change
   ▼
triage (manual or automatic by priority)
   ▼
moderator claims case (status=assigned, assigned_to, assigned_at)
   ▼
decision ─┬─ dismiss  ──▶ status=dismissed, reporters notified "no action"
          ├─ hide     ──▶ content hidden, author notified + warned
          ├─ remove   ──▶ content removed (admin+), author notified
          ├─ sanction ──▶ warn / restrict / suspend / ban (role-gated), user notified
          └─ escalate ──▶ status=escalated, routed to admin queue
   ▼
moderation_actions row (immutable) + audit_logs row
   ▼
case status=actioned, resolution recorded, reporters notified of the outcome category
```

### Case priority formula

```
priority = reason_weight
         + min(distinct_reporters, 5) * 2
         + (target_author_has_prior_sanctions ? 3 : 0)
         + (target_is_public_and_trending    ? 2 : 0)
         + (reporter_trust_score_avg         ? +0..2 : 0)
         - (target_author_is_established     ? 1 : 0)
```
Recomputed on every new report attached to the case. Critical reasons floor at the top of the queue
regardless of the rest.

### SLA targets

| Priority | First response | Resolution |
|---|---|---|
| Critical | 1 hour | 4 hours |
| High | 4 hours | 24 hours |
| Medium | 24 hours | 72 hours |
| Low | 72 hours | 7 days |

Breaches are visible on the admin dashboard, and an ageing case escalates its own priority daily.

## 4. Moderator workspace

**Queue view:** filter by priority, reason, surface, age, assignment. Default sort: priority desc,
age desc. Claiming a case locks it to that moderator for 30 minutes (soft lock, visible to others).

**Case view — one screen, everything needed:**
- Target content rendered as users see it, plus the `target_snapshot` if it has since changed
  (with a visible "content was edited after reporting" warning).
- All grouped reports: reporter, reason, detail, evidence, timestamp.
- Author context: account age, verified accounts, published content count, prior sanctions,
  prior dismissed reports against them, current status.
- Reporter context: each reporter's report history and their dismissal rate (the abuse signal).
- Action panel with mandatory reason code and note; destructive actions ask for confirmation and
  show exactly what the user will be told.
- A visible "what the reporter will see" preview and a "what the author will see" preview.

**Moderator constraints:**
- Cannot action content authored by a moderator or above → forced escalation.
- Cannot action their own reports (a case containing their own report is assigned elsewhere).
- Cannot suspend or ban (admin only).
- All actions require a reason; the note field is mandatory for `remove`, `restrict` and escalations.

## 5. Automated pre-moderation

Automation hides; it never bans. Every automated action creates a case for human review.

| Rule | Trigger | Action |
|---|---|---|
| Prohibited-term match | Listing/post/comment matches the account-trading or credential-sharing lexicon | Auto-hide + Critical case |
| Report velocity | ≥5 distinct reporters within 1 hour on one target | Auto-hide + High case |
| New-account burst | Account <24 h old posts >3 items with links | Auto-restrict posting + case |
| Duplicate comment | Same body 3+ times across targets | Auto-hide the duplicates + case |
| Duplicate layout hash | Same `layout_hash` published by a different user | Flag only (no hide) + Low case — legitimate resharing is common |
| Media quarantine | Upload validation flags suspicious content | Media quarantined, case created |
| Link spam | >1 external link from an account <7 days old | Comment held for review |
| Known-bad link | URL matches a blocklist (phishing/scam feeds) | Auto-hide + High case |
| Mass follow/like | >100 interactions/hour | Rate-limit + Low case |

False-positive control: a dismissed auto-action feeds a weekly report on rule precision; any rule
below 70% precision is tuned or disabled. Rules live in config, not code.

## 6. Sanctions

| Sanction | Who can apply | Duration | Effect |
|---|---|---|---|
| Warning | Moderator+ | — | Notification + record; three warnings in 90 days auto-suggests a restriction |
| Restriction | Moderator+ (≤7 days), Admin+ (longer) | 1–30 days | No publishing, commenting, applying or messaging. Reading and existing content unaffected |
| Suspension | Admin+ | 1–90 days | Login permitted, everything hidden, content hidden, suspension notice with appeal link |
| Ban | Admin+ | Permanent | No login. Content hidden. CoC tags released after 30 days. Email + IP hash recorded for evasion detection |

- All sanctions write `user_sanctions` + `moderation_actions` + `audit_logs`.
- Expiry is handled by a scheduled job that lifts sanctions and notifies the user.
- Lifting a sanction requires a reason and is itself audited.
- **Ban evasion:** new accounts matching a banned user's email domain pattern, IP hash or verified
  CoC tag are flagged for review, not auto-banned (shared IPs are common in this audience, and
  households share connections).

## 7. Appeals (Phase 5)

- Any sanction and any content removal can be appealed once, within 30 days.
- Appeals go to a separate queue; the reviewer **must not be** the original decider
  (enforced in the assignment service).
- Outcomes: upheld, overturned, reduced. Each notifies the user with a reason.
- Overturn rate per moderator is tracked and visible to admins — it is the primary quality signal
  for the moderation team.

## 8. Reporter integrity

- Reporters have an implicit trust score: `(actioned_reports + 1) / (total_reports + 2)`.
- Low-trust reporters' reports still enter the queue but contribute less to priority.
- ≥10 reports with ≥80% dismissal in 30 days → report rate limit cut to 3/day and a warning.
- Malicious mass-reporting (coordinated reports on one target from linked accounts) is itself a
  reportable, sanctionable offence and is surfaced by an anomaly job.
- Reporter identity is **never** exposed to the reported user, in any surface, including appeals.

## 9. Audit and transparency

**Moderation log** (`moderation_actions`): every action, immutable, filterable by actor, target,
type and date. Moderators see their own; admins see everything.

**Audit log** (`audit_logs`): every privileged action, including read access to report evidence,
ownership transfers, role changes and data exports. Two-year retention, never edited.

**User-facing transparency:**
- A user can see their own sanction history and the reason for each.
- A content author sees why their content was hidden or removed, and the appeal path.
- A reporter sees an outcome category only ("action taken" / "no action"), never the sanction.
- A public transparency page (Phase 5) publishes quarterly aggregate counts: reports received,
  actioned, dismissed, by category.

## 10. Anti-abuse protections (mapped to the brief)

| Threat | Primary control | Secondary |
|---|---|---|
| Spam | Rate limits, new-account trust ramp, link limits | Duplicate detection, auto-hide rules |
| Bots | Turnstile, email verification, form timing, honeypots | Cloudflare bot rules, behavioural anomaly job |
| Scam attempts | Prohibited-term screening, order-scoped messaging, explicit no-payment-protection warnings | Report reason `scam` at High priority |
| Fake ownership claims | In-game token verification as the only automatic path | Dispute workflow with evidence and audit trail ([13](13-claiming-workflow.md)) |
| Malicious uploads | Validation + mandatory re-encode + quarantine prefix | Optional ClamAV, sandbox CSP on media origin |
| Rate-limit abuse | Named limiters on every write, global write backstop | Cloudflare rate rules, per-user anomaly detection |
| Mass scraping | Pagination caps, anonymous rate rules, no public API | Anomaly detection feeding Cloudflare rules |
| Duplicate content | `layout_hash` uniqueness per author, cross-author flagging | Trending score penalises duplicate clusters |
| Ban evasion | Email/IP-hash/tag matching flags for review | Verified-tag reuse is the strongest signal |
| Review manipulation (P3) | Reviews only on completed orders | Mutual-review-ring detection |

## 11. Operational requirements

- **Admin dashboard** surfaces: open cases by priority, oldest unactioned case, SLA breaches,
  cases per moderator, auto-action precision, quarantined media count, open disputes.
- **Staffing signal:** if median time-to-first-response exceeds the SLA for two consecutive weeks,
  the platform either recruits moderators or tightens automated restrictions. This is a documented
  operational trigger, not an ad-hoc decision.
- **Moderator onboarding:** a written policy handbook (what each reason code means, what each
  sanction is for, escalation rules) must exist before the first volunteer moderator is appointed.
- **Legal escalation path:** CSAM, credible threats and doxxing have a documented out-of-band
  procedure — preserve evidence, ban immediately, report to the relevant authority, do not engage.
