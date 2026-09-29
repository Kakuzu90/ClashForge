# 02 — Functional Requirements & Feature Breakdown

Requirements are numbered `FR-<domain>-<n>` so tasks and tests can cite them.
Priority: **M** = MVP, **P2** = phase 4–5, **P3** = phase 6–7.

---

## FR-AUTH — Authentication & account lifecycle

| ID | Pri | Requirement |
|---|---|---|
| FR-AUTH-1 | M | A visitor can register with email, username and password. Username: 3–20 chars, `[a-z0-9_]`, case-insensitively unique, reserved-word blocklist. |
| FR-AUTH-2 | M | Passwords are ≥10 chars and checked against a compromised-password list; hashed with bcrypt (cost 12) or argon2id. |
| FR-AUTH-3 | M | Registration sends a signed, single-use, 60-minute email-verification link. |
| FR-AUTH-4 | M | Unverified users may log in but cannot publish bases, comment, attach CoC accounts or upload media. |
| FR-AUTH-5 | M | Login is rate-limited per IP and per account; responses are identical for unknown-email and wrong-password (no account enumeration). |
| FR-AUTH-6 | M | Password reset via signed, single-use, 60-minute token; all sessions are invalidated on reset. |
| FR-AUTH-7 | M | Users can see active sessions (device, IP region, last active) and revoke any or all of them. |
| FR-AUTH-8 | M | Users can change email; the new address requires re-verification, the old one gets a change notice. |
| FR-AUTH-9 | M | Users can delete their account: 30-day soft delete, then anonymisation of profile fields, release of CoC account tags, retention of moderation and audit records. |
| FR-AUTH-10 | P2 | Optional TOTP two-factor auth, mandatory for moderator and above. |
| FR-AUTH-11 | M | Registration is protected by an invisible captcha (Cloudflare Turnstile) and a disposable-email-domain blocklist. |

## FR-PROFILE — Website profiles

| ID | Pri | Requirement |
|---|---|---|
| FR-PROFILE-1 | M | Each user has exactly one profile, created on registration. |
| FR-PROFILE-2 | M | Editable: display name, bio (≤500 chars), country, languages (≤3), timezone, social links (YouTube, Twitch, Discord tag, X). |
| FR-PROFILE-3 | M | Avatar upload: ≤2 MB, jpeg/png/webp, square-cropped to 512/128/48 variants. |
| FR-PROFILE-4 | M | Privacy settings: profile visibility (`public` / `members` / `private`), show connected accounts, show clan, allow recruitment contact. |
| FR-PROFILE-5 | M | Public profile at `/u/{username}` shows avatar, display name, bio, verified badge, featured account, connected verified accounts, published bases, stats, member-since. |
| FR-PROFILE-6 | M | Bio and social links are stripped of HTML; URLs are rendered with `rel="nofollow ugc noopener"`. |
| FR-PROFILE-7 | M | Username changes are limited to once per 30 days; old usernames are reserved for 90 days and redirect. |
| FR-PROFILE-8 | P2 | Followers / following with counts and a follow button. |
| FR-PROFILE-9 | P2 | Activity timeline (published base, verified account, earned badge). |
| FR-PROFILE-10 | P3 | Achievements and badges with rarity tiers. |

## FR-COC — Clash of Clans accounts

| ID | Pri | Requirement |
|---|---|---|
| FR-COC-1 | M | A user may attach unlimited CoC accounts. Rate: ≤5 attach attempts per hour per user. |
| FR-COC-2 | M | Player tags are normalised: uppercase, leading `#`, `O`→`0`, charset `[0289PYLQGRJCUV]`, length 3–12 after `#`. Invalid tags are rejected before any API call. |
| FR-COC-3 | M | On attach, the platform fetches the player from the official API and stores tag, IGN, TH level, XP, trophies, best trophies, war stars, attack/defence wins, donations, clan, clan role, league, heroes, troops, spells and hero equipment. |
| FR-COC-4 | M | A tag is globally unique among accounts in `verified` state. |
| FR-COC-5 | M | Ownership is proven by the in-game API token via `POST /players/{tag}/verifytoken`. Success ⇒ `verified`. |
| FR-COC-6 | M | If a verified owner already exists, the attach is refused and the user is offered the dispute path ([13](13-claiming-workflow.md)). Token verification by a new holder wins automatically over an unverified claim. |
| FR-COC-7 | M | Account states: `unverified`, `verified`, `disputed`, `suspended`, `released`. State transitions are explicit and logged. |
| FR-COC-8 | M | Every ownership change writes an immutable audit-log entry (actor, from-user, to-user, reason, evidence ref, timestamp). |
| FR-COC-9 | M | Users can manually refresh an account, at most once per 10 minutes per account. |
| FR-COC-10 | M | Background sync refreshes verified accounts on a staleness schedule; each sync writes a snapshot if any tracked value changed. |
| FR-COC-11 | M | Up to 5 custom images per CoC account, ≤5 MB each. |
| FR-COC-12 | M | A user designates one account as featured; it is the default card on their profile and base cards. |
| FR-COC-13 | M | Users can detach an account; the tag returns to `released` and becomes claimable, retaining the audit trail. |
| FR-COC-14 | M | Profiles render from the last snapshot when the API is unavailable, labelled with the data age. |
| FR-COC-15 | P2 | Clan records are synced for clans that have ≥1 verified member or an active recruitment post. |

## FR-BASE — Base layouts

| ID | Pri | Requirement |
|---|---|---|
| FR-BASE-1 | M | A verified user can publish a base with title (≤80), description (≤2000), TH level (2–17+), category, base link, ≤10 tags, ≤2 screenshots, ≤1 replay video, visibility (`public`/`unlisted`/`private`). |
| FR-BASE-2 | M | The base link must match the official `https://link.clashofclans.com/...?action=OpenLayout` pattern; the layout hash is extracted and stored. |
| FR-BASE-3 | M | Categories: War, CWL, Farming, Trophy, Legend League, Anti-3-Star, Anti-2-Star, Hybrid, Progress Base, Funny/Troll. Exactly one per base. |
| FR-BASE-4 | M | Tags are free text, normalised to lowercase-kebab, max 24 chars, drawn from a suggested list plus a moderated long tail. |
| FR-BASE-5 | M | A base cannot go public until all of its media reach `ready`. It sits in `processing` until then. |
| FR-BASE-6 | M | Likes: one per user per base, toggleable, denormalised counter. |
| FR-BASE-7 | M | Bookmarks: private to the user, listed on their dashboard. |
| FR-BASE-8 | M | Views are counted once per user-or-IP-hash per base per 24h, batched into a counter. |
| FR-BASE-9 | M | Copy-link clicks are counted server-side via a redirect endpoint, deduped per user/IP per hour. |
| FR-BASE-10 | M | Comments: ≤1000 chars, one level of replies, editable for 15 minutes, soft-deletable by the author, removable by moderators. |
| FR-BASE-11 | M | Duplicate detection: an identical layout hash published by a different user is flagged for review, not auto-blocked. The same user republishing a hash is blocked outright. |
| FR-BASE-12 | M | Trending ranking uses a time-decayed score over likes, copies, views and comments; recomputed on a schedule, never live. |
| FR-BASE-13 | M | Feed filters: TH level, category, tag, min-likes, has-video, sort (newest, trending, most liked, most copied). |
| FR-BASE-14 | M | Publishing is limited to 5 bases per user per day, 20 per week. |
| FR-BASE-15 | M | Authors can edit metadata at any time; edits after 24h flag the base for re-review if it is reported. |
| FR-BASE-16 | P2 | Base collections / folders. |
| FR-BASE-17 | P3 | "Similar bases" recommendations. |

## FR-RECRUIT — Recruitment

| ID | Pri | Requirement |
|---|---|---|
| FR-RECRUIT-1 | P2 | A player can publish one active "looking for clan" post per verified CoC account. |
| FR-RECRUIT-2 | P2 | LFC fields: TH (auto from account), trophies (auto), country, languages, activity level, war preference, CWL interest, competitive/casual, preferred clan level, preferred league, note (≤500). |
| FR-RECRUIT-3 | P2 | A clan recruitment post may only be created by a user whose verified account holds `leader` or `coElder`+ role in that clan, as confirmed by the API. |
| FR-RECRUIT-4 | P2 | Clan post fields: clan tag (synced), required TH, required trophies, required activity, war frequency, CWL league, Clan Capital hall level, language, location, description (≤2000), status (`open`/`paused`/`closed`). |
| FR-RECRUIT-5 | P2 | Posts auto-expire after 30 days and can be renewed in one click. |
| FR-RECRUIT-6 | P2 | Players can apply to a clan post with a short message and a chosen CoC account; one active application per player per post. |
| FR-RECRUIT-7 | P2 | Clan recruiters can express interest in an LFC post; the player accepts or declines. |
| FR-RECRUIT-8 | P2 | Application states: `pending`, `accepted`, `declined`, `withdrawn`, `expired`. |
| FR-RECRUIT-9 | P2 | Clan posts are auto-paused when the synced clan reaches 50/50 members. |
| FR-RECRUIT-10 | P2 | Recruitment search filters on every structured field; results are ranked by freshness and clan activity. |

## FR-MARKET — Marketplace (Phase 6)

| ID | Pri | Requirement |
|---|---|---|
| FR-MARKET-1 | P3 | Sellers apply for seller status; approval is manual, requires a verified account ≥30 days old. |
| FR-MARKET-2 | P3 | Listings belong to an allowed-service category: custom base layout, base review, coaching, clan graphics, banners, video editing, tournament graphics, other-permitted. |
| FR-MARKET-3 | P3 | Any listing implying account sale, account sharing, account boosting-by-login, gem selling, or real-money game-currency transfer is rejected and the seller is suspended. |
| FR-MARKET-4 | P3 | Listings have title, description, category, delivery time, revision count, price range or "quote", portfolio media (≤5), status. |
| FR-MARKET-5 | P3 | Orders track `requested → accepted → in_progress → delivered → completed / cancelled / disputed`. |
| FR-MARKET-6 | P3 | Order-scoped messaging only; no free-form DMs to arbitrary users from the marketplace. |
| FR-MARKET-7 | P3 | Reviews are only possible on `completed` orders, one per order, 1–5 stars plus text. |
| FR-MARKET-8 | P3 | Disputes route to an admin queue with a decision record. |
| FR-MARKET-9 | P3 | Payments are **out of scope** for the first marketplace release: it is a discovery and workflow tool, payment happens off-platform at the parties' own risk, stated explicitly in the UI. See [15](15-marketplace-workflow.md). |

## FR-MEDIA — Media

| ID | Pri | Requirement |
|---|---|---|
| FR-MEDIA-1 | M | Uploads go directly to object storage using short-lived presigned URLs; the app server never proxies file bytes. |
| FR-MEDIA-2 | M | Every upload is validated for extension, declared MIME, real MIME from the file signature, dimensions, and size. |
| FR-MEDIA-3 | M | Images: jpeg, png, webp. Account images ≤5 MB, ≤5 per account. Base screenshots ≤5 MB, ≤2 per base. |
| FR-MEDIA-4 | M | Videos: mp4 (h264/aac) only, ≤100 MB, ≤60 s, ≤1080p after processing. |
| FR-MEDIA-5 | M | All images are re-encoded server-side, stripping EXIF (including GPS). |
| FR-MEDIA-6 | M | Thumbnails are generated for every image and a poster frame for every video. |
| FR-MEDIA-7 | M | Media states: `pending → uploaded → processing → ready / failed / quarantined`. |
| FR-MEDIA-8 | M | Media not attached to an entity within 24h is deleted from storage and the database. |
| FR-MEDIA-9 | M | Deleting an entity soft-deletes its media and hard-deletes from storage after 7 days. |
| FR-MEDIA-10 | M | Public media is served through a CDN on a cookieless domain; private/pending media is served only via short-lived signed URLs. |

## FR-NOTIF — Notifications

| ID | Pri | Requirement |
|---|---|---|
| FR-NOTIF-1 | M | In-app notification centre with unread count, mark-read, mark-all-read and pagination. |
| FR-NOTIF-2 | M | Events: email verified, CoC account verified, claim conflict, dispute opened/resolved, comment on your base, reply to your comment, like milestone, moderation decision, report outcome. |
| FR-NOTIF-3 | M | Transactional email for security-critical events (verification, password reset, email change, suspension, ban). |
| FR-NOTIF-4 | P2 | Per-category notification preferences for in-app and email. |
| FR-NOTIF-5 | P2 | Likes are aggregated ("12 people liked your base") rather than one notification per like. |
| FR-NOTIF-6 | P2 | Optional daily/weekly email digest. |

## FR-MOD — Moderation & reports

| ID | Pri | Requirement |
|---|---|---|
| FR-MOD-1 | M | Any authenticated user can report a profile, CoC account, base, comment, recruitment post, listing or message. |
| FR-MOD-2 | M | A report captures reason code, free-text detail (≤1000), optional evidence URLs/screenshots, reporter, target and a snapshot of the target's content at report time. |
| FR-MOD-3 | M | Reports on the same target are grouped into one case; the case priority rises with distinct reporter count. |
| FR-MOD-4 | M | Case states: `open`, `triaged`, `assigned`, `actioned`, `dismissed`, `escalated`. |
| FR-MOD-5 | M | Moderator actions: hide content, remove content, warn user, restrict user (limited actions), suspend (timed), ban (permanent), dismiss. Each writes a moderation-action record. |
| FR-MOD-6 | M | Every admin/moderator action on another user's data writes an audit-log entry. |
| FR-MOD-7 | M | Reporters are notified of outcome categories, never of the specific sanction. |
| FR-MOD-8 | M | Users are notified of sanctions with reason and appeal instructions. |
| FR-MOD-9 | M | Abusive reporting (repeated dismissed reports) is itself rate-limited and reportable. |
| FR-MOD-10 | P2 | Appeals queue with a distinct reviewer from the original decider. |

## FR-SEARCH — Search & discovery

| ID | Pri | Requirement |
|---|---|---|
| FR-SEARCH-1 | M | Global search across players, CoC accounts and bases with a single input and grouped results. |
| FR-SEARCH-2 | M | Exact tag lookup (`#ABC123`) short-circuits to the account page. |
| FR-SEARCH-3 | M | Faceted base search: TH, category, tags, has-video, sort. |
| FR-SEARCH-4 | P2 | Clan and recruitment search with structured filters ("TH16 looking for clan", "Philippines clans", "Champion CWL clan"). |
| FR-SEARCH-5 | M | Search is implemented behind a `SearchService` interface so the engine can be swapped without touching callers. |
| FR-SEARCH-6 | M | Search results exclude hidden, removed and private content, enforced in the query, not in the view. |

## FR-ADMIN — Admin panel

| ID | Pri | Requirement |
|---|---|---|
| FR-ADMIN-1 | M | Admin area at `/admin`, gated by role, with its own layout and no game-styling flourishes. |
| FR-ADMIN-2 | M | Manage: users, CoC accounts, claims, disputes, bases, comments, media, reports, recruitment posts, listings. |
| FR-ADMIN-3 | M | Suspend/ban with reason, duration and internal note; the sanction is visible on the user record. |
| FR-ADMIN-4 | M | Read-only moderation log and audit log with filters by actor, target, action and date. |
| FR-ADMIN-5 | M | Dashboard: open reports, pending disputes, failed jobs, API sync health, new signups, media storage usage. |
| FR-ADMIN-6 | M | Impersonation is **not** available. Support questions are answered from data, not by logging in as a user. |
| FR-ADMIN-7 | P2 | Feature flags and site-wide banner from the admin UI. |

---

## Feature breakdown by surface

### Public (no auth)
Home feed · base detail · public profiles · public CoC account cards · search · recruitment
browse (P2) · marketplace browse (P3) · legal pages · login/register.

### Authenticated user
Dashboard · attach/verify/manage CoC accounts · publish and manage bases · likes, bookmarks,
comments · notification centre · settings (profile, privacy, security, sessions, notifications) ·
report content · recruitment posts and applications (P2) · marketplace orders (P3).

### Moderator
Report queue · case detail with target snapshot · hide/remove/warn/restrict · comment moderation ·
media review queue · duplicate-base queue.

### Admin
Everything a moderator has, plus: user management, suspensions and bans, claim and dispute
resolution, ownership transfers, seller approvals, tag and category management, audit log.

### Super admin
Everything an admin has, plus: role assignment, moderator management, destructive data operations,
system settings, feature flags.
