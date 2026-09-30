---
paths:
  - "src/database/**"
  - "src/app/Domain/*/Models/**"
---

# Database rules

- Load `specs/07-database-schema.md` and `specs/08-entity-relationships.md` before any migration or
  model change. Column names, types, constraints and indexes follow them exactly; diverge only with
  a Notes entry (synced into specs at implement → Finish).
- PostgreSQL 16 features are expected: `jsonb` + GIN, partial/expression indexes, `CHECK`
  constraints, `tsvector` + `pg_trgm`. Tests run on SQLite and Postgres — guard Postgres-only DDL
  with a driver check.
- Public identifiers are ULIDs or natural keys; autoincrement ids never appear in URLs.
- Cascade and soft-delete semantics per `specs/08` §6.
- Do not remove the default `jobs` index `(queue, reserved_at, available_at, id)`.
- Every model has a factory; factories produce valid, policy-passing defaults with states for the
  edge cases in `specs/23`.
- Migrations are forward-only in production; never edit a merged migration — add a new one.
