[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Task,

    [ValidateSet("backend", "frontend", "full")]
    [string]$Scope = "full",

    [string]$MaxBudgetUsd = "5"
)

$ErrorActionPreference = "Stop"

$RepoRoot = (Get-Location).Path
$RunId = Get-Date -Format "yyyyMMdd-HHmmss"
$RunRoot = Join-Path $RepoRoot "tools\taxvault\runs\$RunId"

New-Item -ItemType Directory -Force -Path $RunRoot | Out-Null

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " TaxVault Autopilot v3" -ForegroundColor Cyan
Write-Host " Claude Code Execution Engine" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Run ID : $RunId" -ForegroundColor Gray
Write-Host "Task   : $Task" -ForegroundColor Yellow
Write-Host "Scope  : $Scope" -ForegroundColor Yellow
Write-Host ""

# ------------------------------------------------------------
# Environment
# ------------------------------------------------------------

$gitBranch = (git branch --show-current 2>$null).Trim()

$phpVersion = (& php --version 2>$null | Select-Object -First 1)
$nodeVersion = (& node --version 2>$null)
$npmVersion = (& npm --version 2>$null)

@{
    run_id = $RunId
    task = $Task
    scope = $Scope
    branch = $gitBranch
    php = $phpVersion
    node = $nodeVersion
    npm = $npmVersion
    started_at = (Get-Date).ToString("o")
} |
ConvertTo-Json -Depth 5 |
Set-Content (Join-Path $RunRoot "environment.json") -Encoding UTF8

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-before.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-before.txt") -Encoding UTF8

# ------------------------------------------------------------
# Safety
# ------------------------------------------------------------

Write-Host "[SAFETY]" -ForegroundColor Magenta
Write-Host "Claude permission mode: acceptEdits"
Write-Host "Production/destructive DB operations are prohibited."
Write-Host ""

# ------------------------------------------------------------
# Claude prompt
# ------------------------------------------------------------

$Prompt = @"
You are the implementation agent for the TaxVault repository.

Repository:
$RepoRoot

Task:
$Task

Scope:
$Scope

You are operating as part of TaxVault Autopilot v3.

Your job is to execute the following workflow completely:

1. INSPECT
   - Inspect the current repository state.
   - Inspect the relevant implementation.
   - Inspect relevant tests.
   - Inspect recent task documentation if available locally.
   - Do not redo work that is already implemented and verified.

2. IMPLEMENT
   - Implement the smallest next incomplete step for task $Task.
   - Follow the existing architecture and naming conventions.
   - Prefer minimal, focused changes.
   - Do not refactor unrelated code.
   - Do not modify unrelated features.

3. FOCUSED TEST
   - Identify and run the smallest relevant backend/frontend test suite.
   - Use the repository's existing test commands.
   - If a test fails, diagnose the actual cause.

4. FIX
   - Fix failures caused by your implementation.
   - Re-run the focused tests.
   - Repeat until the focused tests pass.

5. REGRESSION
   - Run an appropriate regression suite for the affected area.
   - Do not run the entire repository test suite unnecessarily if a focused regression suite is sufficient.

6. VERIFY
   - Confirm the implementation and tests are actually passing.
   - Inspect git diff.
   - Ensure no temporary diagnostics, debug code, backups, or unrelated changes were introduced.

7. HANDOFF
   - Create or update:
       tools/taxvault/runs/$RunId/CLAUDE-RESULT.md
   - Include:
       * task
       * implementation performed
       * files changed
       * focused tests
       * regression tests
       * final PASS/FAIL status
       * remaining risks
       * recommended next task

IMPORTANT SAFETY RULES:

- NEVER use git reset --hard.
- NEVER delete user source code.
- NEVER drop databases or tables.
- NEVER run destructive database commands.
- NEVER modify production configuration.
- NEVER expose or print secrets, passwords, API keys, or tokens.
- NEVER commit changes.
- NEVER push changes.
- Do not modify files outside the repository.
- Do not fix unrelated pre-existing issues unless they block the requested task.
- If an issue is outside the scope of $Task, document it as a follow-up instead.

For this run, do NOT consider the already verified OCR-006 step
"6-3J-2-E Persisted Active Correction Rehydration / GET Review Contract"
as incomplete work.

The current known OCR-006 direction is:

Frontend HTTP DTO/model mapping and Review Detail rehydration for persisted
authoritative corrections.

Known relevant files include:

frontend:
- src/app/shared/models/reviews/review.model.ts
- src/app/shared/services/reviews/review-http.service.ts
- src/app/features/reviews/pages/review-detail/review-detail.component.ts
- src/app/features/reviews/pages/review-detail/review-detail.component.html
- src/app/features/reviews/pages/review-detail/review-detail.component.spec.ts
- src/app/shared/services/reviews/review-http.service.spec.ts

backend:
- app/Http/Controllers/Api/ReviewController.php
- app/Domain/Evidence/Models/TaxEvidence.php
- app/Domain/Reviews/Models/Review.php
- tests/Feature/Api/Reviews/ReviewApiTest.php

Known verified backend baseline:
ReviewApiTest.php
17 tests / 83 assertions / 0 failures.

Known verified frontend baseline:
review-detail.component.spec.ts
23 tests / 0 failures.

A known unrelated follow-up issue is that the backend review index currently returns
{data:[...]} while the frontend pagination model expects pagination metadata.
Do NOT fix that unless it is directly required by the current task.

If the task is already fully implemented and verified:
- do not invent work;
- run the relevant verification;
- document that the task is already complete;
- identify the next smallest incomplete task.

At the end, clearly report:
PASS or FAIL.
"@

$Prompt |
    Set-Content (Join-Path $RunRoot "CLAUDE-PROMPT.txt") -Encoding UTF8

Write-Host "[CLAUDE] Starting execution..." -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------
# Execute Claude Code
# ------------------------------------------------------------

$ClaudeLog = Join-Path $RunRoot "claude-output.txt"

claude `
    -p $Prompt `
    --permission-mode acceptEdits `
    --output-format text `
    --max-budget-usd $MaxBudgetUsd `
    --add-dir $RepoRoot 2>&1 |
    Tee-Object -FilePath $ClaudeLog

$ClaudeExitCode = $LASTEXITCODE

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " Claude exit code: $ClaudeExitCode" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# ------------------------------------------------------------
# Post-run repository state
# ------------------------------------------------------------

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-after.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-after.txt") -Encoding UTF8

git diff |
    Set-Content (Join-Path $RunRoot "git-diff.txt") -Encoding UTF8

$Result = if ($ClaudeExitCode -eq 0) { "CLAUDE_COMPLETED" } else { "CLAUDE_FAILED" }

@{
    run_id = $RunId
    task = $Task
    scope = $Scope
    result = $Result
    claude_exit_code = $ClaudeExitCode
    completed_at = (Get-Date).ToString("o")
} |
ConvertTo-Json -Depth 5 |
Set-Content (Join-Path $RunRoot "run-result.json") -Encoding UTF8

Write-Host ""
Write-Host "Run artifacts:" -ForegroundColor Yellow
Write-Host "  $RunRoot" -ForegroundColor Gray
Write-Host ""

if ($ClaudeExitCode -eq 0) {
    Write-Host "AUTOPILOT V3: CLAUDE COMPLETED" -ForegroundColor Green
}
else {
    Write-Host "AUTOPILOT V3: CLAUDE FAILED - inspect claude-output.txt" -ForegroundColor Red
}

Write-Host ""
Write-Host "IMPORTANT: No git commit or push was performed." -ForegroundColor Yellow
Write-Host ""

exit $ClaudeExitCode
