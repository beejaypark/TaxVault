# Shared helpers for backup.ps1 / restore.ps1 (dot-source this file).
# Binary data is streamed container-to-container through cmd.exe pipes so that
# plaintext database dumps never touch the host disk and PowerShell never
# re-encodes the bytes.

$script:RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

function Get-ConfigValue {
    param([string]$Name, [string]$Default = '')

    $fromEnv = [Environment]::GetEnvironmentVariable($Name)
    if ($fromEnv) { return $fromEnv }

    $envFile = Join-Path $script:RepoRoot '.env'
    if (Test-Path $envFile) {
        $line = Get-Content $envFile | Where-Object { $_ -match "^$Name=" } | Select-Object -First 1
        if ($line) { return ($line -replace "^$Name=", '').Trim() }
    }

    return $Default
}

function Initialize-Backup {
    Set-Location $script:RepoRoot

    $passphrase = Get-ConfigValue 'BACKUP_PASSPHRASE'
    if ($passphrase.Length -lt 16) {
        throw 'Set BACKUP_PASSPHRASE (at least 16 characters) in .env or the environment.'
    }
    $env:BACKUP_PASSPHRASE = $passphrase

    $script:PgUser = Get-ConfigValue 'POSTGRES_USER' 'taxvault'
    $script:AppDb = Get-ConfigValue 'POSTGRES_DB' 'taxvault'
    $script:KeycloakDb = Get-ConfigValue 'KEYCLOAK_DB' 'keycloak'

    $script:Backend = 'docker compose exec -T -e BACKUP_PASSPHRASE backend'
    $script:Postgres = "docker compose exec -T postgres"
    $script:Enc = 'openssl enc -aes-256-cbc -pbkdf2 -iter 600000 -salt -pass env:BACKUP_PASSPHRASE'
    $script:Dec = 'openssl enc -d -aes-256-cbc -pbkdf2 -iter 600000 -pass env:BACKUP_PASSPHRASE'
    $script:DocsDir = 'storage/app/private/documents'

    foreach ($service in 'postgres', 'backend') {
        $running = docker compose ps --status running -q $service
        if (-not $running) { throw "Service '$service' is not running. Start it with: docker compose up -d" }
    }
}

# $Line must not contain double quotes (cmd.exe quoting through PowerShell 5.1).
function Invoke-Cmd {
    param([string]$Line)

    & cmd.exe /c $Line
    if ($LASTEXITCODE -ne 0) { throw "Command failed (exit $LASTEXITCODE): $Line" }
}

function Get-Sql {
    param([string]$Database, [string]$Query)

    $out = & docker compose exec -T postgres psql -U $script:PgUser -d $Database -tAc $Query
    if ($LASTEXITCODE -ne 0) { throw "SQL failed on ${Database}: $Query" }

    return ([string]$out).Trim()
}
