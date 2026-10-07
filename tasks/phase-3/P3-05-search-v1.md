---
id: P3-05
title: Build search v1 (Postgres FTS over players, accounts and bases, tag short-circuit, query parser, /search)
phase: 3
status: done
depends_on: [P3-01]
---

# Search v1

## Spec refs
- Core: specs/17 §1 (`SearchService` interface, `SearchQuery` → `SearchResults`), §2 (searchable entities, filters, sorts; the exclusion list enforced in the query builder), §3 (`search_vector` + GIN, weights A–D, `websearch_to_tsquery`, `ts_rank_cd`; tag short-circuit `^#?[0289PYLQGRJCUV]{3,12}$`; `QueryParser` with visible removable chips; facets from a second aggregate query, cached 60 s), §4 (keyset pages, ≤ 50 per page, anonymous max page 100, minimum term 2, index-covered filters with an `EXPLAIN` test, anonymous results cached 60 s, rate limit), §5 (base ranking blend)
- Plus: specs/07 `profiles.search_vector` (trigger, not generated, since `username` is on `users`), `base_layouts` GIN on `search_vector`, `coc_accounts` (`ign`, `tag_normalized`, existing `coc_accounts_ign_search_index`); specs/05 module table (Search owns no tables, `SearchService`, `IndexableContract`); specs/19 §4 (`/search` route file), §7 (`search:reindex {type?}`); specs/21 §3 (`search:{signature}`, 60 s, anonymous only); specs/04 §4 (`search` limiter); specs/11 §2 (raw SQL only with bindings, `Utf8Text` on free text; profile visibility respected in search); specs/18 §5 (mobile Search tab slot, desktop search entry), §4 (BaseCard, PlayerCard `mini`, Chip, Tabs, EmptyState)
- FR: FR-SEARCH-1, FR-SEARCH-2, FR-SEARCH-3, FR-SEARCH-5, FR-SEARCH-6
- Edge cases: specs/23 §2 (two accounts with the same IGN: the tag shows beside every IGN), §9 (search never returns hidden content: same-transaction vectors)

## Scope
- **Migrations:** `profiles.search_vector` + GIN, kept by triggers on `profiles` and on `users.username`; `base_layouts.search_vector` + GIN (title A, tags B, description C), kept by triggers on `base_layouts` and `base_tags`; `coc_accounts` reuses its `ign` index plus `tag_normalized`. Backfill in the migration (Postgres only; SQLite skips).
- **Domain:**
  - Search: `Contracts\SearchService` (`search`, `hasSearchableText`, `acceptsCursor`, `reindex`), `Contracts\SearchSource` (Q2), `Contracts\TagLookup`, `Data\SearchQuery` (term, type, filters, cursor), `Data\SearchResultsData` + `SearchSectionData` (hits + facets), `Data\SearchCursor`, `Services\QueryParser` (`TH\d+`, category names and synonyms → filters, the rest → term; the tag), `Services\TextQuery`, `Services\PostgresSearchDriver` (cache inline).
  - Sources (one per module, through Contracts): Users `PlayerSearchSource` + `SearchVisibility`, PlayerAccounts `AccountSearchSource` (also `TagLookup`), Bases `BaseSearchSource` (feed filters + facets) with `Queries\BaseListing` (shared with the feed) and `Queries\BaseSearchRanking` (the specs/17 §5 blend, Q6).
  - Console: `search:reindex {type?}` rebuilds the vectors.
- **Http:** `SearchController` + `SearchRequest` (term 2–100 chars, `Utf8Text`, types, base filters reuse `BaseFeedRequest` rules, cursor); `routes/web/search.php`; `search` limiter (Q7). An exact, visible tag redirects to `/accounts/{ulid}` (Q5).
- **UI:** `Pages/Search/Index.vue` (Q8): search input, parsed-filter chips, All / Bases / Players / Accounts tabs, grouped sections with "See all", per-tab lists with load-more, the `/bases` filter panel with facet counts on the Bases tab; `noindex`. Nav: the mobile Search tab slot and the desktop search entry link to `/search`.
- **Config:** `platform.search.{per_page, group_size, min_term, max_term, max_pages, cache_ttl, facet_cache_ttl, snippet_length}`, `platform.search.rate_limits.{per_ip, per_user}`, `platform.search.ranking.*` (decision 1).

## Out of scope
- Header autocomplete with the `/` shortcut, `pg_trgm` typo tolerance and "did you mean" (split to P3-12, Q1).
- Clan and recruitment search and their parsed queries (FR-SEARCH-4, Phase 4); the CoC API fallback for unknown tags (Q5); `IndexSearchDocumentJob` and Meilisearch (Phase 7, specs/17 §7).
- The reported-and-dismissed penalty (P3-06) and author quality from interaction counters (P3-04) (Q6).

## Acceptance criteria
- Functional: FR-SEARCH-1 (one input, grouped results); FR-SEARCH-2 (tag → account page); FR-SEARCH-3 (TH, category, tags, has video, sort, facet counts); FR-SEARCH-5 (controllers only see `SearchService`); FR-SEARCH-6.
- Authorization: public, read-only. The visibility matrix (Q4) holds in the query: no private or members-only (for guests) or non-searchable profiles, no unverified, released or suspended accounts, no unlisted, private, unpublished, hidden or removed bases, nothing by a suspended, banned or deleting user. A hidden account's tag gives the same "not found" as an unknown one.
- Edge cases: same-IGN accounts each show their tag; a 1-character term is a field error; a tampered cursor is a field error, not a 500; `-word` and `"phrase"` input never errors.
- States: empty (no term: prompt + example queries), no results (+ clear filters), loading skeletons, error card with retry; 375 px and desktop.

## Tests
- Feature (Postgres; FTS tests skip on SQLite): `assertInertia` prop shape with no private fields; ranking order; each base filter, facets and sort; keyset paging without repeats; the anonymous page cap; the visibility matrix; tag redirect and hidden-tag miss; anonymous cache hit; the limiter; the vector triggers (username, tag, description edits); `EXPLAIN` uses the GIN indexes.
- Security: SQL metacharacters, NUL bytes and invalid UTF-8 in the term; oversized terms; a guest cannot find a `members` profile.
- Unit: `QueryParser` (TH, categories, synonyms, remainder), `TagLookup`, the query signature, the ranking terms.
- Vitest: query ↔ URL state, chip removal, tab switching.

## Notes

### Open questions
Resolved by the owner, 2026-10-07 (all as recommended); specs synced at implement → Finish.
1. **Split** (synced → specs/25 §4). specs/25's "Search v1" plus specs/17 §3–4 do not fit one page. Recommended: this task (vectors, `SearchService` + Postgres driver, tag short-circuit, `QueryParser`, `/search`), then **P3-12** (the header search box with the `/` shortcut and an 8-result autocomplete, specs/17 §4; `pg_trgm` indexes, typo fallback under 5 results and "did you mean", specs/17 §3). P3-12 row added to the board, depending on P3-05.
2. **Where the vectors live** (synced → specs/05 §2, specs/17 §1, §3, specs/07). specs/05 gives Search a `search_documents` materialised view; specs/17 §3 and specs/07 put a `search_vector` on each table. Recommended: per-table vectors (fresh in the same transaction, which specs/23 §9 relies on; no refresh job). Search may not read other modules' tables, so it defines `Contracts\SearchSource` and each module registers an implementation (tagged in its provider). Sync specs/05.
3. **Text configuration** (synced → specs/17 §3, specs/07). `english` stems titles and bios but mangles IGNs and usernames. Recommended: names and tags use `simple`, title, description and bio use `english`, and the term is matched as `websearch_to_tsquery('simple', …) || websearch_to_tsquery('english', …)`. Sync specs/17 §3.
4. **Visibility matrix** (specs/17 §2, specs/11 §2; implemented in the sources, no spec text changed). Recommended:
   - Players: profile `public` (or `members` for a signed-in viewer), `searchable` on, user not hidden.
   - Accounts: `verified` or `disputed`, the owner not hidden, the owner's profile visible to the viewer with `show_coc_accounts` and `searchable` on.
   - Bases: the feed's rule (published, `public`, author not hidden).
   - `searchable` off hides the player and their accounts, not their public bases.
5. **Unknown tags** (synced → specs/17 §3). specs/17 §3 falls back to the CoC API for a tag unknown locally, but no page shows an unattached account, and clans have no page until Phase 4. Recommended: local only. A visible account redirects; anything else stays on `/search` with "No player with #TAG on Clash Commons yet" (plus "Attach your account" when signed in). The API fallback and clan tags move to Phase 4. Sync specs/17 §3.
6. **Ranking inputs** (synced → specs/17 §5). specs/17 §5 leaves the normalisation open, and two inputs do not exist yet. Recommended:
   - `ts_rank_cd(…, 32)` (rank / (rank + 1)), trending as `t / (t + search.ranking.trending_pivot)`, recency `0.5^(age_days / 14)`.
   - Author quality reads 0 until P3-04 keeps the counters; the reported penalty arrives with P3-06 (both appended to their board rows). The duplicate penalty is already inside `trending_score`.
   - Players sort by rank then newest, accounts by rank then trophies.
7. **Rate limit** (synced → specs/04 §4, specs/17 §4). specs/04 says `search` 60/min per IP; specs/17 §4 says 60/min per IP and 120/min per user. Recommended: specs/17 (user 120, else IP 60), sync specs/04.
8. **Page layout** (synced → specs/18 §5, §6; facets → specs/17 §3). specs/18 §6 has no search page. Recommended: as in Scope → UI. "All" shows 6 bases, 5 players and 5 accounts with "See all" links. Each tab keeps its filters in the URL (`q`, `type`, base filters), the Bases tab reuses `BaseFeedFilters` with counts beside TH and category, and a parsed filter shows as a chip ("TH 17 ×"). Facets are exact counts; the >50k estimate switch stays a Phase 7 note. Sync specs/18 §6.

### Decisions while implementing
1. (synced → specs/19 §5) **Config** lives under `platform.search.*`, not `search.*`: `docs/ai/rules/backend.md` lists the config files limits go in.
2. (synced → specs/17 §1, specs/05 §2) **Interface.** `SearchService::search(SearchQuery, ?User $viewer)`. The viewer decides whether `members` profiles are listed. Two more methods:
   - `acceptsCursor()` lets the Form Request turn a bad cursor into a field error;
   - `reindex(?SearchType)` backs `search:reindex`.

   Sources implement `Search\Contracts\SearchSource` (tagged `search.sources`). PlayerAccounts also binds `Search\Contracts\TagLookup`.
3. (synced → specs/07, specs/17 §3, specs/20 §2) **Vectors** are kept by triggers, Postgres only:
   - `profiles` (username A, display name A, bio C) is updated by `profiles` and `users.username` triggers;
   - `base_layouts` (title A, tag names B, description C) is updated by `base_layouts`, `base_layout_tag` and `base_tags.name` triggers.

   The functions use `CREATE OR REPLACE`, because `migrate:fresh` keeps functions. CoC accounts reuse the existing `to_tsvector('simple', ign)` index. Clan name and tag text are not searched (Phase 4).
4. (synced → specs/17 §5, specs/05 §2) **Base sort.** "Best match" is a new `FeedSort::Relevance` case, and `/search` defaults to it. The feeds offer `FeedSort::feed()` only. The feed and search share `Bases\Queries\BaseListing`, which covers listed bases, the filters and the cards.
5. (synced → specs/17 §3) **Parser.**
   - It reads one Town Hall (`TH17`, `th 17`, `Town Hall 17`, within `bases.th_min`–`th_max`) and one category, from its name or a synonym, longest phrase first.
   - Once a filter is read, the words base(s), layout(s) and link(s) are dropped.
   - A filter set explicitly in the URL beats the parsed one, and then no chip shows.
   - A parsed Town Hall also filters accounts.
   - A bare tag counts as a tag only when written in capitals, as in specs/17's regex. With a `#`, any case works. Lowercase words such as "pug" stay text.
6. (synced → specs/17 §3, specs/18 §6) **Text with no words left.** When parsing leaves no words (`TH17 Anti-3-Star`), only bases are searched. The Players and Accounts tabs then say "Add a name to search…".
7. (synced → specs/17 §5) **Keyset.**
   - "Best match" pages on the score computed in a subquery, with the ranking time stored in the cursor so recency does not drift between pages.
   - Players page on (rank, id); accounts on (rank, trophies, id).
   - The cursor is encrypted and bound to the search signature: type, remaining text, parsed Town Hall and filters.
8. (synced → specs/21 §3, specs/17 §3) **Cache.** Anonymous first pages are cached under `search:{sha256(signature)}` for 60 s. Facet counts are cached for everyone under `search:facets:{…}`, also 60 s. Each facet ignores its own filter.
9. **Bad input.** A bad parameter redirects to `/search`, keeping `q` when `q` itself is valid.
10. (synced → specs/18 §5) **Navigation.** The mobile bottom tab Search links to `/search`, but is not in the desktop text nav (`tabOnly`). The top bar has a search icon link at every width.
11. (synced → specs/17 §3) **Exclusion-only text** (security review). `-zz`, `ring or -zz` or punctuation alone would match every row and scan the table, so `TextQuery::indexable` asks Postgres's `querytree`. Text with nothing indexable left is a `q` field error, unless it also named a filter: `TH16 -zz` then searches bases by Town Hall only. The redirect keeps `q` only when `q` itself passed, so it never loops.
12. **Tests.** `phpunit.xml` sets `memory_limit=1G`. The serial Postgres run keeps every test file in one process, and the architecture tests parsing all of `app/` at the end went past the 512 MB CLI default once this task's classes were added.

### Owner feedback
- 2026-10-07: account hits use the profile's standard PlayerCard (XP level, name, tag, league) instead of a search-only row. `AccountSearchSource` returns `PlayerCardData` through `AccountReadModel::cards()`; `AccountHitData` and `SearchAccountRow` are gone (synced → specs/18 §6).
