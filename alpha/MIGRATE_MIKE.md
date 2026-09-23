# Mike-only migration rehearsal

The importer is for a private, empty ezTradie business owned by Mike. It does not read or alter Mike's source rows and does not move logins, public links, Zoho credentials, SMS sender details or uploaded files. **Keep the Mike of All Trades Work Tracker live.** This is a first rehearsal, not a cutover.

## Coverage

| Source | Destination | Notes |
| --- | --- | --- |
| `work_customers` | `alpha_customers` | Name, email and phone. Source IDs mapped, no Zoho ID or billing settings. |
| `work_properties` | `alpha_properties` | Address and owner customer. Source IDs mapped. |
| `work_jobs` | `alpha_jobs`, `alpha_job_legacy_details` | Status, scope, selected pricing/payment figures, planned dates and master archive URL. Source IDs mapped. Public token and old authentication intentionally excluded. |
| Every other `work_*` table | Reported as deferred | Records are **not** imported. Source backup must be retained. |

The private owner-only **Migration** tab displays the last inventory and old-to-new counts. Each imported job can display its original scope and Mike job number. Empty source tables remain in the report too. The report counts rows, not media files; file existence and checksums need a separate audit.

## Before a rehearsal

1. Take and verify a full database backup and a separate backup of the `storage/private` media tree on Mike's server. Store both outside the web root. Do not place a dump, source password, or customer media in Git or this chat.
2. Restore a **copy** of the source database under a different name on a private host. Use a database user with `SELECT` access only on that source copy. Apply all `alpha/schema/*.sql` migrations, in order, to a separate, empty alpha database. Neither database may be Mike's live database.
3. In the alpha database, use `php alpha/tools/operator.php bootstrap "Mike of All Trades" mike@example.com` with Mike's actual verified email. Deliver the one-time owner token privately; redeem it at the private HTTPS alpha app. Do not use public registration or invite testers.
4. Set `EZ_MIKE_READONLY_DSN`, `EZ_MIKE_READONLY_USER`, `EZ_MIKE_READONLY_PASSWORD`, `EZ_ALPHA_DSN`, `EZ_ALPHA_DB_USER` and `EZ_ALPHA_DB_PASSWORD` as private process environment variables. The two DSNs must point to different database names. Never paste their passwords into chat or commit them.
5. Run `php alpha/tools/import_mike.php plan BUSINESS_ID`. Check the listed core and deferred counts, `invalid_jobs` and `invalid_properties`. Repair source links in a new source copy if either invalid count is nonzero. Verify the proposed destination is the empty business you just created.
6. Only after reviewing that report, run `php alpha/tools/import_mike.php apply BUSINESS_ID I-UNDERSTAND-THIS-IS-PARTIAL`. The destination import uses one transaction, rejects a repeat import, and changes no source rows. Keep the inventory output with the backup record. Compare customers, properties, jobs, status and sample scopes in the private app.

The importer has not been run against Mike's actual data or server. No domain or web deployment is included in this change.

## Before retiring the old tracker

- Import and reconcile tasks, sessions and breaks, workers, reports, goodwill, materials, receipts and their original images, payments, invoice ledger, approval history, SMS history, job photos and social media records. Map every reference to the new business and job IDs.
- Audit all `work_*` tables plus other site data that Mike wants to retain. Compare row counts, money totals, timestamps and media hashes. Report missing files and any records that cannot map to a customer or job. Do not silently drop them.
- Build or migrate the corresponding tenant-safe Work Tracker pages and background jobs. Keep customer portal links private until replaced; the legacy public tokens are not copied.
- Establish a brief write freeze and run a final delta import after Mike has tested the rehearsal; then confirm a rollback path and backup restoration. No automatic switch from the old site is implemented.
