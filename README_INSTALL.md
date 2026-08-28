# Mike of All Trades — Work Tracker MVP

This MVP is deliberately focused on getting a live, defensible job record running quickly.

## What it already does
- Create a new or already-in-progress job.
- Record original scope, expanded scope, unforeseen conditions, original estimate and revised forecast.
- Record value of work/materials already incurred **without pretending those terms were signed earlier**.
- Public customer job page with finger signature for an "agreement to continue".
- Multiple workers and separate hourly rates.
- Unlimited start/stop work sessions across days/weeks.
- Session categories: onsite, measurement, planning, procurement, travel, loading/setup, demolition, repair, unforeseen, other.
- Materials and who paid.
- Running job total, payments and outstanding balance.
- Progress-payment recording/request.
- Daily reports with customer acknowledgement/comments.
- SMS Broadcast sending.
- Inbound SMS webhook logging; YES/NO/PAUSE/STOP WORK recognised.
- Delivery-status webhook endpoint.

## Install

1. Copy the folders/files into your site root so these paths exist:
   - `/includes/work_tracker.php`
   - `/admin/work/`
   - `/api/work/`
   - `/work/job.php`
   - `/uploads/job_signatures/`

2. Import `database_work_tracker.sql` into the same MySQL database used by the site.

3. `includes/work_tracker.php` expects your existing `/includes/db.php` to expose a PDO connection as `$pdo` or `$db`.
   If yours uses a different variable, change the small PDO block at the top.

4. Make `/uploads/job_signatures` writable by the web server. Typical Linux example:
   ```
   sudo mkdir -p /var/www/html/uploads/job_signatures
   sudo chown -R www-data:www-data /var/www/html/uploads/job_signatures
   sudo chmod 750 /var/www/html/uploads/job_signatures
   ```

5. Configure environment variables on Lightsail/Apache:
   ```
   WORKTRACKER_BASE_URL=https://www.YOURDOMAIN.com.au
   SMSBROADCAST_USERNAME=YOUR_API_USERNAME
   SMSBROADCAST_PASSWORD=YOUR_API_PASSWORD
   SMSBROADCAST_FROM=YOUR_DEDICATED_SMS_NUMBER
   ```

   Do not commit SMS credentials to GitHub.

6. In SMS Broadcast, configure inbound replies to:
   `https://www.YOURDOMAIN.com.au/api/work/sms_inbound.php`

   Configure delivery status updates to:
   `https://www.YOURDOMAIN.com.au/api/work/sms_status.php`

7. Open:
   `/admin/work/index.php`

## IMPORTANT before exposing it publicly
This package does NOT know the exact session variable used by your existing `/admin/login.php`.
Protect `/admin/work/*` and the admin-facing `/api/work/*` using the same authentication guard as your existing admin pages BEFORE relying on it on the public internet.

The customer page is intentionally accessed by a random 64-character token.

## For the current job
Create it using "New / Current Job". Enter:
- the original work discussed,
- all additions,
- unforeseen conditions,
- original estimate,
- a realistic revised range,
- work/material value incurred to date,
- payments received,
- rate that applies **from the new signed agreement onward**.

Then open the job and press "SMS agreement/report link".
The customer signs the current agreement from their own phone.

## Next build phase
- photo upload + automatic compression
- receipts
- video/object-storage integration
- automatic day-before booking SMS + YES/NO calendar status
- variation approval records
- editable/revised agreements with version history
- worker/session editing
- non-billable sessions
- Zoho invoice/progress invoice hand-off
- PDFs
- stronger CSRF/auth controls and audit trail
