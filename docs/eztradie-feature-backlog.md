# ezTradie alpha feature backlog — 2026-09-23

This backlog captures Mike's requests. It does not indicate that a feature is implemented. No tester invitations until identity, isolation and privacy gates pass.

| ID | Feature | Priority | Target | Notes / acceptance |
| --- | --- | --- | --- | --- |
| ALPHA-01 | Business isolation and invited user accounts | Blocker | Before any testers | Verified email, invitation-only registration, password reset, owner/admin/staff roles, two-business isolation tests across all routes/files/background jobs. Current alpha foundation is only a small prototype. |
| ALPHA-02 | Internal tester labels and provider profile | High | Tester onboarding | Keep actual business/account name separate from admin-only labels such as “Xero tester” and structured accounting choice (none, Zoho, Xero, MYOB, QuickBooks). Search/filter support view by label/provider. Labels grant no access. |
| ALPHA-03 | Feature requests and feedback queue | High | Early alpha | Tenant user submits request, links screenshots/affected area, sees own request status and updates. Admin triages against existing features, links similar requests without leaking other businesses’ details, sets priority, assigns status and posts updates. Suggested statuses: submitted, assessing, planned, in progress, testing, released, declined. Public roadmap only if anonymised and separately approved. |
| ALPHA-04 | SMS per business | High | Before tester SMS enabled | Send via business-scoped settings/provider, templates, sender identity, message log, delivery status, opt-in and rate/spend cap. Disabled by default. Inbound replies must map to the correct business and conversation; no Mike credentials or sender identity in another workspace. Evaluate platform-managed credits vs bring-your-own provider accounts before charging users. |
| ALPHA-05 | Quick calendar to draft job | Medium | After core job creation | Manual alpha booking drafts and an unconnected `-EZ ` import function exist. Next: one connected calendar per business; map event ID, edits and cancellations; no customer SMS or invoice until owner confirms. Other busy events may block booking availability without creating jobs. |
| ALPHA-06 | iPhone Shortcut booking path | Medium | Alongside calendar pilot | One-tap shortcut can collect title/date/time/notes and send to an authenticated ezTradie draft endpoint; optional calendar event creation. Useful for iCloud calendar or when background calendar reads are not configured. Verify supported Shortcuts actions and authentication on target iOS version. |
| ALPHA-07 | Two-way availability and scheduling | Later | After one-way import | Decide source of truth and conflict handling before linking website booking calendar, work calendar and iPhone calendar. Keep per-business calendar tokens and event mappings. Never let another business’s events affect availability. |
| ALPHA-08 | Per-business music library | Later | Media phase | Private business-owned uploads and tracks, variants for reel lengths, storage/quotas, delete/export and licence metadata. Test cutting/fades/repeats for music quality; AI song creation requires provider/licence/cost research. No copying Mike’s library into testers’ accounts. |
| ALPHA-09 | Accounting adapters | High | Before provider pilot | Provider-neutral invoice/customer interface; per-business choice and encrypted connection. Pilot with named Zoho/Xero/MYOB/QuickBooks test businesses; never share provider tokens or external IDs across businesses. |
| ALPHA-10 | Voice actions | Later | After safe job routes | Push-to-talk, propose structured changes, show user confirmation, audit actions, price and enforce budget before provider calls. No unrestricted agent access. |
| ALPHA-11 | AI cost transparency | High | Before paid AI | Usage by business/user/feature, provider cost versus AUD customer price, monthly owner-selected cap, preflight reservation, alerts and no surprise charges. Internal reservations now exist; rate card, alerts, billing and provider connections remain. |
| ALPHA-12 | Feature catalogue and sales stories | High | During alpha | Status-labelled catalogue with protected controls, evidence and curated screenshots. Separate 2, 5 and 10 minute presentations; never claim a Mike-site feature is already migrated. Initial catalogue and protected-choice check added. |

## Calendar decision to confirm

The existing Mike site reads selected Google Calendars. Determine whether Mike's iPhone events are saved to that Google account or to iCloud/another account. Google Calendar's event API supports incremental sync, but an event on an unrelated iCloud calendar will not appear through the existing Google connection. The exact “-EZ ” prefix is a user convention for importing drafts, not a native Apple trigger.

## Immediate engineering order

1. Complete ALPHA-01. Run the two-business database test on a disposable MySQL database and expand ownership checks to every retained Work Tracker route.
2. Add ALPHA-02 and ALPHA-03 so early testers are identifiable and feedback has an accountable queue.
3. Build ALPHA-04 with SMS disabled by default and test replies for two businesses.
4. Pilot ALPHA-05/06 with Mike only, then offer calendar connections to testers.
5. Add media library and music variants after the core app has safe storage and quotas.
