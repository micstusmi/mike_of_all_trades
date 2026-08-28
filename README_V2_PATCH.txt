MIKE OF ALL TRADES — WORK TRACKER V2 PATCH

1. Import migration_v2.sql into the existing mike_of_all_trades database in phpMyAdmin.
   It adds:
   - original pricing type
   - agreement version
   - separate current-balance acknowledgement
   - continuation authorisation
   - immutable JSON snapshot of what was signed

2. Replace these existing files with the patch versions:
   /admin/work/new.php
   /work/job.php
   /api/work/sign.php

3. Refresh the test job.
   Existing jobs will display "Not yet specified" for Original pricing arrangement.
   New jobs will require you to choose:
   - Fixed-price quote
   - Estimate / approximate budget
   - Hourly rate
   - No specific price was agreed

4. For the cleanest test, create a NEW test job after installing this patch.

IMPORTANT:
The agreement snapshot is stored in work_jobs.agreement_snapshot_json at signing time.
That means later edits to live job information do not silently change the historical data
that the customer actually signed.
