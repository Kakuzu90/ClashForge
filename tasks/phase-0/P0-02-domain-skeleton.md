---
id: P0-02
title: Create the app/Domain module skeleton, boundary checks and Support primitives
phase: 0
status: done
depends_on: [P0-01]
---

# Create the app/Domain module skeleton, boundary checks and Support primitives

## Spec refs
- Core: specs/05 §2 (modules, boundary enforcement), §3 (layering rules), §5 (time, enums); specs/19 §1 (tree, inside a module), §2 (dependency rules), §3 (naming), §6 (architecture tests)
- Plus: specs/03 NFR-PERF-7 (`preventLazyLoading`), NFR-MAINT-2 (PHPStan L8 on `app/Domain`)
- FR: none (foundation)
- Edge cases: none

## Scope
- **Module folders** under `src/app/Domain/`: Auth, Users, CocIntegration, PlayerAccounts, Clans, Bases, Recruitment, Marketplace, Messaging, Media, GameAssets, Notifications, Moderation, Audit, Search (specs/19 §1). Empty modules hold a `.gitkeep`; sub-folders are created by the task that first needs them.
- **Service provider wiring**: module providers (`{Module}ServiceProvider`, optional per specs/19 §1) are registered explicitly in `bootstrap/providers.php` when a module first needs one. No auto-discovery. None created in this task.
- **Boundary checks**:
  - Deptrac (`deptrac.yaml`): layers Http, DomainPublic (`Services|Actions|Queries|Data|Enums|Contracts`), DomainEvents, DomainInternal, DomainModels, Support; Http → DomainPublic only; Domain ↛ Http; Support ↛ Domain (specs/19 §2).
  - Pest arch tests, looping over `app/Domain/*` at runtime so new modules are covered automatically (specs/19 §6):
    - no module uses another module's `Models` namespace;
    - edge modules (CocIntegration, Media, GameAssets) and leaf Audit use no other `App\Domain` module;
    - cross-module references only target `Contracts|Services|Data|Events|Enums`;
    - `App\Domain` never uses `App\Http`.
- **Support primitives** (`src/app/Support/`, specs/19 §1):
  - `Enums/Contracts/HasLabelAndColor` interface + `Enums/Concerns/EnumHelpers` trait (`label()`, `color()`, `options()`), for every status enum (specs/05 §3).
  - `ValueObjects/StringValueObject`: immutable, validates in its constructor, `equals()`, `__toString()`, `JsonSerializable` (base for `PlayerTag`, `BaseLink`, `LayoutHash`).
  - `Casts/AsValueObject`: Eloquent cast for any `StringValueObject`.
  - `Rules/ValidValueObject`: validation rule that delegates to the value object, so Form Requests cannot bypass it (specs/05 §3).
- **App defaults** in `AppServiceProvider`: `Date::use(CarbonImmutable::class)`; `Model::shouldBeStrict(! app()->isProduction())` (lazy-loading, silently discarded attributes, missing attributes).
- Config keys added: none.

## Out of scope
- Any module's models, services, tables or events (Phase 1+)
- `feature_flags` table + `Feature` facade (specs/07 marks it P2)
- Domain value objects themselves (`PlayerTag` etc. ship with their modules)
- `Macros/`, `Traits/` folders (created when first needed)

## Acceptance criteria
- Functional: n/a (structure + primitives).
- Authorization: n/a.
- `scripts/check.sh` green; the PHPStan L8 Domain pass runs now that `app/Domain` exists.
- A deliberate cross-module `Models` import in a throwaway test fixture makes the arch test fail (verified, then removed).

## Tests
- Architecture: the four boundary rules above; enums implement `HasLabelAndColor` and are string-backed.
- Unit: `StringValueObject` (valid/invalid construction, equality, JSON), `AsValueObject` (get/set, null), `ValidValueObject` (passes/fails with message), `EnumHelpers` (`options()` shape).
- Feature: `Date::now()` returns `CarbonImmutable`; lazy loading throws outside production.

## Notes

### Decisions
- Cross-module `Models` isolation is enforced by Pest arch tests (auto-discovering modules) rather than one Deptrac layer per module (45 hand-maintained layers). specs/05 §2 allows "Deptrac or a PHPStan custom rule"; specs/19 §6 already assigns "models not used cross-module" to arch tests. Deptrac keeps the Http/Domain/Support layering.
- The "Depends on" column in specs/05 §2 is not enforced: event consumers (e.g. Clans listening to `CocAccountVerified`) legitimately reference modules outside that column. Only the specs/19 §2 rules are enforced.

### Open questions
None.

### Implementation notes
- Boundary checks proven with throwaway probes (removed): a Users service importing a Bases model and Media importing a Users service fail the arch tests; an Http controller importing a Domain model fails Deptrac; an int-backed enum without `HasLabelAndColor` fails both enum rules.
- Pest chained arch expectations drop the `->enums()` modifier after the first expectation, so the enum rules are two separate tests.
- `phpstan.neon` also analyses `tests/Support` so `EnumHelpers` is exercised before a module uses it.
- `scripts/check.sh` runs the L8 Domain pass only once `app/Domain` contains PHP files.
- Test fixtures: `tests/Support/Fixtures/{SampleCode,OtherCode,SampleStatus}`.

### Spec sync
- synced → specs/05 §2 (boundary enforcement: Pest arch + Deptrac; "Depends on" not enforced)
- synced → specs/19 §2 (implementation note)

