MIKE OF ALL TRADES — V8.20 QUICK-ACTION DASHBOARD
=================================================

WHAT THIS PATCH CHANGES
-----------------------
- Makes the Manage Job quick-action icons larger, clearer and easier to tap.
- Adds highly visible state pills: START, ON / TAP TO STOP, and BREAK ON / TAP TO RESUME.
- Keeps the active activity green and active breaks amber.
- Displays the job's current customer-SMS policy directly above the buttons.
- Makes automatic quick-action SMS obey the selected policy:
  * Full transparency: every meaningful start, stop and break update.
  * Important only: travel to site, arrival, leaving, supplier departure and finish.
  * Daily only: suppress individual quick-action messages.
  * None: suppress all automatic quick-action messages.
- Clearly marks Add Photos and Add Receipt as OPEN / NO SMS.
- Preserves tap-again toggles and prevents overlapping activity timers.
- Fixes the SMS-policy save redirect so it returns to the Manage Job dashboard.

INSTALL
-------
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PATCH_ZIP="/Users/macbook/Downloads/mike-work-tracker-v8_20-quick-action-dashboard-20260917.zip"
PATCH_TEMP="$(mktemp -d /tmp/mot_v8_20.XXXXXX)"
PATCH_BACKUP="backups/v8_20_before_$(date +%Y%m%d_%H%M%S)"

unzip -q "$PATCH_ZIP" -d "$PATCH_TEMP"

mkdir -p \
    "$PATCH_BACKUP/admin/work" \
    "$PATCH_BACKUP/api/work"

cp admin/work/manage_job.php \
    "$PATCH_BACKUP/admin/work/manage_job.php"

cp api/work/quick_action.php \
    api/work/save_update_mode.php \
    "$PATCH_BACKUP/api/work/"

cp "$PATCH_TEMP/admin/work/manage_job.php" \
    admin/work/manage_job.php

cp "$PATCH_TEMP/api/work/quick_action.php" \
    "$PATCH_TEMP/api/work/save_update_mode.php" \
    api/work/

cp "$PATCH_TEMP/README_INSTALL_V8_20_QUICK_ACTION_DASHBOARD.txt" .

echo
echo "V8.20 INSTALLED"
echo "Backup saved at: $PATCH_BACKUP"

LINT
----
cd /Applications/XAMPP/xamppfiles/htdocs/mike_of_all_trades

PHP_BIN="/Applications/XAMPP/xamppfiles/bin/php"

"$PHP_BIN" -l admin/work/manage_job.php
"$PHP_BIN" -l api/work/quick_action.php
"$PHP_BIN" -l api/work/save_update_mode.php

git diff --check

SAFE TEST CHECKLIST
-------------------
Use a test job with your own mobile number first.

1. Set Customer updates to Full transparency.
2. Tap Work: it turns green and says ON / TAP TO STOP.
3. Tap Work again: the timer stops and an SMS is recorded.
4. Start Work, then tap Coffee break: it turns amber.
5. Tap Coffee break again: the break ends and work resumes.
6. Confirm the full SMS text appears in the confirmation toast and SMS history.
7. Set Customer updates to None and repeat a start/stop test.
8. Confirm the activity is recorded but no SMS is sent.
9. Tap Add Photos and Add Receipt; confirm neither changes the timer nor sends SMS.

No database migration is required.
