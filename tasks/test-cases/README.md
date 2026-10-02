# Phase 1 test cases

Manual test cases for Phase 1 (Identity), one file per done task, plus the phase exit flow.
They describe the behaviour as built (task file Scope, Acceptance criteria and Decisions), not
the automated Pest / Vitest suites, which live in `src/tests/`.

## Environment

| What | Where |
|---|---|
| App | http://localhost:8080 (`docker compose up -d`) |
| Mail (every outgoing email) | Mailpit, http://localhost:8025 |
| Database | Adminer, http://localhost:8081 |
| Fresh data | `docker compose exec app php artisan migrate:fresh --seed` |
| Run the scheduler / queue by hand | `docker compose exec app php artisan schedule:run`, the `queue` containers process jobs |

Seeded accounts (local only), password `password`:

| Username | Email | Role |
|---|---|---|
| `test_user` | test@example.com | user |
| `test_moderator` | moderator@example.com | moderator |
| `test_admin` | admin@example.com | admin |
| `test_super_admin` | superadmin@example.com | super_admin |

Account states not seeded (restricted, suspended, banned, unverified, pending deletion) are made
through the admin sanction panel, the danger zone, registration, or Adminer, as each case says.

## Files

| File | Task |
|---|---|
| [P1-00-phase-exit.md](P1-00-phase-exit.md) | Phase 1 exit criteria, end to end |
| [P1-01-login-and-password-reset.md](P1-01-login-and-password-reset.md) | Login, logout, remember-me, password reset |
| [P1-02-roles-status-policy-scaffold.md](P1-02-roles-status-policy-scaffold.md) | Roles, status, write gating |
| [P1-03-profiles-and-avatar.md](P1-03-profiles-and-avatar.md) | Profile settings, avatar upload |
| [P1-04-privacy-and-public-profile.md](P1-04-privacy-and-public-profile.md) | Privacy settings, public profile |
| [P1-05-security-settings-and-sessions.md](P1-05-security-settings-and-sessions.md) | Password change, sessions, re-confirmation |
| [P1-06-audit-log-and-viewer.md](P1-06-audit-log-and-viewer.md) | Audit log viewer |
| [P1-07-notifications-v1.md](P1-07-notifications-v1.md) | Bell, notification centre |
| [P1-08-registration-and-email-verification.md](P1-08-registration-and-email-verification.md) | Registration, email verification |
| [P1-09-username-change.md](P1-09-username-change.md) | Username change, old-name redirects |
| [P1-10-email-change.md](P1-10-email-change.md) | Email change |
| [P1-11-account-deletion.md](P1-11-account-deletion.md) | Account deletion, danger zone |
| [P1-12-admin-user-list-and-detail.md](P1-12-admin-user-list-and-detail.md) | Admin user list and detail |
| [P1-13-admin-dashboard.md](P1-13-admin-dashboard.md) | Admin dashboard |
| [P1-14-sanctions.md](P1-14-sanctions.md) | Suspend, ban, lift |

P1-15, P1-16 (todo) and P0-09 (blocked) have no cases yet.

## Case format

```
### TC-P1-01-001: <what is checked>
- Priority: High | Medium | Low · Type: Functional | Validation | Authorization | Security | Edge case | UI state | Email
- Ref: FR-* / specs/NN §x / task file section
- Preconditions: <account, state, data>
- Steps:
  1. ...
- Expected: <observable result: page, message text, email in Mailpit, row in the DB>
```

IDs are `TC-<task>-<nnn>`, numbered in file order. Expected text quotes the UI copy as built. A
file may open with a short "Notes for the tester" block (cache delays, setup snippets).

## Flash messages

Server messages after a save or redirect (`flash.success`, `flash.error`) show as toasts, bottom
right (above the tab bar on mobile): success toasts close after 5 s, error toasts stay until
dismissed. Cases written before this fix check the inline state, the data and Mailpit; where a
case notes that a message "should appear", expect it as a toast.
