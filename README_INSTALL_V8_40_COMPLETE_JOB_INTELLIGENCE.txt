MIKE OF ALL TRADES — V8.40 COMPLETE JOB INTELLIGENCE
====================================================

THIS REPLACES THE EARLIER V8.40 SAFE-TASK-UPDATE ZIP.
Do not install both packages. This complete package includes that work plus the
receipt and holistic-reel functionality.

WHAT THIS RELEASE ADDS
----------------------
1. Safe AI task reconciliation
   * Mike or the customer can paste a revised list or attach up to 10 task-list
     screenshots, photos or PDFs.
   * AI compares the evidence with the existing task board.
   * Mike reviews each proposed addition/update before applying it.
   * Existing IDs, status, photos, sessions and recorded time are preserved.
   * Established task boards can no longer be destructively regenerated.

2. Receipt capture, bulk upload and background OCR
   * Photograph receipts on a phone while shopping.
   * Upload multiple images/PDFs or a ZIP containing up to 60 receipts.
   * Link a capture to a task and, when launched during procurement, the active
     procurement activity.
   * Duplicate files are detected per job using SHA-256.
   * AI extracts supplier, receipt number, date, line items, gross, GST and net.
   * Processing runs in the background and the page displays status updates.

3. Human-reviewed financial records
   * Every OCR result is displayed beside the original receipt.
   * Mike approves/edit-checks receipt totals and individual line items.
   * Only approved items enter the materials ledger and financial totals.
   * Records retain payer, reimbursement treatment, task, supplier, receipt
     number, purchase date, GST-inclusive amount, GST and excluding-GST amount.
   * The original approved scanned receipt remains viewable from ledger items.

4. Holistic reel intelligence
   Reel planning now considers the complete approved job record:
   * all non-cancelled tasks and detailed procedures;
   * the customer request/current scope;
   * the complete materials ledger;
   * approved scanned receipt line items and totals;
   * recorded job/procurement/travel activity history;
   * metadata for every job photo;
   * visual contact sheets containing the complete job-photo history; and
   * full-size selected slideshow images in before → in progress → after order.

   The prompt asks for a specific, truthful, persuasive transformation story
   showcasing Mike's preparation, problem-solving, care, workmanship, materials
   and visible outcome without turning the narration into a shopping list or
   inventing unsupported/licensed work. Spoken narration and visible captions
   remain identical, and expressive voice-over remains the default.

MANDATORY INSTALLATION ORDER
----------------------------
1. Back up the project and database.
2. Copy this package into the project, preserving folders.
3. Run tools/migration_v8_40_complete_job_intelligence.sql against the Work
   Tracker database before opening Materials / Receipts.
4. Run PHP syntax checks on every changed PHP file.
5. Confirm PHP ZIP support for ZIP receipt uploads. Direct image/PDF uploads work
   independently.
6. Confirm the background receipt worker can use PHP_BINDIR/php. If the server
   uses another CLI binary, set WORKTRACKER_PHP_BIN to its absolute path.
7. Test one receipt and one task comparison on a non-critical job, review the
   results, then test Job #4.

IMPORTANT ACCOUNTING NOTE
-------------------------
OCR is evidence assistance, not an accounting authority. Receipt images can be
blurred, folded or partially obscured, and GST treatment can vary by line item.
The review screen deliberately requires Mike to compare values with the source
receipt before approval. The website identifies supplier-receipt GST; it does
not itself decide Mike Of All Trades' BAS or tax obligations.

ROLLBACK
--------
Restore the backed-up PHP files. The new tables can remain safely for audit and
history because older code does not use them. Do not drop them if submissions,
scans or approved receipt links need to be retained.
