[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Task,

    [ValidateSet("backend", "frontend", "full")]
    [string]$Scope = "full",

    [int]$MaxFixAttempts = 3,

    [string]$MaxBudgetUsd = "5"
)

$ErrorActionPreference = "Stop"

$RepoRoot = (Get-Location).Path
$RunId = Get-Date -Format "yyyyMMdd-HHmmss"
$RunRoot = Join-Path $RepoRoot "tools\taxvault\runs\$RunId"

New-Item -ItemType Directory -Force -Path $RunRoot | Out-Null

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " TaxVault Autopilot v4" -ForegroundColor Cyan
Write-Host " Claude Implementation + PowerShell Test Orchestrator" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Run ID          : $RunId" -ForegroundColor Gray
Write-Host "Task            : $Task" -ForegroundColor Yellow
Write-Host "Scope           : $Scope" -ForegroundColor Yellow
Write-Host "Max fix attempts: $MaxFixAttempts" -ForegroundColor Yellow
Write-Host ""

# ============================================================
# Helpers
# ============================================================

function Invoke-TestCommand {
    param(
        [string]$Name,
        [string]$WorkingDirectory,
        [string]$Command,
        [string]$LogFile
    )

    Write-Host ""
    Write-Host "------------------------------------------------------------" -ForegroundColor DarkCyan
    Write-Host " TEST: $Name" -ForegroundColor Cyan
    Write-Host "------------------------------------------------------------" -ForegroundColor DarkCyan

    Push-Location $WorkingDirectory

    # NOTE: the script sets $ErrorActionPreference = "Stop" globally. When a
    # native command's stderr is merged into the pipeline via "2>&1" (as
    # below, so Tee-Object can capture combined output to $LogFile), every
    # line that tool writes to stderr is surfaced as a PowerShell error
    # record - and with $ErrorActionPreference = "Stop", the *first* such
    # line throws a terminating exception immediately, aborting the pipeline
    # before the nested process's real exit code is ever read. Tools like
    # `npx`/`ng test` and `php artisan` routinely write non-fatal notices to
    # stderr (npm/Node warnings, PHP/Composer notices) on a fully passing
    # run, so this was misreporting genuine 100%-green test runs as FAIL.
    # Scoping ErrorActionPreference to "Continue" for just this native call
    # lets that stderr chatter flow into the log as plain text instead of
    # throwing, so $LASTEXITCODE always reflects the real process exit code.
    $previousErrorActionPreference = $ErrorActionPreference
    $ErrorActionPreference = "Continue"

    try {
        # Also forward the nested process's own exit code explicitly, so
        # $LASTEXITCODE here is never left ambiguous by the nested
        # "powershell.exe -Command" host process itself.
        #
        # IMPORTANT: Tee-Object both writes to $LogFile *and* passes every
        # line back out to the pipeline. Because that pipeline is the last
        # statement in this try block and nothing consumes/suppresses its
        # output, PowerShell folds all of those (unassigned) lines into this
        # function's own return value, ahead of the explicit `return
        # $exitCode` below - so callers doing `$exit = Invoke-TestCommand`
        # were actually getting an array of [...every log line..., $exitCode]
        # instead of a bare integer. `($exit -eq 0)` against that array
        # filters for matching elements and returns the (non-empty, so
        # truthy) set of non-matching text lines, so focused runs were
        # reported as failed even when the nested test process printed
        # "0 failed" and exited 0. Routing the tee'd output to `Out-Host`
        # keeps it visible on the console without letting it leak into the
        # function's output stream.
        & powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$Command; exit `$LASTEXITCODE" `
            2>&1 | Tee-Object -FilePath $LogFile | Out-Host

        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousErrorActionPreference
        Pop-Location
    }

    if ($exitCode -eq 0) {
        Write-Host ""
        Write-Host "PASS: $Name" -ForegroundColor Green
    }
    else {
        Write-Host ""
        Write-Host "FAIL: $Name (exit code $exitCode)" -ForegroundColor Red
    }

    return $exitCode
}

function Invoke-Claude {
    param(
        [string]$Prompt,
        [string]$LogFile
    )

    $Prompt |
        Set-Content (Join-Path $RunRoot "claude-current-prompt.txt") -Encoding UTF8

    # Same pipeline-output-leak hazard as Invoke-TestCommand above: without
    # routing the tee'd lines to Out-Host, they'd fold into this function's
    # return value ahead of the explicit `return $LASTEXITCODE`, making
    # `$ClaudeExit`/`$fixExit` at the call sites an array instead of a bare
    # exit code.
    claude `
        -p $Prompt `
        --permission-mode acceptEdits `
        --output-format text `
        --max-budget-usd $MaxBudgetUsd `
        --add-dir $RepoRoot `
        2>&1 |
        Tee-Object -FilePath $LogFile |
        Out-Host

    return $LASTEXITCODE
}

# ============================================================
# Initial repository snapshot
# ============================================================

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-before.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-before.txt") -Encoding UTF8

git branch --show-current |
    Set-Content (Join-Path $RunRoot "git-branch.txt") -Encoding UTF8

@{
    run_id = $RunId
    task = $Task
    scope = $Scope
    started_at = (Get-Date).ToString("o")
    max_fix_attempts = $MaxFixAttempts
} |
ConvertTo-Json -Depth 5 |
Set-Content (Join-Path $RunRoot "run-config.json") -Encoding UTF8

# ============================================================
# Claude implementation phase
# ============================================================

$ImplementationPrompt = @"
You are the TaxVault implementation agent.

Repository:
$RepoRoot

Task:
$Task

Scope:
$Scope

IMPORTANT:
This is Autopilot v4.

The PowerShell orchestrator, NOT Claude, owns test execution.

Your responsibilities:

1. Inspect the repository and current implementation.
2. Inspect relevant tests.
3. Determine the smallest incomplete implementation step for $Task.
4. Implement that step if it is incomplete.
5. Do not redo already verified work.
6. Do not fix unrelated issues.
7. Do not commit or push.
8. Do not use destructive database commands.
9. Do not modify production configuration.
10. Do not expose secrets.
11. Do not create temporary debug code.
12. Remove temporary files/backups only when they are clearly artifacts of the current implementation and safe to remove.

Known OCR-006 state:

Backend:
- GET /api/reviews/{id} already returns active authoritative corrections.
- Review::corrections() is already fixed.
- ReviewApiTest.php currently passes 17 tests / 83 assertions.

Frontend:
- Review model now needs/has corrections mapping.
- Review HTTP service maps persisted corrections.
- Review Detail rehydrates persisted correction state.
- Focused frontend tests were recently verified at 32 tests passing.

The already verified step:
"6-3J-2-E Persisted Active Correction Rehydration / GET Review Contract"
must NOT be reimplemented.

For OCR-006, if the current implementation is already complete:
- do not invent additional feature work;
- inspect the current diff and tests;
- state that implementation is complete;
- prepare for verification.

Write:
tools/taxvault/runs/$RunId/CLAUDE-IMPLEMENTATION.md

Include:
- implementation status
- files changed
- why the change is needed
- tests that should be run by the orchestrator
- remaining concerns

Do NOT claim tests passed unless you actually have execution evidence.
"@

Write-Host "[CLAUDE] Implementation phase..." -ForegroundColor Cyan

$ClaudeImplementationLog = Join-Path $RunRoot "claude-implementation.txt"

$ClaudeExit = Invoke-Claude `
    -Prompt $ImplementationPrompt `
    -LogFile $ClaudeImplementationLog

Write-Host ""
Write-Host "Claude implementation exit code: $ClaudeExit" -ForegroundColor Gray

# ============================================================
# Test definitions
# ============================================================

$TestResults = @()
$FocusedPassed = $true

if ($Scope -eq "frontend" -or $Scope -eq "full") {

    $frontendLog = Join-Path $RunRoot "test-frontend-focused.txt"

    $exit = Invoke-TestCommand `
        -Name "Frontend Review focused tests" `
        -WorkingDirectory (Join-Path $RepoRoot "frontend") `
        -Command "npx ng test --no-watch --no-progress --include src/app/features/reviews/pages/review-detail/review-detail.component.spec.ts --include src/app/shared/services/reviews/review-http.service.spec.ts" `
        -LogFile $frontendLog

    $TestResults += [PSCustomObject]@{
        Name = "Frontend Review focused tests"
        ExitCode = $exit
        Passed = ($exit -eq 0)
    }

    if ($exit -ne 0) {
        $FocusedPassed = $false
    }
}

if ($Scope -eq "backend" -or $Scope -eq "full") {

    $backendLog = Join-Path $RunRoot "test-backend-focused.txt"

    $exit = Invoke-TestCommand `
        -Name "Backend Review API focused tests" `
        -WorkingDirectory (Join-Path $RepoRoot "backend") `
        -Command "php artisan test .\tests\Feature\Api\Reviews\ReviewApiTest.php" `
        -LogFile $backendLog

    $TestResults += [PSCustomObject]@{
        Name = "Backend Review API focused tests"
        ExitCode = $exit
        Passed = ($exit -eq 0)
    }

    if ($exit -ne 0) {
        $FocusedPassed = $false
    }
}

# ============================================================
# Automatic diagnosis/fix loop
# ============================================================

$Attempt = 0

while (-not $FocusedPassed -and $Attempt -lt $MaxFixAttempts) {

    $Attempt++

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Yellow
    Write-Host " Automatic Fix Attempt $Attempt / $MaxFixAttempts" -ForegroundColor Yellow
    Write-Host "============================================================" -ForegroundColor Yellow
    Write-Host ""

    $FailureSummary = ""

    # NOTE: previously this collected and appended the full set of
    # "*focused*" logs once per failing entry in $TestResults, so when more
    # than one test was (mis)reported as failed, the same log contents were
    # duplicated in $FailureSummary once per failure - producing confusing,
    # repeated blocks in the fix prompt. Collect the candidate logs once,
    # regardless of how many results failed.
    $anyFailed = ($TestResults | Where-Object { -not $_.Passed }) | Select-Object -First 1

    if ($anyFailed) {
        $candidateLogs = Get-ChildItem `
            -Path $RunRoot `
            -Filter "*test*" `
            -File |
            Where-Object { $_.Name -like "*focused*" }

        foreach ($log in $candidateLogs) {
            $FailureSummary += "`n===== $($log.Name) =====`n"
            $FailureSummary += Get-Content $log.FullName -Raw
        }
    }

    $FixPrompt = @"
You are the TaxVault repair agent.

Repository:
$RepoRoot

Task:
$Task

Autopilot fix attempt:
$Attempt of $MaxFixAttempts

The PowerShell orchestrator executed the tests and they FAILED.

You must:

1. Read the failure output below.
2. Diagnose the actual failure.
3. Inspect the relevant source and test files.
4. Fix only failures related to the current task.
5. Preserve existing architecture.
6. Do not commit or push.
7. Do not run destructive database commands.
8. Do not modify production configuration.
9. Do not expose secrets.
10. Do not invent a PASS result.

Failure output:
$FailureSummary

After fixing, write:
tools/taxvault/runs/$RunId/CLAUDE-FIX-$Attempt.md

Include:
- root cause
- files changed
- fix applied
- what should be retested

The PowerShell orchestrator will execute the tests again after you finish.
"@

    $fixLog = Join-Path $RunRoot "claude-fix-$Attempt.txt"

    $fixExit = Invoke-Claude `
        -Prompt $FixPrompt `
        -LogFile $fixLog

    Write-Host ""
    Write-Host "Claude fix exit code: $fixExit" -ForegroundColor Gray

    # Re-run focused tests
    $TestResults = @()
    $FocusedPassed = $true

    if ($Scope -eq "frontend" -or $Scope -eq "full") {

        $frontendLog = Join-Path $RunRoot "test-frontend-focused-attempt-$Attempt.txt"

        $exit = Invoke-TestCommand `
            -Name "Frontend Review focused tests (attempt $Attempt)" `
            -WorkingDirectory (Join-Path $RepoRoot "frontend") `
            -Command "npx ng test --no-watch --no-progress --include src/app/features/reviews/pages/review-detail/review-detail.component.spec.ts --include src/app/shared/services/reviews/review-http.service.spec.ts" `
            -LogFile $frontendLog

        $TestResults += [PSCustomObject]@{
            Name = "Frontend Review focused tests"
            ExitCode = $exit
            Passed = ($exit -eq 0)
        }

        if ($exit -ne 0) {
            $FocusedPassed = $false
        }
    }

    if ($Scope -eq "backend" -or $Scope -eq "full") {

        $backendLog = Join-Path $RunRoot "test-backend-focused-attempt-$Attempt.txt"

        $exit = Invoke-TestCommand `
            -Name "Backend Review API focused tests (attempt $Attempt)" `
            -WorkingDirectory (Join-Path $RepoRoot "backend") `
            -Command "php artisan test .\tests\Feature\Api\Reviews\ReviewApiTest.php" `
            -LogFile $backendLog

        $TestResults += [PSCustomObject]@{
            Name = "Backend Review API focused tests"
            ExitCode = $exit
            Passed = ($exit -eq 0)
        }

        if ($exit -ne 0) {
            $FocusedPassed = $false
        }
    }
}

# ============================================================
# Regression phase
# ============================================================

$RegressionPassed = $true

if ($FocusedPassed) {

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Green
    Write-Host " Focused verification PASS" -ForegroundColor Green
    Write-Host " Starting regression verification..." -ForegroundColor Green
    Write-Host "============================================================" -ForegroundColor Green

    if ($Scope -eq "frontend" -or $Scope -eq "full") {

        $frontendRegressionLog = Join-Path $RunRoot "test-frontend-regression.txt"

        $exit = Invoke-TestCommand `
            -Name "Frontend reviews regression" `
            -WorkingDirectory (Join-Path $RepoRoot "frontend") `
            -Command "npx ng test --no-watch --no-progress --include '**/reviews/**/*.spec.ts'" `
            -LogFile $frontendRegressionLog

        if ($exit -ne 0) {
            $RegressionPassed = $false
        }
    }

    if ($Scope -eq "backend" -or $Scope -eq "full") {

        $backendRegressionLog = Join-Path $RunRoot "test-backend-regression.txt"

        $exit = Invoke-TestCommand `
            -Name "Backend Reviews regression" `
            -WorkingDirectory (Join-Path $RepoRoot "backend") `
            -Command "php artisan test .\tests\Feature\Api\Reviews" `
            -LogFile $backendRegressionLog

        if ($exit -ne 0) {
            $RegressionPassed = $false
        }
    }
}

# ============================================================
# Final repository snapshot
# ============================================================

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-after.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-after.txt") -Encoding UTF8

git diff |
    Set-Content (Join-Path $RunRoot "git-diff.txt") -Encoding UTF8

# ============================================================
# Final result
# ============================================================

if ($FocusedPassed -and $RegressionPassed) {
    $FinalStatus = "PASS"
}
elseif ($FocusedPassed) {
    $FinalStatus = "REGRESSION_FAILED"
}
else {
    $FinalStatus = "FAIL"
}

$TestResults |
    ConvertTo-Json -Depth 5 |
    Set-Content (Join-Path $RunRoot "test-results.json") -Encoding UTF8

@{
    run_id = $RunId
    task = $Task
    scope = $Scope
    final_status = $FinalStatus
    focused_passed = $FocusedPassed
    regression_passed = $RegressionPassed
    fix_attempts_used = $Attempt
    completed_at = (Get-Date).ToString("o")
} |
ConvertTo-Json -Depth 5 |
Set-Content (Join-Path $RunRoot "run-result.json") -Encoding UTF8

$FinalPrompt = @"
Create the final handoff report for TaxVault Autopilot v4.

Task:
$Task

Run:
$RunId

The PowerShell orchestrator has completed test execution.

Focused tests passed:
$FocusedPassed

Regression tests passed:
$RegressionPassed

Final status:
$FinalStatus

Fix attempts:
$Attempt

Inspect the run artifacts under:
tools/taxvault/runs/$RunId

Write:
tools/taxvault/runs/$RunId/CLAUDE-RESULT.md

The report must include:
- implementation performed
- files changed
- focused test results
- regression test results
- final status
- remaining unrelated risks
- recommended next task

Do not claim anything not supported by the artifacts.
Do not commit or push.
"@

$finalLog = Join-Path $RunRoot "claude-final-report.txt"

Invoke-Claude `
    -Prompt $FinalPrompt `
    -LogFile $finalLog | Out-Null

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " AUTOPILOT v4 RESULT" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

if ($FinalStatus -eq "PASS") {
    Write-Host "PASS" -ForegroundColor Green
    Write-Host "Task $Task has passed focused + regression verification." -ForegroundColor Green
}
elseif ($FinalStatus -eq "REGRESSION_FAILED") {
    Write-Host "REGRESSION FAILED" -ForegroundColor Red
}
else {
    Write-Host "FAIL" -ForegroundColor Red
}

Write-Host ""
Write-Host "Run artifacts:" -ForegroundColor Yellow
Write-Host "  $RunRoot" -ForegroundColor Gray
Write-Host ""
Write-Host "No git commit or push was performed." -ForegroundColor Yellow
Write-Host ""

if ($FinalStatus -eq "PASS") {
    exit 0
}
else {
    exit 1
}
