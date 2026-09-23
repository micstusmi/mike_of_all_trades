# ezTradie alpha foundation (development only)

This is an isolated, empty-data prototype. It does not import the Mike of All Trades database, login, uploads, Zoho credentials or public website. The original Work Tracker routes are **not** tenant safe and must not be copied into this public root.

## What this branch currently contains

- A standalone MySQL 8 schema for businesses, verified users, memberships, customers, properties and jobs.
- A small PHP JSON entry point under `public/`, using an alpha-only session cookie and business membership checks on every request.
- Read and create operations for customers and jobs, all scoped on the server. Composite foreign keys reject a job whose property belongs to a different business or customer.
- A transaction-based, two-business isolation test using only synthetic records.
- `schema/002_alpha_services.sql` adds tester labels, an accounting preference, invite/recovery tokens, feedback, and an AI usage ledger. Apply it after `001_foundation.sql` to a disposable database.
- `GET /features` is a status-labelled, public catalogue. “Working on Mike's site” does not mean migrated to ezTradie. `alpha/catalog/protected_controls.json` lists controls that need explicit review before removal.
- Authenticated `GET/POST /feedback`, `GET /feedback/ID`, `GET /ai/usage`, and owner-only `POST /ai/budget`. These are JSON endpoints, not a completed customer UI.
- Trusted CLI `alpha/tools/operator.php` can label testers, record their accounting choice, triage feedback, and issue single-use invite/recovery tokens. Tokens are **not emailed automatically**; operators must deliver them to the verified intended email address through a separate secure process. `POST /redeem` consumes a token and sets a password. Do not enable public invitations before delivery is implemented and tested.
- Usage insertion is internal-only. AI providers, billing, and SMS are still disabled; no client can set the price of a usage event through HTTP.

## Development setup (not a public deployment recipe)

Use a **disposable database**, not Mike's database or the new server's `eztradie_alpha` until the full release gates are met. Apply `schema/001_foundation.sql`, then `schema/002_alpha_services.sql` to the empty database. Configure the following as private process environment variables, never in Git:

```
EZ_ALPHA_DSN=mysql:host=localhost;dbname=DISPOSABLE_DB;charset=utf8mb4
EZ_ALPHA_DB_USER=DEDICATED_DB_USER
EZ_ALPHA_DB_PASSWORD=PRIVATE_PASSWORD
```

Run `python3 alpha/tools/check_catalog.py` from the repository root. Run the isolation test with `EZ_ALPHA_TEST_DATABASE=YES php alpha/tests/isolation.php`. The test requires both schema files and rolls back synthetic rows. It must pass against MySQL with foreign-key checks enabled. Serve **only** `alpha/public/` as a document root in a future HTTPS virtual host. The route path must start at `/`; do not expose the repository root or `alpha/src/`, `alpha/schema/`, `alpha/catalog/`, `alpha/tools/` or `alpha/tests/` over HTTP.

## Release blockers

There is deliberately no public registration, automated invitation/recovery email delivery, full UI, provider integration, billing, or migration of legacy Work Tracker features yet. The operator CLI token flow is **not** an email verification system until a verified delivery process exists. Login attempts are limited per email and business, but broader abuse controls and audit logging remain. AI budget recording is post-action and must be paired with a preflight reservation before connecting a paid provider; a cap that only applies after a request would not prevent provider charges. No tester accounts or domain routing yet. Continue the route conversion and run two-business abuse tests before deployment.
