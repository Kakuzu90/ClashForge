# Workflow: next-task

Input: optional task id (e.g. `P1-03`).

1. Read `tasks/BOARD.md`. Target = the given id, else the first `todo` row whose dependencies are
   all `done`. If none qualify, report what blocks progress and stop.
2. Read `specs/README.md` (locked decisions + load map), then the task's **Core** and **Plus** specs
   from `specs/25-development-phases.md` §4. Pull the matching `FR-*` rows from `specs/02`, the
   domain's rows from `specs/23`, and `specs/11` if the surface takes input/files/crosses a trust
   boundary. Read the `specs/25` phase section once for exit criteria.
3. Write `tasks/phase-<N>/<id>-<kebab-slug>.md` from `tasks/_template.md`. Every section must be
   answerable from the specs — cite spec file + section for each item. Keep it under one page; if it
   will not fit, split it into two tasks and add a row to the board.
4. Anything the specs leave open goes under **Notes → Open questions**. Ask the user about those
   before marking the task ready.
5. Update the board row: `File` = the new path. Leave status `todo`.
6. Output: the task file path and its open questions (if any). Nothing else.
