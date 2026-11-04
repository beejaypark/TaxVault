# TaxVault

TaxVault is an Australian Financial Year-centric evidence and record management tool. It is not a tax-return or lodgement app: it automatically assembles an evidence package for your accountant.

The current UI provides a live dashboard with cash-flow charts, a multi-year
forecast and spending observations, document upload (web, mobile camera, email
forwarding) and review, financial year management, manual income/expense entry,
property and investment records, and an accountant pack (JSON evidence export,
transactions CSV, printable summary and review). Automatic bank feeds and
reconciliation, tax calculations and ATO lodgement are out of scope.

The shared financial-year selector scopes the dashboard, documents,
transactions, and review queue. Financial-year management and property and
investment asset records remain unfiltered because they are maintained across
years; an accountant pack still requires an explicit export year. Financial-year
labels are displayed consistently as `YYYY - YY` (for example, `2025 - 26`);
stored and API financial-year codes remain `YYYY-YY`.

## Architecture

- Frontend: Angular 22+
- Backend: Laravel 13+
- Database: PostgreSQL
- Queue / Cache: Redis
- Object Storage: S3-compatible / MinIO
- Authentication: Keycloak / OIDC
- Deployment: Docker / Docker Compose

## Repository

https://github.com/beejaypark/TaxVault

## Development

The local development environment runs in Docker Compose. Install and start
[Docker Desktop](https://www.docker.com/products/docker-desktop/), then run these
commands from the repository root in PowerShell:

```powershell
Copy-Item .env.example .env
docker compose up --build -d
docker compose ps
```

Open the app at <https://localhost:8443> (`http://localhost:8080` redirects there). On first startup, the backend creates
an application key and runs the database migrations. The key, database, and
uploaded files are kept in Docker volumes. Local development uses the backend's
persistent local file storage.
The backend also seeds a small, versioned set of general document types for
receipt uploads; it does not apply tax classifications or tax treatment.
The upload screen supports selecting multiple files or a whole folder, and
uploads documents sequentially with per-file validation and retry for failures.
Each file is subject to the 20 MB limit and uses the selected financial year
and document type.
If the account does not yet have a financial year, documents can be uploaded
without one and processed locally. When extraction identifies an invoice date,
the documents list suggests the Australian financial year; confirm it to create
the year and link the document before its review is created. If OCR cannot
identify a date, choose an existing year or create one manually before review.
Uploads are checked against the authenticated user's existing documents by
SHA-256 content hash, so renamed copies are also detected. Exact duplicates are
not stored or queued for OCR; the upload screen identifies the existing
document and its financial year. Previously stored documents are retained.
PDF and image uploads are queued for local text extraction using Poppler and
Tesseract; documents are not sent to an external OCR service. The
`document-worker` service processes uploads and creates a review task; it must
be running for uploaded files to finish processing. Reviewers can open the
original file and optionally record a linked income/expense transaction after
verifying the extracted date and amount. Document, dashboard and review
statuses, along with transaction records, refresh automatically every five
seconds while their pages are open.

When using the Angular development server directly at <http://localhost:4200>,
the Keycloak sign-in link starts authentication through the same-origin
backend at <https://localhost:8443> so the callback can use the correct session
cookie.

Keycloak runs in production mode (`start`) with its data in the `keycloak` PostgreSQL database, behind nginx which terminates TLS. It imports the local `taxvault` realm and its public OIDC client on
startup. Create application users in that realm using the Keycloak admin
console at <https://localhost:8443/auth>; the admin username and password are
the `KEYCLOAK_ADMIN` and `KEYCLOAK_ADMIN_PASSWORD` values in `.env`. The realm
uses Authorization Code with PKCE and redirects through the same-origin
`/api/auth/oidc/callback` endpoint. The import does not overwrite an existing
realm, so existing Keycloak data remains intact.

At startup, the backend provisions a local application admin in
`development_admin_credentials` when `APP_ENV` is `local` and
credentials are configured. By default, its login and password reuse
`KEYCLOAK_ADMIN` and `KEYCLOAK_ADMIN_PASSWORD`; optional `DEV_ADMIN_LOGIN` and
`DEV_ADMIN_PASSWORD` values override them. The password must be at least 12
characters and is stored as a hash. Missing credentials skip provisioning
without blocking database migrations or backend startup. Local development
login is enabled by `DEV_ADMIN_LOGIN_ENABLED` and is unavailable in production.
After changing backend code or environment values, rebuild and recreate it:
`docker compose up -d --build --force-recreate backend`.

To follow application logs, run `docker compose logs -f`. Stop the services
with `docker compose down`; this keeps persisted data. `docker compose down -v`
also removes the persisted database, application key, and uploaded files.

The Compose file includes PostgreSQL, Redis, and Keycloak for local development.
Its default credentials in `.env.example` are for local use only; change them
before exposing the services to a network.

### HTTPS (local, mkcert)

nginx serves the app over TLS on port 8443 using a locally trusted certificate:

```powershell
winget install FiloSottile.mkcert
mkcert -install
mkcert -cert-file infrastructure/certs/localhost.pem -key-file infrastructure/certs/localhost-key.pem localhost 127.0.0.1 ::1
docker compose up -d
```

The certificate files are git-ignored. Session cookies are `Secure`, and
PostgreSQL, the backend and the dev server are published on `127.0.0.1` only.
On a real domain, replace the certificate, set `APP_URL`,
`KEYCLOAK_PUBLIC_URL` and `KEYCLOAK_REDIRECT_URI` to the public https URLs, set
strong `KEYCLOAK_ADMIN_PASSWORD`/`POSTGRES_PASSWORD` values, and enable the HSTS
header in `infrastructure/docker/nginx/default.conf`.
## Architecture Documentation

Security, encryption, audit and data deletion policies are in [docs/security.md](docs/security.md).

The authoritative architecture and product decisions are maintained in the TaxVault Notion project documentation.
