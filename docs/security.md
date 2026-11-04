# Security, privacy and data lifecycle

## Tenant isolation
Every record is owned by a user. Controllers and actions scope queries by the
authenticated user, request validation checks that referenced ids (financial
year, property) belong to the caller, and another tenant's resource returns
`404` exactly like a missing one. `backend/tests/Feature/Security/TenantIsolationTest.php`
covers this and must be extended whenever an endpoint that takes an id is added.

## Audit log
`audit_events` is append-only (database trigger blocks UPDATE/DELETE). Recorded:
login, logout, failed login, document upload/download/delete/retry/payment/
financial-year assignment, review decision/correction/delete, exports and
orphan purges. Entries hold actor, target id, IP and request id, never document
contents, tokens or passwords. Add new sensitive routes with the
`audit:<event>,<target type>` middleware.

## Encryption at rest
Uploaded files are encrypted with AES-256-GCM (`TVENC1` header, per-file
HKDF-derived key and random nonce) by the `documents` disk. Set
`DOCUMENT_ENCRYPTION_KEY` (base64 of 32 random bytes); if unset the key is
derived from `APP_KEY`. **Losing the key makes documents unrecoverable**, so
store it separately from backups. Files stored before encryption was enabled
remain readable and are converted with:

```
php artisan documents:encrypt-existing [--dry-run]
```

## Deletion policy
- Deleting a document permanently removes the stored file and its linked
  reviews immediately; there is no soft-delete or recycle bin.
- Because every file has its own key derived from the master key and a random
  salt stored only in the file, removing the file is the erasure; backups taken
  earlier keep the file until they expire (see backup retention).
- Each deletion is written to the audit log (`document.delete`).
- Financial records derived from a document keep only the document id as a
  historical reference.
- Files with no document row (failed uploads, interrupted deletes) are removed
  with `php artisan documents:purge-orphans --force` (dry run without
  `--force`; files newer than `--min-age-hours`, default 24, are skipped).

## Backup and restore

`scripts\backup.ps1` writes an encrypted snapshot to `backups\<timestamp>\`: the app and Keycloak
Postgres dumps, the document files (already encrypted per file) and a `manifest.json` with row counts
and sha256 checksums. Dumps are piped container to container and encrypted with AES-256
(`openssl enc -pbkdf2`) using `BACKUP_PASSPHRASE` from `.env`; no plaintext dump touches the host.

    powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 [-RetentionDays 30] [-CopyTo D:\offsite]

- Store `BACKUP_PASSPHRASE` and `DOCUMENT_ENCRYPTION_KEY` (or `APP_KEY` if no explicit key is set)
  separately from the backups. Without them a backup cannot be restored or read.
- `-RetentionDays` prunes old snapshots; `-CopyTo` copies the new snapshot to another location.
- Schedule it (e.g. Windows Task Scheduler, daily) and test restores regularly.

Restore (`scripts\restore.ps1 -BackupDir backups\<timestamp>`):

- `-Verify` is non-destructive: restores into scratch databases and a scratch directory, checks the
  manifest counts, runs `php artisan documents:verify`, then cleans up.
- `-Force` replaces live data. It stops the worker, Keycloak and nginx, restores both databases, moves the
  current documents directory aside as `documents.before-restore-<ts>`, then restarts and verifies.

## Accountant access

Owners can register accountants under **Manage accountants** (/accountants). Each accountant is a Keycloak user created by the backend (KeycloakAdmin) with a temporary password that is shown to the owner once and must be changed at first sign-in.

- Accountants have a read-only role: the \cting\ middleware (\ActAsGrantedOwner\) rejects every non-GET request (and owner-only routes such as \/accountants\) with 403.
- Each grant limits the sections (documents, transactions, reviews, assets) and, optionally, the financial years the accountant can see. Financial-year limits are enforced by global model scopes.
- Revoking the last grant disables the user and deletes the Keycloak account.
- Pack and invitation emails contain a link only, never a password. Without SMTP (`MAIL_MAILER=log`) emails are written to the Laravel log, so share the temporary password and link manually.
- Production: set `KEYCLOAK_ADMIN_CLIENT_ID` / `KEYCLOAK_ADMIN_CLIENT_SECRET` (service account with user-management rights) instead of the master admin password variables.
