---
paths:
  - "specs/**"
  - "tasks/**"
---

# Specs and tasks rules

- `specs/` is the source of truth; edit it to match reality, never the reverse silently.
- Locked decisions in `specs/README.md` change only with explicit user approval.
- `specs/project.md` is the original brief — never edit it.
- Keep cross-references (`[NN](file)`, `§` numbers, `FR-*` ids) valid after every edit; grep all of
  `specs/` for a renamed term.
- Task files follow `tasks/_template.md`, stay under one page, and cite spec sections for every
  scope item. Keep `tasks/BOARD.md` status and File columns in sync with task files.
