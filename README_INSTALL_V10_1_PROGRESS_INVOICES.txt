ezTradie V10.1 — historical and progress invoices

Purpose
- Link an existing Zoho invoice to one customer and one or more jobs.
- Keep ongoing jobs open after a paid progress invoice.
- Reconcile individual labour sessions and materials to the invoice.
- Exclude only reconciled source records from later invoices.
- Record new ezTradie-sent invoices in the same invoice ledger.

Safety
- Importing never edits, emails or deletes the Zoho invoice.
- The invoice must belong to the deliberately linked Zoho customer.
- Historical source records are not excluded until Mike confirms them.
- Tax shown on an old invoice remains an immutable historical value.

Install
1. Back up the changed files and database.
2. Install the patch files.
3. Run: php tools/install_v10_1.php
4. Confirm PHP syntax, then test invoice import without creating a new invoice.
