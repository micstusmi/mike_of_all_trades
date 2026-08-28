Mike of All Trades Work Tracker V6

Adds:
- Permanent retrospective/past session entry with worker, date, start/finish, location, activity, notes and billable flag.
- Retrospective entries are visibly separated from live-tracked sessions on the customer page.
- Audit metadata records when a retrospective entry was actually entered and why.
- On My Way workflow with ETA and customer SMS (full_transparency or important_only modes).
- Travel to customer is a separate billable job-travel session and appears in job history/time breakdown.
- Arrived & Start Work atomically stops the travel session and starts on-site work.
- Retrospective entries do not generate fake historical Start/Stop SMS messages.

Install order:
1. Back up the database/files.
2. Run migration_v6.sql ONCE.
3. Copy ONLY the files in this patch to their matching paths. Do not replace whole directories.
4. php -l the PHP files.
5. Test on a test job before using a real customer job.

New API files:
api/work/add_retrospective_session.php
api/work/on_my_way.php
api/work/arrive_start_work.php

Modified files:
admin/work/manage_job.php
work/customer_job_record.php
api/work/start_session.php
api/work/stop_session.php (included unchanged for version completeness)
