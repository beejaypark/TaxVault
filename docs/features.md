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
- Preferences are stored in this browser only (`localStorage`), not on the server.

## Known limits

- Email notifications are written to the log only.
- Bank data is imported from files; there is no live bank connection.
