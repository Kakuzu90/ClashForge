Act as a senior software architect, product engineer, and system designer.

I want to build a Clash of Clans community web platform. I need you to PLAN the project only for now. Do not start writing implementation code yet.

## Project Goal

Create a community platform where Clash of Clans players can:

* Create a website account
* Connect multiple Clash of Clans accounts using player tags
* Share base layouts
* Upload screenshots and replay videos
* Find clans or recruit players
* Build public player/community profiles
* Offer allowed Clash of Clans-related services through a marketplace

The system should be scalable, secure, and designed so new community features can be added later.

## 1. User Accounts

Users should be able to:

* Register
* Login/logout
* Verify email
* Reset password
* Edit profile
* Upload avatar
* Configure privacy settings
* Manage multiple Clash of Clans accounts

Each website user may attach unlimited CoC accounts.

## 2. Clash of Clans Accounts

Users can add accounts using their CoC player tag.

Example:

`#ABC123XYZ`

Store useful information from the official Clash of Clans API where available, including:

* Player tag
* IGN
* Town Hall level
* XP level
* Trophies
* Best trophies
* War stars
* Clan
* Clan role
* Heroes
* Troops
* Spells
* Hero equipment
* League

The player tag must be globally unique in our platform.

If another user already claimed the same tag:

* Do not automatically attach it.
* Ask the new user to verify ownership where possible.
* Otherwise allow them to submit an ownership dispute.
* Admins can review disputes.
* Every ownership transfer must have an audit log.

Account states should include:

* Unverified
* Verified
* Disputed
* Suspended

Users may also upload a limited number of custom screenshots/images for each CoC account.

## 3. Public Player Profiles

Each website user should have a public profile containing:

* Username
* Avatar
* Bio
* Connected CoC accounts
* Verified badge
* Featured account
* Uploaded bases
* Clan information
* Followers/following if appropriate
* Achievements/badges
* Activity
* Statistics

Design this so it can later become a social profile for CoC players.

## 4. Base Layout Sharing

Users can publish CoC base layouts.

Each layout should support:

* Title
* Description
* Town Hall level
* Base type/category
* Base link
* Tags
* Maximum 2 screenshots
* Maximum 1 replay video
* Replay video duration and file-size limits
* Visibility
* Created date
* Updated date

Possible categories:

* War
* CWL
* Farming
* Trophy
* Legend League
* Anti-3-Star
* Anti-2-Star
* Hybrid
* Progress Base
* Funny/Troll

Community features:

* Likes
* Comments
* Bookmarks
* Views
* Copy-base clicks
* Reports
* Creator profile
* Trending/popular bases

Design appropriate anti-abuse mechanisms.

## 5. Recruitment

Support both:

### Looking for Clan

Players can create recruitment profiles including:

* Town Hall
* Trophies
* Location
* Language
* Activity
* War preference
* CWL interest
* Competitive/casual preference
* Preferred clan level/league

### Clan Recruitment

Clan owners/recruiters can post:

* Clan information
* Required TH
* Required trophies
* Required activity
* War frequency
* CWL league
* Clan Capital information
* Language
* Location
* Description
* Recruitment status

Players should be able to show interest/apply.

## 6. Marketplace

Do NOT design a marketplace for buying, selling, trading, sharing, or transferring Clash of Clans accounts.

Instead design a marketplace for allowed community services such as:

* Custom base layouts
* Base reviews
* Coaching
* Clan graphics
* Banners
* Video editing
* Tournament graphics
* Other permitted gaming-related services

Marketplace features may include:

* Seller profiles
* Listings
* Orders
* Buyer/seller messaging
* Order status
* Reviews
* Disputes
* Admin moderation

I am also considering a middleman/escrow-style workflow for permitted services.

Design this carefully and identify legal/payment/provider risks before recommending an implementation.

## 7. Media Storage

Images and replay videos should NOT be stored directly inside the relational database.

Design an object-storage architecture.

Suggested starting limits:

Account:

* Maximum 5 images
* Maximum 5 MB/image

Base:

* Maximum 2 images
* Maximum 5 MB/image
* Maximum 1 video
* Around 30–60 seconds
* Maximum around 50–100 MB

Include:

* Upload validation
* MIME checking
* File signatures
* Virus/malware considerations
* Signed/private upload URLs
* Thumbnail generation
* Video processing strategy
* CDN
* Storage cleanup
* Orphaned file handling

## 8. Admin Panel

Admins should be able to manage:

* Users
* CoC accounts
* Ownership claims
* Ownership disputes
* Base layouts
* Recruitment posts
* Marketplace listings
* Reports
* Comments
* Uploaded media
* Suspensions
* Bans
* Moderation logs
* Audit logs

Use role-based access control.

Possible roles:

* User
* Moderator
* Admin
* Super Admin

## 9. Reports and Moderation

Users should be able to report:

* Players
* Profiles
* Bases
* Comments
* Recruitment posts
* Marketplace listings
* Messages

Include:

* Report reasons
* Evidence
* Status
* Assigned moderator
* Moderator decision
* Audit trail

Also plan protections against:

* Spam
* Bots
* Scam attempts
* Fake ownership claims
* Malicious uploads
* Rate-limit abuse
* Mass scraping
* Duplicate content

## 10. Notifications

Plan notification support for:

* Account verification
* Ownership disputes
* Comments
* Likes
* Recruitment applications
* Clan interest
* Marketplace orders
* Messages
* Reports
* Admin decisions

Support in-app notifications first.

Email notifications can be optional.

## 11. Search and Discovery

Users should eventually be able to search/filter:

* Players
* CoC accounts
* Bases
* Clans
* Recruitment posts
* Marketplace listings

Examples:

`TH17 Anti-3-Star`

`Philippines clans`

`Champion CWL clan`

`TH16 looking for clan`

Design search so we can initially use the database and later migrate to a dedicated search engine if required.

## 12. Suggested Tech Stack

Evaluate this stack:

* Laravel
* PHP
* PostgreSQL or MySQL
* Queue workers
* Laravel Scheduler
* Official Clash of Clans API
* Cloudflare R2 or similar object storage
* CDN
* Blade, Livewire, or Inertia for frontend
* Laravel Cache, Queue, and Session using the `database` driver for the MVP
  (no Redis at launch, to keep infrastructure cost low)
* All code must use Laravel's Cache/Queue facades only, never driver-specific calls,
  so we can switch to Redis later by changing .env
* Note where Redis will become necessary (e.g. rate limiting at scale,
  heavy queues, real-time features) and what signals should trigger the switch

Recommend whether I should use:

* Laravel + Livewire
* Laravel + Inertia + Vue/React
* Separate API + frontend

Explain the tradeoffs based specifically on this project.

## 13. Clash of Clans API Integration

Design a proper API integration layer.

Consider:

* API credentials
* IP restrictions
* Rate limits
* Caching
* Background synchronization
* Failed requests
* API downtime
* Stale data
* Manual refresh
* Scheduled refresh
* Player verification
* Clan synchronization

Do not couple the application directly to the external API.

Use a service/provider architecture.

## 14. Database Design

Create a detailed database/schema proposal.

At minimum consider:

* users
* profiles
* coc_accounts
* coc_account_claims
* coc_account_snapshots
* clans
* clan_memberships
* base_layouts
* base_media
* base_tags
* base_comments
* base_likes
* base_bookmarks
* recruitment_posts
* recruitment_applications
* marketplace_listings
* marketplace_orders
* marketplace_reviews
* conversations
* messages
* notifications
* reports
* moderation_actions
* audit_logs
* media

For every table provide:

* Purpose
* Important columns
* Relationships
* Unique constraints
* Important indexes

Avoid unnecessary over-normalization.

## 15. Security

Treat security as a first-class requirement.

Plan protection against:

* SQL injection
* XSS
* CSRF
* IDOR
* Broken authorization
* File upload attacks
* Mass assignment
* API abuse
* Brute force attacks
* Account enumeration
* Session attacks
* Spam
* Bot registration
* Privilege escalation
* Improper admin access
* Marketplace scams

Use policies/permissions rather than scattered authorization logic.

## 16. Architecture

Propose an architecture appropriate for an MVP but capable of scaling.

Prefer a modular monolith initially unless there is a strong reason otherwise.

Define suggested modules/domains such as:

* Authentication
* Users
* CoC Integration
* Player Accounts
* Bases
* Clans
* Recruitment
* Marketplace
* Messaging
* Media
* Notifications
* Moderation
* Admin

Explain module boundaries and responsibilities.


## 17. UI/UX and Design System

The platform must feel like a game companion app, not a generic dashboard or
starter-kit template. Do NOT use Laravel Breeze/starter-kit default styling or
default shadcn/ui theme values.

### Visual direction
* Game-inspired: bold, chunky, tactile, rewarding
* Must be ORIGINAL. Do not use Supercell's fonts, logos, character art, icons,
  or game UI assets. Create our own visual language inspired by the genre.
* Usability first: game vibe on cards, profiles, badges, and highlights;
  forms, tables, and admin screens stay clean and readable.

### Design tokens
* Heading font: Lilita One (fallback: system bold sans)
* Body font: Inter
* Colors:
  - Background: #0E1220 (deep navy)
  - Surface: #161B2E
  - Surface raised: #1E2540
  - Border: #2A3150
  - Primary (gold): #F5B800
  - Accent (purple): #B04CFF
  - Success: #3DDC84
  - Danger: #FF4D4D
  - Text: #F2F4FA / muted #8A93B2
* Radius: 12px on cards, 10px on buttons (chunky, not sharp)
* Depth: buttons use a solid darker bottom border (4px) that compresses on
  press, instead of soft drop shadows
* Dark mode is the default. A light theme is optional and comes later.

### Signature components
* Player card: profile shown like a collectible card (avatar, IGN, TH badge,
  trophies, league, verified badge)
* Base card: screenshot, TH badge, category tag, likes/copies, creator
* Town Hall badge: a distinct color per TH level range
* Stat blocks: large numbers with icons (trophies, war stars, XP)
* Resource-style counters for likes, views, and copies
* Recruitment cards: clan badge, requirements as pill tags, status indicator

### Motion and feedback
* Button press compression, card hover lift
* Like/bookmark: a small "pop" animation
* Stats count up on first view
* Reward-style toasts for achievements, verification success, and new badges
* All motion respects prefers-reduced-motion

### Layout
* Mobile-first (most players browse on their phones)
* Mobile: bottom tab navigation (Home, Bases, Recruit, Market, Profile)
* Desktop: left sidebar plus top bar with global search
* Content max width: 1200px

### Accessibility
* WCAG AA contrast minimum
* Information is never conveyed by color alone (TH badges also show the number)
* Keyboard navigable, visible focus states

### Deliverables for this section
* Design token list (for Tailwind config / shadcn CSS variables)
* Component inventory with variants
* Page layouts for: home feed, base detail, player profile, recruitment
  listing, marketplace listing, admin
* Empty, loading (skeleton), and error states for each main page

## 18. Development Phases

Create a realistic phased development plan.

Suggested direction:

### Phase 1 — Foundation

Authentication, profiles, roles, CoC integration.

### Phase 2 — Player Accounts

Multiple accounts, verification, public profiles.

### Phase 3 — Bases

Base layouts, images, video, likes, bookmarks.

### Phase 4 — Recruitment

Clan and player recruitment.

### Phase 5 — Community

Comments, notifications, following, reports.

### Phase 6 — Marketplace

Permitted service marketplace.

### Phase 7 — Advanced

Search, recommendations, achievements, analytics, etc.

Improve this roadmap where necessary

## Deliverables

Give me:

1. Product overview
2. MVP definition
3. Functional requirements
4. Non-functional requirements
5. User roles and permissions
6. Complete feature breakdown
7. Recommended architecture
8. Recommended Laravel/frontend stack
9. Database schema
10. Main entity relationships
11. API integration architecture
12. Media/storage architecture
13. Authentication/authorization strategy
14. Security requirements
15. Moderation system
16. Recruitment workflow
17. CoC account claiming workflow
18. Marketplace workflow
19. Major edge cases
20. Development phases
21. Suggested Laravel module/folder structure
22. Background jobs and scheduled tasks
23. Caching strategy
24. Scaling considerations
25. Risks and assumptions
26. Features that should NOT be part of the MVP

Be opinionated about technical architecture where appropriate.

Prioritize:

* Maintainability
* Security
* Good UX
* Low initial infrastructure cost
* Scalability
* Clean domain boundaries
* Avoiding unnecessary complexity

Do not generate implementation code yet.

The result should be detailed enough that afterward I can convert each phase into specifications and development tasks for an agentic coding workflow.
