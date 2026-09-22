MIKE OF ALL TRADES — V8.47 JOB + CUSTOMER PORTAL NAVIGATION

CHANGES
- Replaces the long admin accordion workspace with a job overview hub.
- Adds focused job views for customer/schedule, pricing/agreement, tasks,
  time/workers, materials/receipts, media, billing, messages, goodwill and settings.
- Automatically merges duplicate destinations such as Pricing & agreement while
  preserving the contents and forms from both original cards.
- Removes the unnecessary section-reordering controls from focused job views.
- Makes global search visually obvious with a magnifying glass and Search label.
- Adds a private receipt-image/PDF gallery for each job.
- Adds a customer portal hub, customer-safe page navigation, and a prominent
  Search your job record field.
- No database migration is required.

FILES
- includes/admin_nav.php
- api/work/admin_search.php
- admin/work/manage_job.php
- admin/work/assets/workspace_v8_3.js
- admin/work/assets/workspace_v8_8.css
- admin/work/receipt_images.php
- work/customer_job_record.php

TEST
1. Open a job. The default page should show quick actions, summaries and the
   new Job management hub rather than the long accordion list.
2. Open Scope, pricing & agreement. Confirm all status and editable settings
   are on one destination with no duplicate shortcut.
3. Open Tasks, Time, Materials and Billing and confirm only relevant sections
   appear on each page.
4. Search for slideshow, receipt, customer and timer in the admin search.
5. Open Receipt images and confirm stored images/PDFs can be viewed.
6. Open a customer token link and test its page tabs and search.
