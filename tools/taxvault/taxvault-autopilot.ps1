[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Task,

    [ValidateSet("backend", "frontend", "full")]
    [string]$Scope = "full",

    [int]$MaxClaudeCallsPerDay = 0,

    [int]$MaxFixAttempts = 1,

    [string]$MaxBudgetUsd = "1"
)

$ErrorActionPreference = "Stop"

$RepoRoot = (Get-Location).Path
$StateRoot = Join-Path $RepoRoot "tools\taxvault\state"
$RunId = Get-Date -Format "yyyyMMdd-HHmmss"
$RunRoot = Join-Path $RepoRoot "tools\taxvault\runs\$RunId"

New-Item -ItemType Directory -Force -Path $StateRoot | Out-Null
New-Item -ItemType Directory -Force -Path $RunRoot | Out-Null

$UsageFile = Join-Path $StateRoot "claude-usage.json"

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " TaxVault Autopilot v5" -ForegroundColor Cyan
Write-Host " Claude Usage Guard: 10% Allocation" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Run ID              : $RunId" -ForegroundColor Gray
Write-Host "Task                : $Task" -ForegroundColor Yellow
Write-Host "Scope               : $Scope" -ForegroundColor Yellow
Write-Host "Daily Claude calls  : $(if ($MaxClaudeCallsPerDay -gt 0) { "maximum $MaxClaudeCallsPerDay" } else { "unlimited" })" -ForegroundColor Yellow
Write-Host "Maximum fix attempts: $MaxFixAttempts" -ForegroundColor Yellow
Write-Host ""

# ============================================================
# Usage state
# ============================================================

$Today = Get-Date -Format "yyyy-MM-dd"
$Month = Get-Date -Format "yyyy-MM"

if (Test-Path $UsageFile) {
    try {
        $Usage = Get-Content $UsageFile -Raw | ConvertFrom-Json
    }
    catch {
        $Usage = $null
    }
}
else {
    $Usage = $null
}

if ($null -eq $Usage) {
    $Usage = [PSCustomObject]@{
        month = $Month
        month_calls = 0
        days = @{}
    }
}

if ($Usage.month -ne $Month) {
    $Usage = [PSCustomObject]@{
        month = $Month
        month_calls = 0
        days = @{}
    }
}

if ($null -eq $Usage.days) {
    $Usage | Add-Member -MemberType NoteProperty -Name days -Value @{}
}

$TodayCalls = 0

if ($Usage.days.PSObject.Properties.Name -contains $Today) {
    $TodayCalls = [int]$Usage.days.$Today
}

Write-Host "[CLAUDE USAGE GUARD]" -ForegroundColor Magenta
Write-Host "Month       : $Month"
Write-Host "Today       : $Today"
Write-Host "Today calls : $TodayCalls / $MaxClaudeCallsPerDay"
Write-Host "Month calls : $($Usage.month_calls)"
Write-Host ""

if ($MaxClaudeCallsPerDay -gt 0 -and $TodayCalls -ge $MaxClaudeCallsPerDay) {
    Write-Host "CLAUDE DAILY QUOTA REACHED." -ForegroundColor Red
    Write-Host "TaxVault will NOT call Claude today." -ForegroundColor Red
    Write-Host ""
    Write-Host "Tests can still be executed manually." -ForegroundColor Yellow

    exit 2
}

$Usage |
    ConvertTo-Json -Depth 10 |
    Set-Content $UsageFile -Encoding UTF8

# ============================================================
# Helper: Claude call
# ============================================================

function Invoke-ClaudeGuarded {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Prompt,

        [Parameter(Mandatory = $true)]
        [string]$LogFile
    )

    $CurrentUsage = Get-Content $UsageFile -Raw | ConvertFrom-Json

    $CurrentTodayCalls = 0

    if ($CurrentUsage.days.PSObject.Properties.Name -contains $Today) {
        $CurrentTodayCalls = [int]$CurrentUsage.days.$Today
    }

    if ($MaxClaudeCallsPerDay -gt 0 -and $CurrentTodayCalls -ge $MaxClaudeCallsPerDay) {
        Write-Host ""
        Write-Host "CLAUDE QUOTA GUARD: BLOCKED" -ForegroundColor Red
        Write-Host "Today's TaxVault Claude quota is exhausted." -ForegroundColor Red
        return 2
    }

    $NewTodayCalls = $CurrentTodayCalls + 1
    $NewMonthCalls = [int]$CurrentUsage.month_calls + 1

    if ($CurrentUsage.days.PSObject.Properties.Name -contains $Today) {
        $CurrentUsage.days.$Today = $NewTodayCalls
    }
    else {
        $CurrentUsage.days | Add-Member `
            -MemberType NoteProperty `
            -Name $Today `
            -Value $NewTodayCalls
    }

    $CurrentUsage.month_calls = $NewMonthCalls

    $CurrentUsage |
        ConvertTo-Json -Depth 10 |
        Set-Content $UsageFile -Encoding UTF8

    Write-Host ""
    Write-Host "Claude call $NewTodayCalls / $MaxClaudeCallsPerDay for today" -ForegroundColor Cyan

    $Prompt |
        Set-Content (Join-Path $RunRoot "claude-prompt-$NewTodayCalls.txt") `
        -Encoding UTF8

    & claude `
        -p $Prompt `
        --permission-mode acceptEdits `
        --output-format text `
        --max-budget-usd $MaxBudgetUsd `
        --add-dir $RepoRoot `
        *> $LogFile

    $ClaudeExitCode = [int]$LASTEXITCODE

    Get-Content $LogFile | ForEach-Object {
        Write-Host $_
    }

    return $ClaudeExitCode
}

# ============================================================
# Repository snapshot
# ============================================================

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-before.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-before.txt") -Encoding UTF8

git branch --show-current |
    Set-Content (Join-Path $RunRoot "git-branch.txt") -Encoding UTF8

# ============================================================
# Claude implementation
# ============================================================

$ImplementationPrompt = @"
You are the TaxVault implementation agent.

Repository:
$RepoRoot

Task:
$Task

Scope:
$Scope

This is a controlled Autopilot run.

Your job:

1. Inspect the current repository.
2. Inspect relevant implementation and tests.
3. Determine the smallest incomplete step.
4. Implement only that step.
5. Do not redo verified work.
6. Do not modify unrelated features.
7. Do not commit.
8. Do not push.
9. Do not reset the repository.
10. Do not run destructive database commands.
11. Do not expose secrets.
12. Do not invent test results.

IMPORTANT:
The PowerShell orchestrator owns test execution.

Do NOT spend time trying to execute npm, npx, node, php,
or other test commands.

If implementation is already complete:
- do not invent work;
- inspect the current implementation;
- report that it is ready for verification.

Known OCR-006 status:

Backend:
- persisted authoritative correction GET contract is verified.
- ReviewApiTest.php: 17 tests / 83 assertions passing.

Frontend:
- persisted corrections are mapped into the Review model.
- Review Detail rehydrates persisted correction state.
- focused frontend tests: 32/32 passing.

The already verified 6-3J-2-E step must not be reimplemented.

Write:
tools/taxvault/runs/$RunId/CLAUDE-IMPLEMENTATION.md

Include:
- implementation status
- files changed
- reasoning
- expected verification
- remaining concerns
"@

Write-Host "[CLAUDE] Implementation phase" -ForegroundColor Cyan

$ImplementationLog = Join-Path $RunRoot "claude-implementation.txt"

$ClaudeExit = Invoke-ClaudeGuarded `
    -Prompt $ImplementationPrompt `
    -LogFile $ImplementationLog

if ($ClaudeExit -eq 2) {
    Write-Host "Claude execution blocked by daily quota." -ForegroundColor Red
    exit 2
}

# ============================================================
# Focused tests
# ============================================================

$FocusedPassed = $true

if ($Scope -eq "frontend" -or $Scope -eq "full") {

    $FrontendLog = Join-Path $RunRoot "test-frontend-focused.txt"

    Push-Location (Join-Path $RepoRoot "frontend")

    try {
        & npx ng test --no-watch --no-progress `
            --include src/app/features/reviews/pages/review-detail/review-detail.component.spec.ts `
            --include src/app/shared/services/reviews/review-http.service.spec.ts `
            *> $FrontendLog

        $FrontendExit = [int]$LASTEXITCODE

        Get-Content $FrontendLog | ForEach-Object {
            Write-Host $_
        }
    }
    finally {
        Pop-Location
    }

    if ($FrontendExit -ne 0) {
        $FocusedPassed = $false
    }
}

if ($Scope -eq "backend" -or $Scope -eq "full") {

    $BackendLog = Join-Path $RunRoot "test-backend-focused.txt"

    Push-Location (Join-Path $RepoRoot "backend")

    try {
        & php artisan test .\tests\Feature\Api\Reviews\ReviewApiTest.php `
            *> $BackendLog

        $BackendExit = [int]$LASTEXITCODE

        Get-Content $BackendLog | ForEach-Object {
            Write-Host $_
        }
    }
    finally {
        Pop-Location
    }

    if ($BackendExit -ne 0) {
        $FocusedPassed = $false
    }
}

# ============================================================
# One controlled fix attempt
# ============================================================

$FixUsed = $false

if (-not $FocusedPassed -and $MaxFixAttempts -gt 0) {

    $FixUsed = $true

    $FailureOutput = ""

    Get-ChildItem $RunRoot -Filter "test-*.txt" -File |
        ForEach-Object {
            $FailureOutput += "`n===== $($_.Name) =====`n"
            $FailureOutput += Get-Content $_.FullName -Raw
        }

    $FixPrompt = @"
You are the TaxVault repair agent.

Task:
$Task

The PowerShell test orchestrator executed the focused tests.
They failed.

You have ONE repair attempt.

Read the failure output below.

Diagnose and fix only the failure caused by the current task.

Do not:
- commit
- push
- reset
- delete unrelated source
- run destructive DB commands
- expose secrets
- fix unrelated pre-existing issues

After fixing, write:
tools/taxvault/runs/$RunId/CLAUDE-FIX.md

Include:
- root cause
- files changed
- fix applied

The PowerShell orchestrator will execute the tests again.

Failure output:
$FailureOutput
"@

    $FixLog = Join-Path $RunRoot "claude-fix.txt"

    $FixExit = Invoke-ClaudeGuarded `
        -Prompt $FixPrompt `
        -LogFile $FixLog

    if ($FixExit -eq 2) {
        Write-Host ""
        Write-Host "No Claude quota remaining for automatic fix." -ForegroundColor Red
    }
    else {

        $FocusedPassed = $true

        if ($Scope -eq "frontend" -or $Scope -eq "full") {

            Push-Location (Join-Path $RepoRoot "frontend")

            try {
                $FrontendAfterFixLog = Join-Path $RunRoot "test-frontend-focused-after-fix.txt"

                & npx ng test --no-watch --no-progress `
                    --include src/app/features/reviews/pages/review-detail/review-detail.component.spec.ts `
                    --include src/app/shared/services/reviews/review-http.service.spec.ts `
                    *> $FrontendAfterFixLog

                $FrontendExit = [int]$LASTEXITCODE

                Get-Content $FrontendAfterFixLog | ForEach-Object {
                    Write-Host $_
                }
            }
            finally {
                Pop-Location
            }

            if ($FrontendExit -ne 0) {
                $FocusedPassed = $false
            }
        }

        if ($Scope -eq "backend" -or $Scope -eq "full") {

            Push-Location (Join-Path $RepoRoot "backend")

            try {
                $BackendAfterFixLog = Join-Path $RunRoot "test-backend-focused-after-fix.txt"

                & php artisan test .\tests\Feature\Api\Reviews\ReviewApiTest.php `
                    *> $BackendAfterFixLog

                $BackendExit = [int]$LASTEXITCODE

                Get-Content $BackendAfterFixLog | ForEach-Object {
                    Write-Host $_
                }
            }
            finally {
                Pop-Location
            }

            if ($BackendExit -ne 0) {
                $FocusedPassed = $false
            }
        }
    }
}

# ============================================================
# Regression
# ============================================================

$RegressionPassed = $true

if ($FocusedPassed) {

    Write-Host ""
    Write-Host "Focused verification PASS." -ForegroundColor Green
    Write-Host "Running Reviews regression..." -ForegroundColor Green

    if ($Scope -eq "frontend" -or $Scope -eq "full") {

        Push-Location (Join-Path $RepoRoot "frontend")

        try {
            $RegressionFrontendLog = Join-Path $RunRoot "test-frontend-regression.txt"

            & npx ng test --no-watch --no-progress `
                --include '**/reviews/**/*.spec.ts' `
                *> $RegressionFrontendLog

            $RegressionFrontendExit = [int]$LASTEXITCODE

            Get-Content $RegressionFrontendLog | ForEach-Object {
                Write-Host $_
            }
        }
        finally {
            Pop-Location
        }

        if ($RegressionFrontendExit -ne 0) {
            $RegressionPassed = $false
        }
    }

    if ($Scope -eq "backend" -or $Scope -eq "full") {

        Push-Location (Join-Path $RepoRoot "backend")

        try {
            $RegressionBackendLog = Join-Path $RunRoot "test-backend-regression.txt"

            & php artisan test .\tests\Feature\Api\Reviews `
                *> $RegressionBackendLog

            $RegressionBackendExit = [int]$LASTEXITCODE

            Get-Content $RegressionBackendLog | ForEach-Object {
                Write-Host $_
            }
        }
        finally {
            Pop-Location
        }

        if ($RegressionBackendExit -ne 0) {
            $RegressionPassed = $false
        }
    }
}

# ============================================================
# Final snapshot
# ============================================================

git status --short |
    Set-Content (Join-Path $RunRoot "git-status-after.txt") -Encoding UTF8

git diff --stat |
    Set-Content (Join-Path $RunRoot "git-diff-after.txt") -Encoding UTF8

git diff |
    Set-Content (Join-Path $RunRoot "git-diff.txt") -Encoding UTF8

$FinalStatus = if ($FocusedPassed -and $RegressionPassed) {
    "PASS"
}
elseif ($FocusedPassed) {
    "REGRESSION_FAILED"
}
else {
    "FAIL"
}

$CurrentUsage = Get-Content $UsageFile -Raw | ConvertFrom-Json

@{
    run_id = $RunId
    task = $Task
    scope = $Scope
    final_status = $FinalStatus
    focused_passed = $FocusedPassed
    regression_passed = $RegressionPassed
    claude_calls_today = $CurrentUsage.days.$Today
    claude_calls_month = $CurrentUsage.month_calls
    completed_at = (Get-Date).ToString("o")
} |
ConvertTo-Json -Depth 10 |
Set-Content (Join-Path $RunRoot "run-result.json") -Encoding UTF8

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " AUTOPILOT v5 RESULT" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

if ($FinalStatus -eq "PASS") {
    Write-Host "PASS" -ForegroundColor Green
}
else {
    Write-Host $FinalStatus -ForegroundColor Red
}

Write-Host ""
Write-Host "Claude calls today : $($CurrentUsage.days.$Today) / $MaxClaudeCallsPerDay" -ForegroundColor Yellow
Write-Host "Claude calls month : $($CurrentUsage.month_calls)" -ForegroundColor Yellow
Write-Host ""
Write-Host "Run artifacts:" -ForegroundColor Gray
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



