# Production VPS deployment

Use these steps on the production VPS after pushing application changes to `origin/main`. Run them from the VPS checkout of TaxVault, where `.env` contains that VPS's private production settings.

Replace `/path/to/TaxVault` with the actual repository path on the VPS. Keep the production `.env` in place; do not copy the local development `.env.example` over it.

## Update backend and frontend

For changes that affect both the API and the web app, run:

```bash
cd /path/to/TaxVault
git pull --ff-only origin main
docker compose up -d --build --force-recreate backend frontend
docker compose restart document-worker
docker compose restart scheduler
docker compose ps
docker compose logs --tail=100 backend document-worker scheduler
```

The backend entrypoint runs `php artisan migrate --force` during startup. Wait for the backend to start before restarting `document-worker` and `scheduler`, so the migration completes first. Restarting those services loads the updated backend code used by queue jobs and scheduled tasks.

## Deploy only the affected services

- Frontend-only changes: after `git pull`, run `docker compose up -d --build --force-recreate frontend`.
- Backend changes: after `git pull`, run these commands so the backend migration finishes before workers restart:

  ```bash
  docker compose up -d --build --force-recreate backend
  docker compose restart document-worker
  docker compose restart scheduler
  ```

- Documentation-only changes: no VPS update is needed.

Check service health with `docker compose ps` and inspect relevant logs with `docker compose logs --tail=100 <service>`.

Do not run `docker compose down -v`; it removes persisted database and application data. Do not deploy to the VPS unless deployment has been explicitly requested.
