# Workflow: antislop-audit

antislop in **after** mode, scoped to one change. Runs once per UI task, inside `verify`, after the
implementation is complete. Read-only except for the audit report file.

## Inputs
- The task file (if given) and the diff (`git diff` + `git diff --cached`, or `git diff master...HEAD`).
- Scope = changed files under `src/resources/js/**`, `src/resources/css/**`, `src/resources/views/**`,
  plus user-facing copy anywhere (translations, notification/mail text, meta/OG text). If none
  changed, output "antislop: no UI changes, skipped." and stop.

## Steps
1. Read `DESIGN.md` (dials + owner decisions) and `.agents/skills/antislop/SKILL.md` (core). Then
   read only the skills the scope needs:
   - Vue components/layout/motion → `antislop-ui`, `antislop-layoutmobile`
   - Copy → `antislop-copywriting`
   - Forms, focus, contrast, states → `antislop-human`
   - Comments in changed files → `antislop-code`
2. Audit only the scoped files against R-01 to R-38 and C-1 to C-5. Owner decisions in `DESIGN.md`
   are settled: never report them. R-02 applies to user-facing copy only. For new colour pairs, run
   `python3 .agents/skills/antislop-human/contrast-check.py "<fg>" "<bg>"`.
3. Skip R-35 click-through here (static audit). It runs at phase end; see `verify`.
4. Write `anti-slop/audit-NNN-YYYY-MM-DD.md` (NNN = next number in the folder): numbered findings,
   each with file:line, rule (R-XX / C-X), one-line reason, priority (Hard Gate = HIGH,
   Purpose-Gate = MEDIUM, Quality Locks = LOW), and a one-line fix.
5. Output: the report path and the findings list, HIGH first. No other commentary. If nothing is
   found, write the report with "No findings." and say so.

## After the audit
Do not change code until the user approves specific finding numbers. Fix only those, re-run
`scripts/check.sh`, and append a follow-up section to the same report listing what was fixed.
