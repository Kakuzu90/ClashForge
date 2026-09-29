# 24 — Risks & Assumptions

## 1. Risk register

Scored: **Impact** (1–5) × **Likelihood** (1–5). Anything ≥12 needs a mitigation owner before the
phase that exposes it ships.

### Existential

| # | Risk | I | L | Score | Mitigation |
|---|---|---|---|---|---|
| R1 | **Supercell changes or restricts the API** (removes `verifytoken`, tightens terms, blocks community platforms) | 5 | 2 | 10 | Snapshots mean the site survives without the API. Verification would fall back to manual/dispute-only, which is worse but not fatal. Keep the integration behind an interface; keep nothing business-critical dependent on an endpoint we cannot replace |
| R2 | **Platform is seen as facilitating account trading** and Supercell or a payment provider acts | 5 | 2 | 10 | Explicit prohibition in ToS, listing validation, prohibited-term lexicon, Critical report reason, immediate bans, no payment processing in Stage 1 ([15](15-marketplace-workflow.md)) |
| R3 | **No content supply** — nobody publishes bases, so nobody visits | 5 | 3 | 15 | Seed with invited creators before public launch; make publishing take under 2 minutes; credit creators prominently; consider a launch competition. **This is the biggest realistic failure mode and it is a product problem, not a technical one** |
| R4 | **Moderation capacity is overwhelmed** and the platform becomes a scam venue | 4 | 3 | 12 | Grouped cases, automated pre-moderation, trust ramps, documented staffing trigger ([12 §11](12-moderation-system.md)), ability to disable the marketplace and messaging entirely |

### High

| # | Risk | I | L | Score | Mitigation |
|---|---|---|---|---|---|
| R5 | API egress IP changes break all keys | 4 | 3 | 12 | Health check every 5 min, automated key rotation, manual runbook, graceful degradation |
| R6 | Media costs or abuse spiral (video uploads) | 3 | 3 | 9 | Hard caps, per-user quota, verified-only video if needed, transcode offload path |
| R7 | Legal exposure from marketplace payments | 5 | 2 | 10 | Stage 1 has no payments; Stage 2 gated on legal review ([15 §4](15-marketplace-workflow.md)) |
| R8 | Ownership dispute decisions are wrong and users lose their identity | 4 | 3 | 12 | Token verification is decisive and self-service; bias toward the current holder; full audit trail; appeals |
| R9 | Database-driver queue/cache hits limits earlier than expected | 3 | 3 | 9 | Documented triggers and a one-hour migration path ([21 §7](21-caching-strategy.md)) |
| R10 | Solo/small team burnout on a 7-phase roadmap | 4 | 4 | 16 | Phases are independently shippable; MVP is Phases 1–3 only; nothing after Phase 3 is committed until the MVP has users |

### Medium

| # | Risk | I | L | Score | Mitigation |
|---|---|---|---|---|---|
| R11 | Game updates break data mapping | 2 | 4 | 8 | `jsonb` storage, unknown units preserved, no hardcoded TH max, contract tests |
| R12 | Livewire performance on slow mobile networks | 3 | 3 | 9 | Alpine for local interactivity, minimal round-trips, debounced inputs, performance budgets in CI |
| R13 | Spam and bot registration at launch | 3 | 3 | 9 | Turnstile, email verification, trust ramp, disposable-domain blocklist |
| R14 | Scraping of the base corpus by competitors | 2 | 4 | 8 | Rate limits, pagination caps, no public API; accepted as partly unavoidable |
| R15 | SEO fails to materialise; no organic acquisition | 3 | 3 | 9 | Server-rendered pages, structured data, TH/category landing pages from day one |
| R16 | R2 or Cloudflare outage | 3 | 2 | 6 | Graceful degradation; media unavailable does not break pages; documented incident response |
| R17 | Minors on the platform create compliance obligations | 3 | 3 | 9 | 13+ age gate, no payments, no DMs in the MVP, deletion on underage reports |
| R18 | Design system drifts into inconsistency | 2 | 4 | 8 | Token lint rule, component gallery at `/dev/components`, review checklist |

### Accepted (no mitigation beyond awareness)

| # | Risk | Why accepted |
|---|---|---|
| R19 | Base links cannot be validated for their actual contents | The game does not expose layout data; community reporting is the only control |
| R20 | Off-platform scams damage platform reputation | Inherent to any discovery marketplace without payments; reputation systems mitigate, not eliminate |
| R21 | Public content is scrapable | True of every public website |
| R22 | Duplicate base layouts across creators | Legitimate resharing is common; flagging plus ranking penalties is proportionate |

## 2. Assumptions

Each assumption is stated with what happens if it turns out false.

### Product

| # | Assumption | If wrong |
|---|---|---|
| A1 | Players want a verified public identity across CoC | The wedge fails; the platform becomes another base-link site and must compete on content volume alone |
| A2 | Enough players will publish bases without payment | Content supply fails (R3); consider creator incentives, importing with permission, or pivoting to recruitment-first |
| A3 | Recruitment friction is real and structured matching helps | Phase 4 underperforms; deprioritise in favour of bases and community |
| A4 | The community will tolerate an English-only launch | Growth is capped in key regions (PH, ID, BR); i18n moves up the roadmap |
| A5 | Mobile-first is correct | Analytics will tell us within weeks; the responsive design covers both regardless |
| A6 | A services marketplace has real demand | Phase 6 is deferred precisely because this is unproven |

### Technical

| # | Assumption | If wrong |
|---|---|---|
| A7 | `verifytoken` remains available and reliable | Ownership verification becomes manual-only; the dispute system absorbs the load; trust degrades (R1) |
| A8 | The API's practical rate limits are well above our 10 req/s budget | Widen sync tiers; add keys; sync on view rather than on schedule |
| A9 | Postgres handles cache+queue+session to 50k MAU | Move to Redis earlier; the triggers exist and the path is one hour |
| A10 | Postgres FTS is adequate to ~100k documents | Move to Meilisearch earlier; the interface exists |
| A11 | In-house ffmpeg is adequate for MVP video volume | Offload to a transcoding service; the interface exists |
| A12 | One VPS serves 50k MAU | Scale vertically first, then horizontally; the app is stateless by requirement |
| A13 | Livewire is sufficient for every MVP interaction | Add Alpine-side behaviour; in the extreme, a single section adopts Inertia — business logic already sits in services, so only presentation moves |
| A14 | R2 + Cloudflare egress stays effectively free | Media costs rise; tighten quotas; the storage interface allows a provider change |

### Operational

| # | Assumption | If wrong |
|---|---|---|
| A15 | Volunteer moderators can be recruited from the community | Tighten automated restrictions, reduce surface area (disable messaging/marketplace), and slow growth until capacity exists |
| A16 | Report volume stays proportionate to active users | The staffing trigger in [12 §11](12-moderation-system.md) fires; automation thresholds tighten |
| A17 | Infra spend stays under $250/month to 50k MAU | Re-examine media and sync costs first — they are the two variable components |
| A18 | The team can sustain roughly one phase per 4–8 weeks | Phases are independently shippable; the roadmap stretches rather than breaking |

### Legal

| # | Assumption | If wrong |
|---|---|---|
| A19 | Hosting fan content under Supercell's Fan Content Policy is acceptable with the required disclaimer and no commercial use of their IP | Remove the offending surface; our own identity uses no Supercell assets, so only the game-asset category is at stake |
| A22 | Using unmodified game assets to identify game content (units, TH levels, clan badges, league emblems) is permitted fan content, including self-hosting a byte-exact copy on our own CDN | Flip `assets.enabled` to false: the resolver returns original placeholders everywhere and the `game/` prefix stops being served — one config value, no component rewrites, because no asset path is hardcoded in a template ([18 §2.3](18-design-system.md)) |
| A20 | A no-payments marketplace creates no money-transmission exposure | Stage 1 is designed precisely so this holds; legal review before Stage 2 regardless |
| A21 | User-uploaded in-game screenshots are acceptable fan content | Copyright report path exists; takedown process documented |

## 3. Open questions

To be resolved before the phase named:

| # | Question | Needed by |
|---|---|---|
| Q1 | Operating jurisdiction and legal entity — determines privacy law, tax, and marketplace analysis | Before launch |
| Q2 | Domain name and branding (must not imply Supercell endorsement) | Before launch |
| Q13 | Where does the curated asset pack come from (which source, and is redistributing a byte-exact copy from our own CDN acceptable under the Fan Content Policy as read in Q1's jurisdiction)? | Phase 2 |
| Q14 | Who owns re-cutting the asset pack after a game update adds units or a TH level, and how fast? A missing asset degrades to a placeholder, so this is a chore, not an incident — but it is an unowned one today | Phase 2 |
| Q3 | Minimum age: 13 globally, or 16 in the EEA? Affects the age gate and consent | Phase 1 |
| Q4 | Does the platform commit to an SLA for dispute resolution, and with what staffing? | Phase 2 |
| Q5 | Are unverified CoC accounts shown publicly at all, or only to their owner? (Current spec: shown, clearly labelled — **confirm**) | Phase 2 |
| Q6 | Can a user credit a base to an account they do not own (e.g. a designer publishing for a friend)? (Current spec: no) | Phase 3 |
| Q7 | Retention period for base view events — 30 days assumed; is longer analytics wanted? | Phase 3 |
| Q8 | Should clan pages exist as a public destination, or only as recruitment context? | Phase 4 |
| Q9 | Direct messaging: ship it at all, or keep messaging order-scoped forever? Moderation cost is the deciding factor | Phase 5 |
| Q10 | Marketplace commission model, if Stage 2 ever happens | Phase 6 |
| Q11 | Who are the first moderators, and what is their onboarding? | Before public launch |
| Q12 | Launch strategy for content supply (invited creators? competition? import with permission?) | Before public launch |

## 4. Kill criteria

Defined in advance, because deciding to stop is much harder without them:

- **Phase 3 (MVP) + 3 months:** fewer than 100 verified accounts or fewer than 200 published bases
  → the wedge is not working. Re-examine the premise before building Phase 4.
- **Any phase:** moderation backlog exceeding the SLA for a month with no capacity plan → disable
  the offending surface rather than shipping the next feature.
- **Marketplace:** any payment-provider or legal signal against the model → stop at Stage 1
  permanently, which is a fine outcome.
