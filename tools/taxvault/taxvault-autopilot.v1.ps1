param(
    [Parameter(Mandatory=$true)]
    [string]$Task,

    [ValidateSet("backend","frontend","full")]
    [string]$Scope = "full",

    [switch]$NoNotion
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Root = Split-Path -Parent $Root

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " TaxVault Autopilot" -ForegroundColor Cyan
Write-Host " Task : $Task" -ForegroundColor Cyan
Write-Host " Scope: $Scope" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $Root

Write-Host "[1/7] Repository status" -ForegroundColor Yellow
git status --short

Write-Host ""
Write-Host "[2/7] Current branch" -ForegroundColor Yellow
git branch --show-current

Write-Host ""
Write-Host "[3/7] Backend environment" -ForegroundColor Yellow
if (Test-Path ".\backend") {
    Push-Location ".\backend"

    php --version | Select-Object -First 1
    php artisan --version

    Pop-Location
}

Write-Host ""
Write-Host "[4/7] Frontend environment" -ForegroundColor Yellow
if (Test-Path ".\frontend") {
    Push-Location ".\frontend"

    node --version
    npm --version

    Pop-Location
}

Write-Host ""
Write-Host "[5/7] Task" -ForegroundColor Green
Write-Host $Task

Write-Host ""
Write-Host "[6/7] Safety gate" -ForegroundColor Yellow
Write-Host ""
Write-Host "Autopilot will NOT automatically:"
Write-Host "  - run destructive database operations"
Write-Host "  - delete production data"
Write-Host "  - modify credentials/secrets"
Write-Host "  - perform irreversible architecture changes"
Write-Host ""

Write-Host "[7/7] Ready" -ForegroundColor Green
Write-Host ""
Write-Host "The repository is ready for the implementation agent."
Write-Host ""

Write-Host "Next:"
Write-Host "  Task = $Task"
Write-Host "  Scope = $Scope"
Write-Host ""

if (-not $NoNotion) {
    Write-Host "Notion update will be performed after PASS." -ForegroundColor DarkCyan
}

Write-Host ""
Write-Host "Autopilot bootstrap completed." -ForegroundColor Green
