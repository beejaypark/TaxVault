# TaxVault launch readiness

This checklist is the release gate for the current product direction: a trusted evidence-preparation workspace between a taxpayer and their accountant.

## Ready in the current workspace

- Tax readiness is calculated by the backend and exposed through a versioned API.
- Dashboard shows readiness score, completion denominators, blocking issues, and previous-year comparison when available.
- Properties and investments expose portfolio value, debt/cost-base, and missing-value signals.
- Accountant Pack shows a pre-send readiness preview.
- Required field validation is applied across the main data-entry workflows.
- OCR failures and extraction timeouts are surfaced as processing issues.
- Production Angular build completes with the configured application budgets.

## Release blockers before real users

- Configure a real `MAIL_FROM_ADDRESS`; the accountant send form intentionally starts blank and requires an explicit sender.
- Set production `APP_ENV`, `APP_URL`, `KEYCLOAK_PUBLIC_URL`, and `KEYCLOAK_REDIRECT_URI` to the public HTTPS host.
- Replace all development Keycloak, database, and development-admin passwords with secret-manager values.
- Configure production object storage and verify encryption-key backup and restore.
- Run an end-to-end upload, OCR, review, export, and accountant-access test with a non-production account.
- Confirm retention, deletion, and data-export procedures with the target jurisdiction and accountant workflow.

## Pilot acceptance criteria

Before launch, run at least three real-world pilot cases and record:

1. Can a user explain why the readiness score has its value?
2. Can every blocking issue be opened and resolved without searching manually?
3. Can an accountant identify included data, missing evidence, and reviewer corrections from the pack?
4. Can the user recover from OCR failure without losing the original document?
5. Can the team reproduce the export and audit history for the same financial year?

## Decision

TaxVault should enter a limited pilot only after the release blockers are configured and the pilot acceptance criteria are observed. The product is not yet ready for an unrestricted public launch because email delivery, production secrets, storage recovery, and jurisdiction-specific operating procedures still require environment-level verification.
