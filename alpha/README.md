# ezTradie alpha foundation (development only)

This is an isolated, empty-data prototype. It does not import the Mike of All Trades database, login, uploads, Zoho credentials or public website. The original Work Tracker routes are **not** tenant safe and must not be copied into this public root.

## What this slice contains

- A standalone MySQL 8 schema for businesses, verified users, memberships, customers, properties and jobs.
- A small PHP JSON entry point under `public/`, using an alpha-only session cookie and business membership checks on every request.
- Read and create operations for customers and jobs, all scoped on the server. Composite foreign keys reject a job whose property belongs to a different business or customer.
- A transaction-based, two-business isolation test using only synthetic records.

## Development setup (not a public deployment recipe)

Use a **disposable database**, not Mike's database or the new server's `eztradie_alpha` until the full release gates are met. Apply `schema/001_foundation.sql` to the empty database. Configure the following as private process environment variables, never in Git:

```
EZ_ALPHA_DSN=mysql:host=localhost;dbname=DISPOSABLE_DB;charset=utf8mb4
EZ_ALPHA_DB_USER=DEDICATED_DB_USER
EZ_ALPHA_DB_PASSWORD=PRIVATE_PASSWORD
```

Run the isolation test with `EZ_ALPHA_TEST_DATABASE=YES php alpha/tests/isolation.php`. The test requires the schema and rolls back synthetic rows. It must pass against MySQL with foreign-key checks enabled. Serve **only** `alpha/public/` as a document root in a future HTTPS virtual host. The route path must start at `/`; do not expose the repository root or `alpha/src/`, `alpha/schema/` or `alpha/tests/` over HTTP.

## Release blockers

There is deliberately no public registration, invitation sender, password reset, email verification flow, seed account, UI, provider integration or migration of legacy Work Tracker features yet. There is also no login rate limiting or audit trail. Do not point DNS here, share links with testers, or treat this API as the released alpha. Continue the endpoint conversion and run two-business abuse tests before any deployment.
