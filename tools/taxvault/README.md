# TaxVault Autopilot

## Usage

.\tools\taxvault\taxvault-autopilot.ps1 -Task "OCR-006" -Scope full

## Scopes

backend
frontend
full

## Workflow

1. Inspect repository
2. Inspect current task
3. Implement
4. Run focused tests
5. Diagnose failures
6. Fix
7. Re-run tests
8. Run regression tests
9. Record PASS
10. Update Notion
11. Determine next task

## Safety gates

Manual confirmation remains required for:

- destructive DB operations
- production data changes
- credentials/secrets
- irreversible architecture changes
- external paid APIs
