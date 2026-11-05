# TaxVault features

TaxVault organises evidence for your accountant. It does not prepare or lodge tax returns and does not give tax advice.

## Accountant pack

- Per financial year: evidence totals by tax return item, a transaction ledger, source documents and a prior-year comparison.
- Transactions without a tax return item are listed separately and excluded from the totals.
- Tax return item names and references are guidance only; confirm them with your accountant.

## Income & expenses

- Filter by type and search descriptions. Select several rows to categorise them in bulk, or use **Suggest** to pre-fill items from the description (it only applies to the visible, selected rows).
- **Import bank file** reads CSV, OFX and QFX exports in the browser. Rows are previewed first and nothing is sent to a third party. Re-importing the same file skips rows that already exist.
- **Working from home** logs hours per financial year using an editable cents-per-hour rate (default $0.70 for 2025-26).

## Global search

- Press `Ctrl+K` (`⌘K` on macOS) or use the search button in the header.
- Searches pages, documents, transactions, properties, investments and financial years. Every word must match.
- Opening a document or transaction result applies the search term as a filter on that page.
- Search runs in the browser over data already available to your account.

## Notifications

- The bell shows documents that need review, failed processing or need a financial year, and transactions without a tax return item (for the selected financial year, or all years).
- It refreshes every minute and when opened.

## Settings

- Profile (read-only), the financial year selected at sign-in, and notification toggles.
- Preferences are saved to your account and follow you across browsers; a copy is kept in the browser as a fallback.
- Preferences saved in a browser before this existed are uploaded to your account the first time you sign in.

## Email reminders

- Opt-in under Settings > Email reminders (off by default; not shown to accountants).
- Weekly review digest: at most one email a week, only when documents need review, failed processing or need a financial year.
- Lodgement reminders: 30, 7 and 1 day before the deadline. The default is 31 October after each financial year ends; you can set your own date. These are reminders only, so confirm the real date with the ATO or a tax agent.
- "Send test email" checks the mail setup; "Add to calendar (.ics)" downloads an all-day event with 7-day and 1-day alerts.
- Every email has an unsubscribe link that turns both email settings off.
- Dashboard shows the next lodgement date as a countdown card with a checklist (documents waiting for review, transactions without a tax return item). Past dates are hidden. "Mark as done" stops emails and bell alerts for that date; "Reopen" undoes it.
- The bell shows a lodgement-date notice when the date is 30 days or less away and not marked done.
- Sending: the `scheduler` container runs `notifications:send-emails` daily at 08:00 (`NOTIFICATION_TIMEZONE`, default Australia/Sydney). Each email is sent once (tracked in `email_notification_logs`).
- Mail server: development uses Mailpit (inbox at http://localhost:8025). For Gmail set `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, an App Password in `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` equal to the Gmail address in the root `.env`. Real Gmail sending has not been tested.

## My assets

The dashboard opens with a "My assets" overview.

- **Values:** enter a current value (and date) on a property or investment. Each save adds a dated entry to the valuation history. Investments without a value fall back to their cost base.
- **Net worth:** assets minus loans. Loans use the original loan amount (repayments are not tracked).
- **Cash flow:** income, expenses and savings rate for the last 12 months, from transactions.
- **Trend:** net worth over time, built from the recorded valuation history.
- **Projection:** 5, 10 and 20 years in conservative, base and optimistic scenarios, with sliders for income growth, expense growth, asset growth and loan interest. Loans are treated as interest-only. These are estimates, not financial or tax advice.
- **Strengths and improvements:** rule-based notes on savings rate, gearing, concentration, interest-rate sensitivity and stale values.

## Known limits

- Live bank feed (optional): set `BASIQ_API_KEY` in the root `.env` and restart. The Import panel then shows "Connect bank" and "Sync now". You approve read-only access with your bank through Basiq; synced rows arrive uncategorised and are de-duplicated by Basiq transaction id. Without a key the feature is hidden. It was verified against the live Basiq API for token, user creation, consent URL and transaction listing (the probe user had no connected bank, so no real rows were imported); the import path is covered by mocked tests.
- File import (CSV/OFX) remains available.
- Bank data is imported from files; there is no live bank connection.

## Reviews: enter missing details by hand

- When OCR finds no total amount or issue date, the review page shows an "Add missing details" card with an amount and a date field.
- Saved values are stored as reviewer corrections (amount > 0, real date). Payment suggestions use them automatically.

