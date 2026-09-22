MIKE OF ALL TRADES — V9.1 ZOHO ZERO-TAX ID CORRECTION

Zoho's Australian organisation ignored a blank tax_id and reapplied the
default 10% GST. V9.1 lists the organisation's configured taxes and assigns a
real active 0% tax_id to every invoice line.

The existing GST and total safety checks remain in force. If the OAuth token
cannot read tax settings, or the organisation has no active 0% tax, syncing
stops with a specific instruction and the invoice cannot be emailed.

No database migration is required.
