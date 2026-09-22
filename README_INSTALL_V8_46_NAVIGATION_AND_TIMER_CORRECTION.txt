MIKE OF ALL TRADES — V8.46 NAVIGATION + FORGOTTEN TIMER CORRECTION

WHAT THIS UPDATE ADDS
- Shared admin header, breadcrumbs, job context and related-page shortcuts.
- Global “Find anything” search for tools, actions, customers and jobs.
- Work Tracker search by customer, email, phone, address, status or job number.
- Job-page jump navigation and a consolidated Scope, pricing & agreement area.
- Customer-safe “Find something in your job record” section search. It only
  searches content already present on that customer's token-protected page.
- “I actually stopped earlier” on running activities, including duration
  preview, future/start-time validation, worker overlap protection, break
  validation, recalculated totals and an audit note.

DATABASE
- No migration is required for this release. It uses the existing
  work_sessions.entry_note audit field already used by recorded-session edits.

INSTALL / TEST
1. Copy the updated files over the matching site files.
2. No SQL migration is needed.
3. Test the admin “Find anything” box with: slideshow, receipt and a customer.
4. Open Work Tracker and test its customer/job filter.
5. Open a job, start a short test activity, choose Change/finish activity,
   select “I actually stopped earlier”, and enter a valid past finish time.
6. Confirm an overlapping finish time is rejected and a valid correction
   changes the recorded duration/totals.
7. Open the customer link and test the section finder with: tasks, schedule,
   agreement and payments.

NOTES
- This package is intentionally not committed to Git.
- Deploy and test locally before committing or pushing to Lightsail.
