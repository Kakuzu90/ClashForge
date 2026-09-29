# Clash Commons — Specification Set

Planning documents for the Clash of Clans community platform described in [project.md](project.md).
No implementation code lives here. Each file is self-contained enough to be converted into
development tasks for an agentic coding workflow.

## Reading order

| # | File | Covers |
|---|------|--------|
| 01 | [01-product-overview.md](01-product-overview.md) | Product overview, positioning, MVP definition, explicit non-goals |
| 02 | [02-functional-requirements.md](02-functional-requirements.md) | Numbered functional requirements + full feature breakdown |
| 03 | [03-non-functional-requirements.md](03-non-functional-requirements.md) | Performance, availability, cost, compliance, observability budgets |
| 04 | [04-roles-and-permissions.md](04-roles-and-permissions.md) | Roles, permission matrix, authentication/authorization strategy |
| 05 | [05-architecture.md](05-architecture.md) | Modular monolith design, module boundaries, request/event flow |
| 06 | [06-tech-stack.md](06-tech-stack.md) | Stack evaluation, Livewire vs Inertia vs split API, Redis trigger points |
| 07 | [07-database-schema.md](07-database-schema.md) | Table-by-table schema, constraints, indexes |
| 08 | [08-entity-relationships.md](08-entity-relationships.md) | ER diagram, cardinalities, ownership/cascade rules |
| 09 | [09-coc-api-integration.md](09-coc-api-integration.md) | CoC API layer, rate limits, sync, failure handling |
| 10 | [10-media-storage.md](10-media-storage.md) | Object storage, upload pipeline, validation, video, CDN, cleanup |
| 11 | [11-security.md](11-security.md) | Threat model and controls per OWASP category |
| 12 | [12-moderation-system.md](12-moderation-system.md) | Reports, queues, moderator workflow, anti-abuse, audit trail |
| 13 | [13-claiming-workflow.md](13-claiming-workflow.md) | CoC account claiming, verification, disputes, transfers |
| 14 | [14-recruitment-workflow.md](14-recruitment-workflow.md) | Looking-for-clan and clan recruitment workflows |
| 15 | [15-marketplace-workflow.md](15-marketplace-workflow.md) | Services marketplace, escrow analysis, legal/payment risks |
| 16 | [16-notifications.md](16-notifications.md) | Notification types, channels, fan-out, digests, preferences |
| 17 | [17-search-and-discovery.md](17-search-and-discovery.md) | Postgres-first search, facets, ranking, migration path |
| 18 | [18-design-system.md](18-design-system.md) | Design tokens, component inventory, page layouts, states |
| 19 | [19-module-structure.md](19-module-structure.md) | Laravel folder structure, naming conventions, testing layout |
| 20 | [20-jobs-and-scheduling.md](20-jobs-and-scheduling.md) | Queues, jobs, scheduled tasks, idempotency, retry policy |
| 21 | [21-caching-strategy.md](21-caching-strategy.md) | Cache layers, keys, TTLs, invalidation, driver-agnostic rules |
| 22 | [22-scaling.md](22-scaling.md) | Growth stages, bottlenecks, scaling levers, cost curve |
| 23 | [23-edge-cases.md](23-edge-cases.md) | Major edge cases by domain with expected behaviour |
| 24 | [24-risks-and-assumptions.md](24-risks-and-assumptions.md) | Risk register, assumptions, mitigations, open questions |
| 25 | [25-development-phases.md](25-development-phases.md) | Phased roadmap, exit criteria, task-conversion guidance |

## Load map

The numbering above is a reading order for a person. **Do not load the set in order for a coding
task** — load by what you are building.

### Always in context

| File | Why |
|---|---|
| [README.md](README.md) | Locked decisions |
| [05-architecture.md](05-architecture.md) | Module boundaries, event seam, where code goes |
| [19-module-structure.md](19-module-structure.md) | Folder layout, naming, dependency rules |
| [04-roles-and-permissions.md](04-roles-and-permissions.md) | Every write surface needs a policy |

### Per task

| Building | Load |
|---|---|
| Any migration / model | [07](07-database-schema.md), [08](08-entity-relationships.md) |
| Auth, registration, sessions | [04](04-roles-and-permissions.md), [11](11-security.md) |
| CoC attach / verify / dispute | [13](13-claiming-workflow.md), [09](09-coc-api-integration.md), [07](07-database-schema.md) |
| Account sync, API client | [09](09-coc-api-integration.md), [20](20-jobs-and-scheduling.md) |
| Bases, feed, comments | [02](02-functional-requirements.md) (FR-BASE), [07](07-database-schema.md), [17](17-search-and-discovery.md) |
| Uploads, images, video | [10](10-media-storage.md), [20](20-jobs-and-scheduling.md) |
| Game assets | [18 §2](18-design-system.md), [10 §11](10-media-storage.md) |
| Any UI | [18](18-design-system.md) |
| Reports, moderation, admin | [12](12-moderation-system.md), [04](04-roles-and-permissions.md) |
| Recruitment | [14](14-recruitment-workflow.md), [07](07-database-schema.md) |
| Marketplace | [15](15-marketplace-workflow.md) |
| Notifications | [16](16-notifications.md) |
| Search | [17](17-search-and-discovery.md) |
| Jobs, schedule, caching | [20](20-jobs-and-scheduling.md), [21](21-caching-strategy.md) |

### Add to every task

- [02](02-functional-requirements.md) — the `FR-*` ids the task satisfies
- [23](23-edge-cases.md) — the rows for the domain being touched
- [11](11-security.md) — if the surface takes user input, files, or crosses a trust boundary

### Per phase task

[25 §4](25-development-phases.md) maps each phase task to its exact spec bundle (core + supporting).
Start there when picking up a task; the tables above are the fallback for work that does not map to
a phase task.

### Not implementation input

[01](01-product-overview.md), [03](03-non-functional-requirements.md),
[06](06-tech-stack.md), [22](22-scaling.md), [24](24-risks-and-assumptions.md) — planning context.
Read [25](25-development-phases.md) once when starting a phase to pick and shape tasks, then drop it.

## Locked decisions

These are settled; changing one means revisiting the affected specs.

1. **Modular monolith**, Laravel 12 / PHP 8.3, one deployable. No microservices before Stage 3 traffic.
2. **Laravel + Livewire 3 + Tailwind + Alpine.** Not Inertia, not a split SPA. Rationale in [06](06-tech-stack.md).
3. **PostgreSQL 16** as the only datastore for MVP, including cache, queue and session tables.
4. **No Redis at launch.** All code goes through `Cache`/`Queue` facades. Switch triggers in [21](21-caching-strategy.md).
5. **Cloudflare R2 + CDN** for all media. Nothing binary in the database.
6. **Supercell content policy compliance is a hard constraint.** No account trading, no real-money gambling. Clash of Clans assets (troops, heroes, spells, equipment, Town Halls, clan badges, league emblems) may be used **unmodified, to identify game content only**; our branding, navigation, UI components, badges, illustrations and visual identity stay original ([18 §2](18-design-system.md)).
7. **No payment processing in the MVP marketplace.** See the risk analysis in [15](15-marketplace-workflow.md).
