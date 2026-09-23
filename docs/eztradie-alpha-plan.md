# ezTradie alpha: isolation and release plan

Base: `378f722` (V10.2). This branch is development only. It must not be deployed to either public server yet.

## Current code findings

- `admin/work/_auth.php`, `api/work/_admin_auth.php`, and `includes/auth_admin.php` accept a global `admin` role; they do not resolve a business membership.
- `includes/work_tracker.php::wt_job()` fetches by job ID alone, and `admin/work/index.php` lists all `work_jobs`. Individual API routes call these helpers or run their own queries. A navigation-only filter would not stop cross-business access.
- `work/customer_job_record.php` starts with a public job token and then loads job-related data. Customer visibility must be checked for each field and file; a worker/admin token must never grant another business access.
- `database_work_tracker.sql` creates job-rooted tables without `business_id`. V10 adds standalone `work_customers` and `work_properties`; invoice ledger joins customer, job, session and material IDs. Current Zoho IDs and unique indexes assume one business.
- `tools/install_v10.php` modifies and backfills an existing database. It is not a fresh installer for the empty alpha database. `includes/db.php` and `includes/config.php` are intentionally absent from GitHub, so alpha needs new private configuration.
- There are 88 PHP routes under `api/work/` at this commit. Every route that reads or writes a business record needs server-side ownership checks. Queues, scheduled tools, search, exports, uploads, customer links, SMS callbacks and accounting callbacks need the same treatment.

## Implementation sequence

1. **Fresh alpha bootstrap.** Build a reproducible schema from the current migrations, create a dedicated least-privilege DB user, and load only synthetic data. Keep configuration and credentials outside the public web root and repository.
2. **Identity and membership.** Add businesses, verified users, membership roles (owner/admin/staff), invitation acceptance and recovery. Resolve the active business on the server from an authenticated membership. Default deny when no membership exists.
3. **Data ownership.** Give each customer, property, job, worker and invoice an immutable business owner. Child records inherit ownership through checked parent relations; enforce business-scoped foreign-key relationships or equivalent application checks. Scope unique external accounting IDs by business/provider.
4. **Route and file enforcement.** Convert lists, single-record lookups, mutations, search, media serving, queues, scheduled tools and public customer links. Check ownership before any effect, including SMS or invoice API calls. Store private files under business-specific paths and serve them through checked handlers.
5. **Accounting adapters.** Define a provider-neutral invoice/customer interface. Store each business's provider choice and encrypted OAuth credentials separately. Keep Zoho optional; add Xero/MYOB/QuickBooks adapters later without making any provider a requirement for onboarding.
6. **Proof with two businesses.** Create two synthetic accounts and overlapping record IDs. Test URL guessing, POST tampering, uploads/downloads, search, background jobs, invitation roles, password recovery and provider callbacks. Every attempted cross-business read/write must fail.
7. **Controlled alpha launch.** Deploy only after the above gates, with TLS, backups and restricted invitation flow. Point `eztradie.com.au` to the attached static IP only when the alpha can be opened safely. Invite friends and family after end-to-end isolation tests.

## Explicit launch rules

- Do not copy the Mike of All Trades production database, uploads, API keys, Zoho tokens or .env/config files to alpha.
- Do not run the existing V10 installer against `eztradie_alpha` until the fresh-install path is understood and revised.
- Do not merge alpha changes into `main` or deploy this branch to Mike's live site as a side effect.
- Mike of All Trades can become business one later through a separately tested migration; it is not seed data for early alpha tests.
