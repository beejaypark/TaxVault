# TaxVault project guide

## Product

TaxVault is an Australian financial-year-centric evidence and record management app. It helps individuals and small businesses collect, review, organise and share records with an accountant. It is not a tax-return preparation or lodgement service and must not present tax, legal or financial estimates as professional advice.

Core workflows include document upload and OCR review, income and expense records, financial-year management, property and investment records, accountant access and evidence exports. Users can import documents from connected OneDrive, Google Drive and Dropbox accounts. Billing and trial flows are also part of the product.

## Stack and layout

- `frontend/`: Angular 22+, TypeScript, Angular Material. Feature areas live under `src/app/features`; shared UI and translation code live under `src/app/shared` and `src/app/i18n`.
- `backend/`: Laravel 13+, PHP 8.3+, PostgreSQL. Routes, domain logic, jobs, services and models are under `routes/` and `app/`; migrations and seeders are under `database/`.
- `infrastructure/` and the root `docker-compose.yml`: nginx/TLS, Keycloak and local service configuration.
- `docs/`: product feature and security documentation. Update relevant docs when behavior or operational procedures change.

## Platform upgrades

- Before upgrading either platform (for example, Angular on the frontend or Laravel/PHP on the backend), ask the user whether to proceed. Do not begin or include a platform upgrade as part of another task without their explicit approval.
- This approval rule applies to framework/runtime version upgrades. Routine application changes and compatible dependency maintenance that do not upgrade the platform do not need this question.

## Deployment and Git workflow

- Read `.agents/skills/deployment-and-development/SKILL.md` before changing deployment configuration and before every Git commit or push.
- Keep local development, staging, and the production release VPS separate. The local setup uses `localhost`; `taxvaultapp.com.au` belongs to the production release. Never share environment secrets, databases, storage volumes or encryption keys between environments.
- Complete the skill's staged-diff and environment-boundary checklist before committing or pushing. This instruction does not itself authorize a commit, push or deployment.

Use the root README for local setup and the security guide at `docs/security.md` for data lifecycle and security details. Local development is Docker Compose based.

## Domain and product rules

- Financial-year codes stored by the API use `YYYY-YY`; UI labels use `YYYY - YY` (for example, code `2025-26` is shown as `2025 - 26`). Keep this distinction consistent.
- The shared financial-year selection scopes year-specific dashboard, document, transaction and review views. Asset records and financial-year management span years; accountant exports require an explicit year.
- Preserve original source documents and their relationship to extracted values, reviewer corrections and audit history.
- Storage integrations must feed the same provider-neutral document, OCR and review workflows. Keep provider authorization and sync metadata separate from tax/evidence logic. Never expose provider secrets or tokens in client responses or logs.
- Do not add tax calculations, ATO lodgement, or claims that the app determines tax treatment. Tax-return item names are guidance and users should confirm them with an accountant.
- Keep user-facing copy and help aligned with the implemented behavior, including limits and setup requirements.

## Security and data handling

- Every user-owned query and mutation must be scoped to the authenticated tenant. Validate that referenced IDs belong to the caller; cross-tenant resources should behave like missing resources. Extend tenant-isolation coverage when adding ID-based endpoints.
- Treat documents, financial records, OAuth credentials and billing data as sensitive. Do not put secrets, tokens, document contents or passwords in logs, fixtures, screenshots or committed files. Never read or copy values from local `.env` files into source or documentation.
- Sensitive actions should follow the existing audit-event middleware and conventions. Audit records are append-only.
- Preserve document encryption and deletion semantics. Changes to encrypted storage, key handling, backups or restore procedures require particular care and corresponding documentation updates.
- Accountant access is read-only and limited by granted sections and financial years; enforce these restrictions server-side.

## Frontend conventions

- Keep screens consistent with existing Angular feature and shared-component patterns; avoid duplicating API or translation behavior.
- The UI has English, Chinese and Japanese translation resources under `frontend/src/assets/i18n/`. When changing user-facing text, update all supported locales and preserve interpolation placeholders and key parity. Run `npm run check:i18n` from `frontend/` when asked to verify translation completeness.
- Keep API/database enum values stable and untranslated; translate only their display labels.
- Prefer accessible controls, clear loading/error states and mobile-friendly layouts.

## Backend conventions

- Follow existing Laravel patterns for validation, authorization, tenant-scoped queries, transactions and API response shapes.
- Put business rules in the established domain/service layers rather than embedding them in controllers.
- Queue expensive document processing and external-provider work using the existing job/worker design; make retry behavior safe and avoid duplicate imports.
- Add or update migrations for schema changes; do not edit deployed schema assumptions by changing old migrations without checking project conventions.

## Useful commands

From the repository root, local services are managed with `docker compose` (see `README.md`). Useful project checks include:

```powershell
# Frontend
cd frontend
npm run build
npm run check:i18n

# Backend (inside the backend container, or a configured PHP environment)
php artisan test
composer lint
composer analyse
```

Run only checks relevant to the change and requested by the task. Do not use `docker compose down -v` unless intentionally deleting local database, key and uploaded-file volumes.

## Documentation and scope

The root README and `docs/` describe current product behavior and operations; consult them before changing those areas. Some docs may lag behind recent integrations, so confirm behavior in the implementation before treating a document as authoritative. Avoid expanding product scope beyond the requested change.
