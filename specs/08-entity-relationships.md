# 08 — Entity Relationships

## 1. Core relationship map

```
                          ┌──────────────┐
                          │    users     │
                          └──────┬───────┘
        ┌────────────┬───────────┼────────────┬──────────────┬─────────────┐
        │ 1:1        │ 1:1       │ 1:1        │ 1:N          │ 1:N         │ 1:N
   ┌────▼────┐  ┌────▼─────┐ ┌───▼─────┐ ┌────▼────────┐ ┌───▼────────┐ ┌──▼──────┐
   │profiles │  │ privacy_ │ │  user_  │ │coc_accounts │ │base_layouts│ │ reports │
   │         │  │ settings │ │  stats  │ │             │ │            │ │(reporter)│
   └────┬────┘  └──────────┘ └─────────┘ └────┬────────┘ └───┬────────┘ └─────────┘
        │ avatar                              │               │
        │ N:1                        ┌────────┼──────┐        ├──1:1──▶ base_metrics
   ┌────▼────┐                       │ 1:N    │ N:1  │ 1:N    ├──1:N──▶ base_comments
   │  media  │◀──────────────────────┤        │      │        ├──1:N──▶ base_likes
   │(polymor-│  attachable      ┌────▼─────┐ ┌▼─────┐│        ├──1:N──▶ base_bookmarks
   │ phic)   │                  │snapshots │ │clans ││        ├──M:N──▶ base_tags
   └────┬────┘                  └──────────┘ └──┬───┘│        └──1:N──▶ base_view_events
        │ 1:N                                   │    │
   ┌────▼──────────┐                       1:N  │    │ 1:N
   │media_variants │                    ┌───────▼────▼──────┐
   └───────────────┘                    │ clan_memberships  │
                                        └───────────────────┘

   coc_accounts ──1:N──▶ coc_account_claims ──────┐
   coc_accounts ──1:N──▶ coc_account_disputes ────┴──▶ audit_logs (by action)

   users ──1:N──▶ recruitment_posts ──1:N──▶ recruitment_applications ──N:1──▶ users
   clans ──1:N──▶ recruitment_posts (type=clan)
   coc_accounts ──1:1──▶ recruitment_posts (type=player, one open)

   users ──1:1──▶ seller_profiles ──1:N──▶ marketplace_listings
                                              │ 1:N
                                       marketplace_orders ──1:1──▶ conversations
                                              ├─1:N─▶ marketplace_order_events
                                              ├─1:1─▶ marketplace_reviews
                                              └─0:1─▶ marketplace_disputes

   reports ──N:1──▶ report_cases ──1:N──▶ moderation_actions ──1:1──▶ user_sanctions
   any privileged action ────────────────▶ audit_logs
```

## 2. Cardinalities and ownership rules

| Relationship | Cardinality | Owning side | On owner delete |
|---|---|---|---|
| user → profile | 1:1 | user | cascade |
| user → privacy_settings | 1:1 | user | cascade |
| user → user_stats | 1:1 | user | cascade |
| user → coc_accounts | 1:N | user | **set null + status `released`** — the tag must become reclaimable, and the history must survive |
| user → featured coc_account | N:1 (nullable, circular) | user | set null |
| coc_account → snapshots | 1:N | account | cascade |
| coc_account → claims | 1:N | account (nullable) | set null (claims outlive the account row) |
| coc_account → disputes | 1:N | account | restrict — a disputed account cannot be deleted |
| coc_account → clan | N:1 (nullable) | clan | set null |
| clan → clan_memberships | 1:N | clan | cascade |
| coc_account → clan_memberships | 1:N | account | cascade |
| user → base_layouts | 1:N | user | cascade (author deletion removes their bases) |
| coc_account → base_layouts | 1:N (credit only) | account | set null — the base survives detaching an account |
| base_layout → base_metrics | 1:1 | base | cascade |
| base_layout → comments/likes/bookmarks/views/copies | 1:N | base | cascade |
| base_layout ↔ base_tags | M:N | pivot | cascade both sides |
| comment → replies | 1:N, depth 1 | parent comment | cascade |
| any entity → media | 1:N polymorphic | entity | soft-delete media, hard-delete from storage after 7 days |
| media → media_variants | 1:N | media | cascade (plus storage delete job) |
| user → recruitment_posts | 1:N | user | cascade |
| recruitment_post → applications | 1:N | post | cascade |
| user → applications | 1:N | user | cascade |
| user → seller_profile | 1:1 | user | restrict while orders are open |
| seller_profile → listings | 1:N | seller | soft-delete listings |
| listing → orders | 1:N | listing | **restrict** — order history must not be erasable by delisting |
| order → review | 1:1 | order | restrict |
| order → conversation | 1:1 | order | cascade |
| user → reports (as reporter) | 1:N | user | **restrict / anonymise** — reports survive account deletion |
| report → report_case | N:1 | case | set null |
| report_case → moderation_actions | 1:N | case | restrict |
| user → moderation_actions (as actor) | 1:N | user | restrict |
| user → user_sanctions | 1:N | user | restrict |
| anything → audit_logs | 1:N polymorphic | — | never deleted |

## 3. The three relationships that carry the product

### 3.1 `users` ↔ `coc_accounts` — the trust edge

- A user holds **many** CoC accounts. A tag is held by **at most one verified user at a time**,
  enforced by the partial unique index `UNIQUE (tag_normalized) WHERE status = 'verified'`.
- Multiple *unverified* rows for the same tag may coexist across users. This is intentional: it
  lets people add an account they cannot verify right now (e.g. a second device) without blocking
  the real owner, and it gives the dispute system evidence.
- The relationship is **temporal**: it can move between users. `coc_account_claims` records every
  attempt, `coc_account_disputes` records contested moves, `audit_logs` records the transfer.
  Nothing in the chain is deletable.
- Ownership transfer keeps the same `coc_accounts` row (preserving snapshots and history) and
  changes `user_id`, rather than creating a new row.

### 3.2 `base_layouts` ↔ `users` and `coc_accounts` — authorship vs credit

- `base_layouts.user_id` is **authorship**: who published it. Cascade on delete, never reassigned.
- `base_layouts.coc_account_id` is **credit**: which in-game account is shown on the card. Nullable,
  set-null on account release.
- Consequence to enforce in the service layer: when a base's credited account is transferred to
  another user in a dispute, **the base stays with the original author** and the credit link is
  nulled. Base authorship is not evidence of tag ownership, and transferring content would let a
  fraudulent dispute steal a creator's library.

### 3.3 `media` ↔ everything — the polymorphic lifecycle

- `media` is attached via `attachable_type` + `attachable_id`, but **attachment is the second step**.
  Media is created in `pending` with `user_id` and a `collection`, uploaded directly to R2, then
  bound to an entity when the parent form is submitted.
- Therefore `attachable_id` is nullable and `expires_at` exists: an unattached `pending`/`uploaded`
  row is an orphan candidate after 24 hours.
- Quotas are enforced against the parent entity's counter column (`coc_accounts.images_count`,
  screenshots per base) inside the attach transaction, not at upload time — the upload is
  speculative, the attach is authoritative.

## 4. Polymorphic relationships (deliberate, limited list)

| Table | Polymorphic column | Allowed types | Why polymorphic |
|---|---|---|---|
| `media` | `attachable` | Profile, CocAccount, BaseLayout, MarketplaceListing, Report | Identical lifecycle and sweeping for all; one sweeper job |
| `reports` | `reportable` | Profile, CocAccount, BaseLayout, BaseComment, RecruitmentPost, MarketplaceListing, Message | One report queue, one UI, one reason taxonomy |
| `report_cases` | `reportable` | same | mirrors reports |
| `moderation_actions` | `target` | any moderatable + User | one audit-shaped history |
| `audit_logs` | `auditable` | any | forensic catch-all |
| `notifications` | `notifiable` | User (only, for now) | Laravel convention |
| `conversations` | `subject` | MarketplaceOrder (only, for now) | leaves room for clan/recruitment threads |

**Everything else uses explicit foreign keys.** Polymorphism costs index quality and foreign-key
integrity, so it is spent only where the alternative is five near-identical tables and five
near-identical moderation UIs. A morph map (short string aliases, e.g. `base`, `comment`) is
registered so class renames never break stored data.

## 5. Derived and denormalised data

Every denormalised value has one authoritative writer and one repair job.

| Denormalised | Source of truth | Written by | Repaired by |
|---|---|---|---|
| `base_metrics.likes_count` | `base_likes` | like/unlike transaction | nightly reconcile job |
| `base_metrics.comments_count` | `base_comments` (visible) | comment create/remove | nightly reconcile |
| `base_metrics.views_count` | `base_view_events` | hourly aggregation job | full recount weekly |
| `base_metrics.copies_count` | `base_copy_events` | hourly aggregation job | full recount weekly |
| `base_metrics.trending_score` | metrics + age | scheduled scorer (15 min) | recomputed each run |
| `users.verified_accounts_count` | `coc_accounts` | verification/detach service | nightly reconcile |
| `user_stats.*` | bases, likes, comments | event listeners | nightly recompute |
| `base_tags.usage_count` | pivot | tag sync in publish/unpublish | nightly reconcile |
| `clans.members_count` | CoC API | clan sync job | next sync |
| `coc_accounts.clan_tag` | CoC API | account sync | next sync |
| `report_cases.reports_count` | `reports` | report intake | on case open |
| `seller_profiles.rating_avg` | `marketplace_reviews` | review create/hide | nightly reconcile |
| `profiles.search_vector`, `base_layouts.search_vector` | source text | generated column or trigger | reindex command |

## 6. Delete and anonymisation semantics

Account deletion (FR-AUTH-9) is a 30-day soft delete, then:

| Data | Fate |
|---|---|
| `users` row | retained, anonymised: `username → deleted_user_{ulid}`, email hashed, password nulled, `status='banned'`-equivalent tombstone |
| `profiles` | bio, socials, country cleared; avatar media deleted |
| `coc_accounts` | `user_id` nulled, `status='released'`, snapshots retained, tag reclaimable |
| `base_layouts` | deleted (cascade), media swept |
| `base_comments` | body replaced with a tombstone, row retained so threads stay readable |
| `base_likes`, `bookmarks` | deleted |
| `reports` filed by the user | retained, reporter anonymised |
| `moderation_actions`, `user_sanctions`, `audit_logs` | fully retained — these are the compliance record |
| `marketplace_orders` | retained; buyer/seller pseudonymised after any dispute window closes |

The anonymisation job is idempotent and logs to `audit_logs`.
