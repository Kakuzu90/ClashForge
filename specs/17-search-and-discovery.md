# 17 — Search & Discovery

## 1. Strategy

Postgres does the MVP. A dedicated engine is a Phase 7 migration with explicit triggers, and the
`SearchService` interface exists from day one so the migration touches one package.

```
Livewire search UI ─▶ SearchService (interface)
                          ├── PostgresSearchDriver   (MVP)
                          └── MeilisearchDriver      (Phase 7, if triggered)
```

`SearchService::search(SearchQuery $q): SearchResults` — `SearchQuery` is a typed object
(term, types[], filters, sort, page), `SearchResults` carries typed result DTOs plus facet counts.
No caller ever writes SQL or an engine query.

## 2. What is searchable

| Entity | Searchable text | Filters | Sorts |
|---|---|---|---|
| Player (profile) | username, display_name, bio | country, language, has_verified_account, min_th | relevance, newest, most_bases |
| CoC account | ign, tag, clan_name | th_level, trophies range, league, clan, verified | relevance, trophies, th |
| Base layout | title, description, tags | th_level, category, tags, has_video, min_likes, author | relevance, trending, newest, likes, copies |
| Clan (P2) | name, description, tag | level, war_frequency, cwl_league, capital_hall, country, language, members_available | relevance, level, points |
| Recruitment post (P2) | title, description | type, th, trophies, country, language, activity, war_preference, cwl | bumped, relevance, th |
| Marketplace listing (P3) | title, description | category, price_range, delivery_days, rating | relevance, rating, orders |

Excluded from search always: private/unlisted content, hidden/removed content, `private` profiles,
suspended and banned users' content, unverified CoC accounts (they are not trustworthy data), draft
and processing bases. **Enforced in the query builder**, never as a post-filter on results.

## 3. Postgres implementation

### Text search
- A `search_vector tsvector` column per searchable table, maintained as a **generated column** where
  the source is a single table (`profiles`, `base_layouts`), or by a trigger where it aggregates
  child rows (base tags).
- Weights: `A` = title/name/ign, `B` = tags, `C` = description/bio, `D` = secondary.
- `GIN` index on each `search_vector`.
- Query: `websearch_to_tsquery('english', :term)` — it handles quoted phrases and `-exclusions`
  from user input safely, unlike `to_tsquery`.
- Ranking: `ts_rank_cd(search_vector, query)` combined with a popularity term (see §5).

### Fuzzy matching
- `pg_trgm` extension with GIN trigram indexes on `profiles.username`, `coc_accounts.ign`,
  `clans.name`.
- Used for (a) typo tolerance when FTS returns fewer than 5 results, and (b) "did you mean".
- `similarity()` threshold 0.3, tuned against real queries.

### Tag lookup short-circuit
Input matching `^#?[0289PYLQGRJCUV]{3,12}$` is treated as a player or clan tag: exact lookup first,
then the API if it is unknown locally. This is the highest-intent query on the platform and must
never go through ranking.

### Structured queries
The example queries in the brief are handled by parsing, not by embedding:

| Query | Parsed as |
|---|---|
| `TH17 Anti-3-Star` | `th_level=17` + `category=anti_3_star`, type=base |
| `Philippines clans` | `country=PH` + type=clan |
| `Champion CWL clan` | `cwl_league LIKE 'Champion%'` + type=clan |
| `TH16 looking for clan` | `th_level=16` + type=recruitment_post + `post_type=player` |

A small, explicit `QueryParser` extracts known patterns (`TH\d+`, category names and synonyms,
country names and codes, league names, "looking for clan"/"lfc", "recruiting") into filters, and
passes the remainder as the text term. Parsed filters are shown as removable chips so the user can
see and correct what was inferred — an inferred filter the user cannot see is a bug factory.

### Facets
Facet counts come from a second aggregate query over the same filtered set, capped and cached for
60 seconds per filter signature. Above ~50k matching rows, facet counts switch to estimates
(`reltuples`-based) rather than exact counts.

## 4. Performance rules

| Rule | Reason |
|---|---|
| Keyset pagination (`WHERE (score, id) < (?, ?)`) for feeds, offset pagination only for admin tables | `OFFSET 10000` is a table scan |
| Max 50 results per page, max page 100 for anonymous users | Bounds the worst case and the scraping cost |
| A minimum term length of 2 characters; 1-character terms are rejected | Prevents whole-index scans |
| Every filter combination in the UI must be index-covered; a test asserts `EXPLAIN` shows no seq scan on tables > 10k rows | Catches regressions |
| Search results cached 60 s keyed by the full normalised query signature for anonymous users | Absorbs bursts and bots |
| Autocomplete is a separate, narrower query (prefix match on name fields only, limit 8, 150 ms budget), debounced 250 ms client-side | Autocomplete must never run the full ranking |
| Search is rate-limited: 60/min per IP, 120/min per user | Abuse control |

## 5. Ranking

Relevance alone produces a boring, gameable feed. Base search blends:

```
final = 0.5 * normalized(ts_rank_cd)
      + 0.3 * normalized(trending_score)
      + 0.1 * recency_decay(published_at, half_life = 14 days)
      + 0.1 * author_quality(verified_account, prior_base_performance)
      - penalties(duplicate_layout_cluster, reported_and_dismissed_recently)
```

`trending_score` is precomputed in `base_metrics` every 15 minutes:

```
trending = (2*likes + 3*copies + 1.5*comments + 0.1*views) / (hours_since_publish + 2)^1.5
```

Copies weigh more than likes because copying a base is the action that proves value. Views weigh
least because they are the easiest to inflate. The exponent damps old-but-popular content so the
feed stays fresh.

Anti-gaming: likes and copies from accounts younger than 7 days or without a verified CoC account
contribute at 25% weight; self-interactions and interactions from the same IP hash cluster are
excluded from the score (but still shown as counts, to avoid tipping off manipulators).

## 6. Discovery surfaces beyond search

| Surface | Content | Refresh |
|---|---|---|
| Home feed (anonymous) | Trending bases, mixed TH levels, with a TH filter bar | cached 5 min |
| Home feed (logged in) | Trending, filtered to the user's TH ±1 by default, plus followed authors (P2) | cached 60 s per TH bucket |
| "New bases" | Reverse-chronological, published only | cached 60 s |
| Category pages | `/bases/war`, `/bases/farming`, ... | cached 5 min |
| TH pages | `/bases/th17`, high-intent SEO landing pages | cached 5 min |
| Combined | `/bases/th17/anti-3-star` — the canonical SEO surface | cached 5 min |
| Tag pages | `/tags/{tag}` | cached 5 min |
| Creator pages | `/u/{username}` bases tab | cached 60 s |
| Related bases | Same TH ±1, same category, excluding the same author, ordered by trending | cached 15 min |
| Recruitment browse (P2) | Bumped order with filters | cached 60 s |

**SEO requirements:** every discovery surface is server-rendered with a unique title and meta
description, canonical URLs, `ItemList`/`VideoObject`/`Person` JSON-LD where applicable, Open Graph
images (the base screenshot), an XML sitemap regenerated nightly for public bases and profiles, and
`robots.txt` disallowing `/search`, filter permutations and deep pagination.

## 7. Migration trigger and path

Move off Postgres FTS when **any two** of the following hold for two consecutive weeks:

| Signal | Threshold |
|---|---|
| Searchable base documents | > 100,000 |
| Search p95 latency | > 500 ms |
| Search queries | > 20/s sustained |
| Facet query cost | > 200 ms p95 |
| Demand for typo tolerance and multi-language stemming | qualitative, from support/feedback |

**Target:** Meilisearch (self-hosted, single binary, ~200 MB RAM at this scale, excellent typo
tolerance and faceting out of the box, no cluster to operate). Typesense is an equivalent
alternative. Elasticsearch is rejected as far too heavy for this data volume and operational budget.

**Migration plan:**
1. Implement `MeilisearchDriver` against the existing `SearchService` interface.
2. Add an `IndexDocument` job dispatched from the same domain events that already fire
   (`BasePublished`, `CocAccountVerified`, profile updates, moderation actions).
3. Backfill with a console command.
4. Run both drivers in shadow mode: serve Postgres results, log Meilisearch results, compare.
5. Flip with a feature flag, per entity type, starting with bases.
6. Keep the Postgres driver as the fallback for one release cycle; the vectors and indexes stay.

**What does not move:** exact tag lookup, admin tables and anything requiring transactional
consistency stay on Postgres. Search engines are for ranking and faceting, never the source of
truth.
