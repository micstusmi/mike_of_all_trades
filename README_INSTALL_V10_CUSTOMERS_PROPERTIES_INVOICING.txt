MIKE OF ALL TRADES V10 — CUSTOMERS, PROPERTIES, ZOHO LINKS, TERMS AND NAVIGATION

Starting commit: b8f1937

WHAT V10 CHANGES
- Adds shared customer records and separate property/site records.
- Keeps every job, task, photo, material and receipt attached to its original job.
- Lets multiple jobs/properties belong to one customer.
- Preserves the job/source name (for example, "Kuma D.") separately from the full billing name.
- Makes the customer name clickable and adds an explicit Edit customer action.
- Renames the global Jobs link to All jobs and removes the redundant local text-link row.
- Stores customer-level payment terms. Fontaine Industries is backfilled as Net 15; all other customers default to Due on Receipt.
- Shows terms and due date before draft creation.
- Sends payment_terms, payment_terms_label and due_date to Zoho.
- Refuses to send when Zoho's customer, total, GST or due date does not match the approved Work Tracker snapshot.
- Requires an explicitly selected existing Zoho contact ID before invoicing; invoice creation never creates a contact automatically.
- Supports one combined invoice for selected uninvoiced jobs belonging to the same customer, with job/property labels on every charge.

SAFE DATA MIGRATION
Run tools/install_v10.php once after installing the files. It is idempotent.
Every existing job is initially given its own customer and property record. V10 deliberately does not guess which historical jobs belong to the same person. Use the customer page's "Link another existing job" control to connect confirmed matches.

KUMA WORKFLOW
1. Open either Kuma job and click the customer name or Edit customer.
2. Change the full billing name to Kuma Dadallage; retain Kuma D. as the source alias.
3. Search Zoho and link the existing Kuma Dadallage record.
4. Link Kuma's other existing job to this same customer. Its property, job history and photos remain separate.
5. Select either or both uninvoiced jobs when preparing an invoice.

FONTAINE WORKFLOW
Fontaine jobs are initially backfilled as separate customer records, each with Net 15. Confirm one Fontaine record, link the correct existing Zoho contact, then link any other Fontaine jobs that belong to that same account.

ZOHO PDF HEADING — ONE-TIME ZOHO SETTING
Zoho controls the printed heading (currently "Tax Invoice") in its PDF template, not in the invoice-create payload. The official API can assign an existing template but cannot redesign the heading text. In Zoho Invoice, customise or duplicate the active invoice template and change the document title from "Tax Invoice" to "Invoice", then make that template the default for invoices. V10 continues to enforce the organisation's real 0% "No GST" tax ID.

DO NOT
- Do not merge property or job records.
- Do not link a Zoho search result unless the name/email/phone confirms it is the correct person.
- Do not create or send a combined invoice containing a job marked ALREADY INVOICED.
