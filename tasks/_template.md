---
id: P0-01
title: <imperative, one deliverable>
phase: 0
status: todo            # todo | in-progress | review | done | blocked
depends_on: []          # task ids
---

# <title>

## Spec refs
- Core: <spec file + section>
- Plus: <supporting specs>
- FR: <FR-* ids from specs/02>
- Edge cases: <rows from specs/23>

## Scope
- Migrations / models / factories:
- Domain (enums, value objects, services/actions, events):
- Policy + Form Request:
- UI (controller + Inertia Vue page/components, design-system components used):
- Jobs / listeners / schedule entries:
- Config keys added:

## Out of scope
- <explicit, to stop drift>

## Acceptance criteria
- Functional: <FR ids>
- Authorization: <who can and cannot, per specs/04>
- Edge cases: <from specs/23>
- States: empty / loading / error designed and implemented

## Tests
- Feature (`assertInertia`): happy path, authorization, validation
- Security: <if input, files, or a trust boundary>
- Unit: <value objects, scoring, parsers>
- Vitest: <composables/components with logic>

## Notes
<decisions made while implementing; divergences, synced into specs at implement → Finish>
