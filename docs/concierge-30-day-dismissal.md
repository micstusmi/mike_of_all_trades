# Floating AI concierge: 30-day automatic-popup dismissal

Base: 378f72229d16c9104bc2e2abb4173683697398f2. Mike requested this change for the Micstusmi instance on 26 October 2026.

The public header's existing concierge now offers “Don't show for 30 days”. An expiry timestamp in localStorage suppresses automatic invitations across pages and visits in the same browser/origin. The permanent Ask AI button still opens manually. The normal close and No thanks controls retain their visit-only behaviour. Expired or malformed preferences allow the automatic invitation again. Clearing browser storage, another browser/device, or the alternate www/apex origin has its own preference.

All session-storage operations now tolerate blocked browser storage. If the durable preference cannot be saved, show a truthful message in the popup instead of promising it was saved; normal close/manual controls remain usable. No account, server session, quote routing or customer information is stored by this new preference.

Verified PHP syntax, actual PHP-rendered header in Chromium, initial ten-second invitation, thirty-day timestamp, reload/new-tab suppression, manual reopen while snoozed, expiry/malformed values and blocked local/session storage. No live provider, database or Work Tracker changes. Phone appearance is a separate device acceptance step.

Deploy only ops/deploy-concierge-snooze.py from the exact reviewed commit, with SHA256 verified before execution, as root on hostname mikeofalltrades. It discovers the existing HTTPS site's DocumentRoot, takes the shared deployment lock, verifies each original popup fragment before modifying it, preserves unrelated header edits, backs up the original header under /var/backups/mikeofalltrades/concierge-COMMIT/header.before.php, lints a staged PHP file, atomically replaces only includes/header.php, and checks the locally resolved HTTPS homepage. It rolls back that one header on failed HTTP smoke if no intervening edit occurred. A changed/unrecognised popup aborts for reconciliation. Matching interrupted backups and already-applied patches are safe to retry. No vhost, database, other repository file, tracker or service restart is performed.

Source merged does not mean installed. The operator's successful console result and browser check establish deployment. The new choice is per browser, not an account-wide setting.
