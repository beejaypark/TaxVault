<#
.SYNOPSIS
  Verifies or restores a backup made by backup.ps1.
.PARAMETER BackupDir
  Backup folder, e.g. backups\20261104-193000.
.PARAMETER Verify
  Non-destructive: checks checksums, restores both databases into scratch
  databases and the documents into /tmp, compares with the manifest and checks
  that every document decrypts with the current key. Live data is untouched.
.PARAMETER Force
  Required to REPLACE the live databases and document storage.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$BackupDir,
    [switch]$Verify,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
if (-not $Verify -and -not $Force) {
    throw 'Choose -Verify (safe check) or -Force (replace live data).'
}

. (Join-Path $PSScriptRoot 'backup-common.ps1')
Initialize-Backup

$dir = (Resolve-Path $BackupDir).Path
if (-not $dir.StartsWith($script:RepoRoot, [StringComparison]::OrdinalIgnoreCase)) {
    throw "BackupDir must be inside the repository ($script:RepoRoot); copy external backups into backups\ first."
}
$rel = $dir.Substring($script:RepoRoot.Length).TrimStart('\')
$manifest = Get-Content (Join-Path $dir 'manifest.json') -Raw | ConvertFrom-Json

foreach ($prop in $manifest.files.PSObject.Properties) {
    $actual = (Get-FileHash (Join-Path $dir $prop.Name) -Algorithm SHA256).Hash.ToLower()
    if ($actual -ne $prop.Value.sha256) { throw "Checksum mismatch: $($prop.Name)" }
}
Write-Host "Checksums OK ($($manifest.created_at))"

$app = "$rel\app.dump.enc"
$kc = "$rel\keycloak.dump.enc"
$docs = "$rel\documents.tar.enc"

function Restore-Database {
    param([string]$File, [string]$Database, [string]$Flags)

    Invoke-Cmd "$script:Backend $script:Dec < $File | $script:Postgres pg_restore -U $script:PgUser -d $Database --no-owner $Flags"
}

function Assert-Equal {
    param([string]$What, $Expected, $Actual)

    if ([string]$Expected -ne [string]$Actual) { throw "$What mismatch: expected $Expected, got $Actual" }
    Write-Host ("  {0,-16} {1}" -f $What, $Actual)
}

if ($Verify) {
    $scratchApp = "$($script:AppDb)_restore_check"
    $scratchKc = "$($script:KeycloakDb)_restore_check"
    $scratchDocs = '/tmp/restore-check'

    try {
        foreach ($db in $scratchApp, $scratchKc) {
            docker compose exec -T postgres dropdb -U $script:PgUser --if-exists $db
            docker compose exec -T postgres createdb -U $script:PgUser $db
            if ($LASTEXITCODE -ne 0) { throw "Cannot create scratch database $db" }
        }
        Restore-Database $app $scratchApp ''
        Restore-Database $kc $scratchKc ''

        docker compose exec -T backend sh -c "rm -rf $scratchDocs && mkdir -p $scratchDocs"
        Invoke-Cmd "$script:Backend $script:Dec < $docs | docker compose exec -T backend tar -C $scratchDocs -xf -"

        Write-Host 'Restored into scratch locations:'
        Assert-Equal 'documents' $manifest.counts.documents (Get-Sql $scratchApp 'select count(*) from documents')
        Assert-Equal 'audit_events' $manifest.counts.audit_events (Get-Sql $scratchApp 'select count(*) from audit_events')
        Assert-Equal 'keycloak_users' $manifest.counts.keycloak_users (Get-Sql $scratchKc 'select count(*) from user_entity')
        $fileCount = (docker compose exec -T backend sh -c "find $scratchDocs -type f | wc -l").Trim()
        Assert-Equal 'document_files' $manifest.counts.document_files $fileCount

        docker compose exec -T backend php artisan documents:verify --root=$scratchDocs/documents
        if ($LASTEXITCODE -ne 0) { throw 'Some document files do not decrypt with the current key.' }
        Write-Host 'Backup verified.'
    }
    finally {
        foreach ($db in $scratchApp, $scratchKc) {
            docker compose exec -T postgres dropdb -U $script:PgUser --if-exists $db | Out-Null
        }
        docker compose exec -T backend rm -rf $scratchDocs | Out-Null
    }

    return
}

Write-Host 'Stopping worker, keycloak and nginx...'
docker compose stop document-worker keycloak nginx | Out-Null

Restore-Database $app $script:AppDb '--clean --if-exists --single-transaction'
Restore-Database $kc $script:KeycloakDb '--clean --if-exists --single-transaction'

# Keep the current documents until the new ones are fully extracted.
$aside = "$($script:DocsDir).before-restore-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
docker compose exec -T backend sh -c "if [ -d $($script:DocsDir) ]; then mv $($script:DocsDir) $aside; fi"
Invoke-Cmd "$script:Backend $script:Dec < $docs | docker compose exec -T backend tar -C storage/app/private -xf -"

docker compose up -d | Out-Null
docker compose exec -T backend php artisan documents:verify
if ($LASTEXITCODE -ne 0) { Write-Warning 'Some restored documents do not decrypt: check DOCUMENT_ENCRYPTION_KEY / APP_KEY.' }

Write-Host "Restore complete. Previous documents were kept in the backend container at $aside; delete them once you are satisfied."
