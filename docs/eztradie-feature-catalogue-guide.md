# ezTradie feature catalogue and sales presentation guide

## Truthful availability labels

The catalogue at `alpha/catalog/features.json` drives `/features` in the isolated alpha prototype. The status of the legacy Mike of All Trades site and the status of ezTradie alpha are separate fields. A feature on Mike's site is a **demonstration of the idea**, not proof that another business can safely use it. Do not show customer addresses, receipts, faces, phone numbers, or invoices in screenshots without permission and redaction.

No screenshot is published until reviewed. Add a screenshot path only after creating a synthetic job in a test business, checking mobile and desktop crops, and confirming there is no real customer information. Include the photographed app version and capture date in the sales asset notes.

## Change policy

- **Protected** means an existing, identified behavior or option may change only with a written reason, owner review, migration plan where needed, and a regression check. This is not a promise that it can never improve.
- **Flexible** means a concept whose wording or workflow is still open to user feedback.
- Missing from the catalogue means **unreviewed**, not permission to remove it.
- Run `python3 alpha/tools/check_catalog.py` in every change to the legacy job page. The protected controls contract currently covers its quick actions, activity transition, locations and activity types. Add contracts for each page as it is migrated, then compare screenshots and behavior before release.
- A customer-facing claim must use the **ezTradie alpha** availability field. Features planned for alpha should appear under an explicit future-features heading in a pitch.

## Presentation outlines

### Two minutes — overview (five slides)

1. The problem: a busy tradie moves between site, supplier, customer calls, receipts and invoicing.
2. Mike-site demonstration: one-tap travel, arrival, breaks and task tracking. Mark it as **pilot functionality to be migrated**.
3. Mike-site demonstration: before/progress/after photos, receipt review and a reel example, using synthetic media.
4. ezTradie alpha: separate businesses, controlled invitations, and a clear list of what works now versus what is coming.
5. Invitation to collaborate: request features, report pain points and choose relevant integrations. No public signup claim.

### Five minutes — workflow (eight slides)

Add a sample day from booking through job creation, travel, tasks, supplier purchase, photos, customer update, invoice preparation, and reel. Show the corresponding catalogue ID and availability label on each slide. Finish with an example feedback request and its status changes.

### Ten minutes — guided walkthrough (twelve slides)

Include the eight-slide workflow plus separate slides for account roles and offboarding, receipt/image review, optional AI usage and limits, and provider-specific accounting/calendar choices. Show an actual connected integration only after it has been implemented and tested in that business. Bring a live sandbox account with synthetic jobs; never demo Mike's customer records.

## Sales asset checklist

For each slide record: feature ID, audience, availability, title, one-sentence benefit, verified screenshot or demo clip, version/date, privacy review, and claim reviewer. Adjust the story for the trade and their accounting provider without changing the underlying availability label.
