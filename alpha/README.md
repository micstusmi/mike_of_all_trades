# ezTradie alpha foundation (development only)

This is an isolated, empty-data prototype. It does not import the Mike of All Trades database, login, uploads, Zoho credentials or public website. The original Work Tracker routes are **not** tenant safe and must not be copied into this public root.

## What this branch currently contains

- A standalone MySQL 8 schema for businesses, verified users, memberships, customers, properties and jobs.
- A small PHP JSON entry point under `public/`, using an alpha-only session cookie and business membership checks on every request.
- Read and create operations for customers and jobs, all scoped on the server. Composite foreign keys reject a job whose property belongs to a different business or customer.
- A transaction-based, two-business isolation test using only synthetic records.
- `schema/002_alpha_services.sql` adds tester labels, an accounting preference, invite/recovery tokens, feedback, and an AI usage ledger. Apply it after `001_foundation.sql` to a disposable database.
- `schema/003_usage_reservations.sql` adds preflight AI reservations. A trusted server process must reserve its maximum charge and provider cost before an external call, then complete or cancel it. Reservations remain counted until explicitly resolved, including after their two-hour completion window, so a stalled job cannot silently open more spending capacity. No provider calls are connected yet.
- `schema/004_calendar_drafts.sql` stores business-owned booking drafts. The app lets an invited member quickly save an appointment. A future trusted calendar worker can import exactly `-EZ ` events idempotently; no Google or iCloud connection exists yet.
- `/app.php` is a small private alpha interface for invited users to create customers, properties and draft jobs, submit feedback, inspect AI usage and manage member access. It is not the existing Mike Work Tracker.
- `GET /features` is a status-labelled, public catalogue. “Working on Mike's site” does not mean migrated to ezTradie. `alpha/catalog/protected_controls.json` lists controls that need explicit review before removal.
- Authenticated `GET/POST /feedback`, `GET /feedback/ID`, `GET /ai/usage`, and owner-only `POST /ai/budget`. These are JSON endpoints, not a completed customer UI.
- Trusted CLI `alpha/tools/operator.php` can label testers, record their accounting choice, triage feedback, and issue single-use invite/recovery tokens. Tokens are **not emailed automatically**; operators must deliver them to the verified intended email address through a separate secure process. `POST /redeem` consumes a token and sets a password. Do not enable public invitations before delivery is implemented and tested.
- Usage insertion is internal-only. AI providers, billing, and SMS are still disabled; no client can set the price of a usage event through HTTP.

## Development setup (not a public deployment recipe)

Use a **disposable database**, not Mike's database or the new server's `eztradie_alpha` until the full release gates are met. Apply `alpha/schema/*.sql` in filename order to the empty database. Configure the following as private process environment variables, never in Git:

```
EZ_ALPHA_DSN=mysql:host=localhost;dbname=DISPOSABLE_DB;charset=utf8mb4
EZ_ALPHA_DB_USER=DEDICATED_DB_USER
EZ_ALPHA_DB_PASSWORD=PRIVATE_PASSWORD
```

Run `python3 alpha/tools/check_catalog.py` from the repository root. Run the isolation test with `EZ_ALPHA_TEST_DATABASE=YES php alpha/tests/isolation.php`. The test requires all migrations and rolls back synthetic rows. The `.github/workflows/eztradie-alpha.yml` check builds a disposable MySQL database on branch pushes. Serve **only** `alpha/public/` as a document root in a future HTTPS virtual host. Route dynamic requests to `index.php` while preserving static files and `/app.php`. Require HTTPS, and do not expose the repository root or `alpha/src/`, `alpha/schema/`, `alpha/catalog/`, `alpha/tools/` or `alpha/tests/` over HTTP.

## Release blockers

There is deliberately no public registration, automated invitation/recovery email delivery, provider integration, billing, or migration of legacy Work Tracker features yet. The operator CLI token flow is **not** an email verification system until a verified delivery process exists. Login attempts are limited per email and business, but broader abuse controls and audit logging remain. AI reservations have no provider integrations or price schedule yet. No tester accounts or domain routing yet. Continue the route conversion and run two-business abuse tests before deployment.
