# 01 — Product Overview & MVP Definition

## 1. Product overview

**Clash Commons** is a community platform for Clash of Clans players. A player creates one website
account, attaches any number of verified in-game accounts to it, and uses that identity across the
site: sharing base layouts, finding a clan or recruiting for one, building a public profile, and
later selling permitted creative services.

### The problem

The CoC community is fragmented across Reddit, Discord, YouTube and link-dump base sites. Three
concrete gaps:

1. **No portable identity.** A player's reputation lives in a Discord server and dies with it. There
   is no verified, public "this is my TH17 account, these are my bases, this is my history" page.
2. **Base sharing is low-trust.** Base link sites rarely verify who made a layout, rarely show the
   creator's own TH level, and are saturated with reposts.
3. **Recruitment is manual and unstructured.** Clan recruitment happens as free text in chat, with
   no filters for TH level, war frequency, language or timezone.

### The wedge

**Verified account ownership.** The official Clash of Clans API exposes a player-token verification
endpoint, which lets us prove a user controls a given player tag. Every other feature gets its trust
from that: a verified TH17 badge on a base card, a recruitment post that cannot lie about trophies,
a marketplace seller whose in-game credentials are real.

### Positioning

- Not a stats tracker (ClashOfStats, ClashNinja already do this well). Stats are *context* on a
  profile, not the product.
- Not a Discord replacement. Real-time chat is out of scope; the platform is the durable,
  searchable, linkable layer under the chat.
- Not a marketplace for accounts. Account sales are explicitly prohibited and actively moderated.

### Success metrics (first 6 months post-launch)

| Metric | Target | Why it matters |
|---|---|---|
| Verified CoC accounts / registered users | > 0.7 | Verification is the wedge; a low ratio means the flow is broken |
| Weekly active base publishers | > 50 | Content supply is the hardest side of the marketplace |
| Base copy-link clicks / base view | > 0.15 | Proves bases are actually useful, not just browsed |
| Recruitment posts with ≥1 application | > 40% | Proves the matching filters work |
| Median report resolution time | < 24h | Moderation capacity keeps pace with growth |
| Infra cost / MAU | < $0.01 | The no-Redis, single-VPS bet holds |

---

## 2. MVP definition

The MVP is **Phases 1–3** of [25-development-phases.md](25-development-phases.md): identity,
verified CoC accounts, public profiles, and base sharing. Recruitment (Phase 4) is the first
post-MVP milestone and should be scoped during Phase 3.

### MVP scope — in

**Identity & accounts**
- Email/password registration, login, logout, email verification, password reset.
- Profile: username (unique, immutable for 30 days after change), display name, bio, avatar,
  country, languages, socials.
- Privacy settings: profile visibility (public / logged-in only), show-or-hide connected accounts,
  show-or-hide clan.
- Roles: user, moderator, admin, super admin.

**CoC accounts**
- Attach a player tag; fetch the profile from the official API.
- Ownership verification via the official player API token (`POST /players/{tag}/verifytoken`).
- One tag maps to at most one *verified* owner, platform-wide.
- Claim conflicts route to a dispute queue with an admin decision and an immutable audit trail.
- States: `unverified`, `verified`, `disputed`, `suspended`, `released`.
- Snapshot history so a profile survives API downtime and can show progression.
- Featured account per user. Up to 5 custom images per CoC account.

**Public profiles**
- `/u/{username}` with avatar, bio, verified badge, featured account card, connected accounts,
  published bases, basic stats, join date.
- Respects privacy settings; unverified accounts are visibly labelled.

**Base layouts**
- Publish with title, description, TH level, category, base link (validated `link.clashofclans.com`),
  up to 10 tags, up to 2 screenshots, optional 1 replay video (≤60s, ≤100 MB), visibility.
- Likes, bookmarks, views, copy-link counter, comments (flat, 1 level of replies).
- Feed with filters (TH, category, tag, sort) and a trending ranking.
- Reporting on bases and comments; moderator queue; hide/remove actions.

**Platform**
- In-app notifications for verification, disputes, comments, likes and moderation decisions.
- Media pipeline on R2 with signed uploads, validation, thumbnails and orphan cleanup.
- Admin panel: users, CoC accounts, claims/disputes, bases, comments, media, reports, moderation
  log, audit log.
- Postgres-backed search across players, CoC accounts and bases.

### MVP scope — out

Deferred deliberately; see [25](25-development-phases.md) for when each returns.

| Deferred | Why |
|---|---|
| Marketplace + any payment handling | Legal, PSP and fraud exposure out of proportion to MVP value ([15](15-marketplace-workflow.md)) |
| Direct messaging between users | Highest-abuse surface on the platform; needs moderation capacity that does not exist yet |
| Following / social graph / activity feed | Meaningless before there is content and a user base to follow |
| Clan pages & clan sync | Only needed once recruitment ships; clan data is expensive to keep fresh |
| Achievements & badges beyond "verified" | Pure retention polish; needs real activity data to calibrate |
| Dedicated search engine (Meilisearch/Typesense) | Postgres handles the first ~100k rows comfortably |
| Real-time anything (websockets, presence, live chat) | Requires Redis + a socket server; kills the cost model |
| Native mobile apps | The site is mobile-first and installable as a PWA; native adds no MVP value |
| OAuth social login | Adds provider risk and account-linking edge cases for marginal signup lift |
| Multi-language UI | English only at launch; the schema stores user language for matching, not for i18n |
| Base layout image recognition / duplicate detection by pixel | Expensive; start with link-hash duplicate detection |
| Tournaments, events, esports features | Whole product of its own |

### MVP exit criteria

The MVP is done when all of the following hold on production:

1. A new user can register, verify email, attach and verify a CoC account, publish a base with two
   screenshots and a video, and see it on their public profile — with no manual admin step.
2. A second user attempting the same tag is blocked and can open a dispute that an admin resolves,
   producing an audit log entry and a transfer.
3. A report on a base reaches a moderator queue and can be actioned, with a moderation log entry
   and a notification to the reporter.
4. All media lives on R2; the database contains no binaries; orphan sweeps run nightly.
5. p95 page render < 400 ms for the base feed with 10k bases seeded.
6. CoC API outage for 30 minutes degrades gracefully: profiles render from snapshots with a
   "last updated" timestamp, no 5xx.
7. Automated test suite green on both SQLite and Postgres in CI.

---

## 3. Product principles

1. **Verification is the moat.** Any feature that can be gated on a verified account should be.
2. **Cheap to run, boring to operate.** One VPS, one database, no daemons we don't need. Every
   dependency must pay for itself.
3. **Mobile-first, always.** Most players browse on a phone between attacks. If it doesn't work at
   375 px, it doesn't ship.
4. **Moderation is a feature, not an afterthought.** Every user-generated surface ships with a
   report path and a moderator action on day one.
5. **Compliance is non-negotiable.** Supercell's Fan Content Policy and Terms of Service bound the
   product. No account trading, no gambling, no paid advantage. Game assets identify game content
   and nothing else; the platform's own identity is original ([18 §2](18-design-system.md)).
6. **The domain layer does not know about HTTP or the CoC API.** Swappable edges, stable core.
