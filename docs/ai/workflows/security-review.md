# Workflow: security-review

Audit a diff for security defects. Read-only: do not edit files.

Read `specs/11-security.md`, `specs/04-roles-and-permissions.md`, and — when relevant —
`specs/10-media-storage.md` (uploads), `specs/13-claiming-workflow.md` (CoC tokens),
`specs/12-moderation-system.md` (admin/moderation).

Check the diff for:
- **Authorization**: every state-changing controller action calls `authorize()`; ownership-scoped
  queries; scoped route bindings; ULIDs/natural keys only in URLs; admin Gates in each action.
- **Mass assignment**: `$fillable` set; `role`/status fields never fillable.
- **Props exposure**: no Eloquent models or `toArray()` in `Inertia::render()` or shared props;
  no email, IP data, 2FA state, tokens or private order details reaching the client; lazy/deferred
  props authorised like their page.
- **XSS**: no `v-html` outside `<SanitizedMarkdown>`; no `{!! !!}` in Blade; user URLs restricted to
  http/https with `rel="nofollow ugc noopener"`.
- **Injection**: no raw SQL with user input; sort/filter columns allowlisted; `Process` with
  argument arrays for ffmpeg.
- **Uploads**: presigned intents scoped to the owner, MIME/size/dimension validated server-side,
  SVG rejected, re-encode path intact.
- **Secrets**: CoC API keys/tokens never logged, stored, put in job payloads or sent to the browser.
- **Rate limits / CSRF / re-confirmation**: named limiters on writes and search; no CSRF exemptions;
  password re-confirmation on sensitive actions.
- **Tests**: a security test exists for each new input/file/trust-boundary surface (`tests/Security`).

Output: findings, most severe first, each with severity (critical/high/medium/low), file:line, a
concrete exploit scenario, and a one-line fix. If nothing is wrong, say "No findings."
