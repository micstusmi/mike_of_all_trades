MIKE OF ALL TRADES — V9 GST SAFETY AND ADMIN NAVIGATION

Upload this package over the current web application, preserving the existing
server configuration and database.

CHANGES
- Zoho invoice lines explicitly clear tax_id and use 0% tax.
- New and synchronised drafts are rejected if Zoho reports GST or a total that
  differs from the GST-free Work Tracker total.
- Emailing now fetches the live Zoho draft and performs the same safety check.
- Existing incorrect draft: open it from Invoice drafts and choose
  SYNC CURRENT ZOHO DRAFT before attempting to send it.
- The admin header now has a categorised hamburger menu.
- Invoice drafts has its own page and wording.
- Add task is available from the header, current-job shortcuts, menu and search.

IMPORTANT
Do not send the existing INV-000190 as shown in the supplied screenshot. It
contains $15.05 GST. After deployment, synchronise that draft; the application
will block email unless Zoho returns $0.00 tax and the correct GST-free total.
