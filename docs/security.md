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
