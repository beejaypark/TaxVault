---
name: deployment-and-development
description: Keep TaxVault local development, staging, and production deployments clearly separated. Use before changing deployment configuration and before every Git commit or push.
---

# TaxVault development and deployment boundaries

## Environments

Keep these environments separate. Share application source code, but do not share environment-specific configuration, credentials, or data.

- **Local development:** use `localhost` and loopback-only host bindings (`127.0.0.1`). Use local Docker Compose services, local development credentials, and local persistent volumes. The root `.env` is private and must remain untracked.
- **Production release:** the release VPS serves `taxvaultapp.com.au` (and explicitly configured production hostnames). It uses production-only environment values, secrets, OAuth callbacks, databases, volumes, encryption keys, backups, and service credentials. Never deploy local-only host bindings or development credentials as production configuration.
- **Staging/development VPS, if used:** give it its own hostname (for example, `dev.taxvaultapp.com.au`), VPS or isolated services, database, volumes, credentials, OAuth callbacks, and backups. Do not point staging at production data.

Use separate deployment configuration for local and production environments. For example, keep local Compose/nginx settings distinct from production Compose/nginx settings; do not make one file silently serve both roles. Make the selected environment explicit in deployment instructions and commands.

Tailscale must not be an application hostname or a substitute for the public production domain. If Tailscale is intentionally used for private administration, keep that access separate from public app routing and document it as an operator-only path.

## Configuration changes

Before editing deployment settings, identify which environment consumes each file. Check `docker-compose.yml`, environment templates, nginx/Keycloak settings, and deployment scripts together so local-only changes cannot break a release VPS.

- Never copy values from `.env` into tracked files, logs, chat, or documentation.
- Keep `.env` ignored by Git; commit only safe templates such as `.env.example`, with placeholders or local defaults and no real secrets.
- Keep app URLs, OAuth redirect URIs, Keycloak client origins, cookies, TLS settings, and published ports consistent within each environment.
- Keep production databases, document storage, encryption keys, OAuth credentials, and backups isolated from local and staging environments.
- When a change affects deployment behavior, update the relevant README/docs and state which environment the change targets.
- Do not delete persistent volumes or reset existing identity data as a shortcut for applying configuration changes.

## Required check before every commit or push

Before running `git commit` or `git push`, read this skill and check the exact changes being committed or pushed:

1. Inspect `git status` and the staged diff; confirm unrelated user work is not included.
2. Confirm `.env`, credentials, tokens, private keys, database dumps, and user documents are not staged.
3. Confirm local-only URLs, loopback port bindings, development credentials, and local Keycloak settings are not being applied to production deployment files.
4. Confirm production-domain and release settings have not been replaced by local or staging values.
5. Check the diff for whitespace errors and run relevant requested checks. Do not claim a check passed unless it was run.
6. If environment ownership is unclear or a change could affect the live release VPS, stop before committing/pushing and ask the user to clarify the target.

This checklist does not itself authorize a commit, push, or deployment. Perform those actions only when the user has requested them.
