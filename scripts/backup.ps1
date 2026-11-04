<#
.SYNOPSIS
  Creates an encrypted TaxVault backup (PostgreSQL app + Keycloak databases and
  the document storage) under backups/<timestamp>/.
.PARAMETER RetentionDays
  Backups older than this many days are deleted. 0 disables pruning.
.PARAMETER CopyTo
  Optional extra destination (e.g. an external drive or synced folder).
.NOTES
  Requires BACKUP_PASSPHRASE. The document master key (DOCUMENT_ENCRYPTION_KEY,
  or APP_KEY when unset) is NOT included: keep it separately or restored
  documents cannot be decrypted.
#>
[CmdletBinding()]
param(
    [int]$RetentionDays = 30,
    [string]$CopyTo
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'backup-common.ps1')
Initialize-Backup

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$relDir = "backups\$stamp"
New-Item -ItemType Directory -Force $relDir | Out-Null

$files = [ordered]@{
    app      = "$relDir\app.dump.enc"
    keycloak = "$relDir\keycloak.dump.enc"
    docs     = "$relDir\documents.tar.enc"
}

Write-Host "Backing up to $relDir"

foreach ($pair in @(@('app', $script:AppDb), @('keycloak', $script:KeycloakDb))) {
    $name = $pair[0]; $db = $pair[1]; $file = $files[$name]

    Invoke-Cmd "$script:Postgres pg_dump -U $script:PgUser -Fc $db | $script:Backend $script:Enc > $file"
    # An empty or truncated dump still encrypts fine, so prove it can be read back.
    Invoke-Cmd "$script:Backend $script:Dec < $file | $script:Postgres pg_restore -l > NUL"
    Write-Host ("  {0,-9} {1:N0} bytes, verified" -f $name, (Get-Item $file).Length)
}

Invoke-Cmd "docker compose exec -T backend tar -C storage/app/private -cf - documents | $script:Backend $script:Enc > $($files['docs'])"
$list = "$relDir\documents.list"
Invoke-Cmd "$script:Backend $script:Dec < $($files['docs']) | docker compose exec -T backend tar -tf - > $list"
$docFiles = @(Get-Content $list | Where-Object { $_ -and -not $_.EndsWith('/') }).Count
Remove-Item $list
Write-Host ("  {0,-9} {1:N0} bytes, {2} files, verified" -f 'documents', (Get-Item $files['docs']).Length, $docFiles)

$manifest = [ordered]@{
    created_at = (Get-Date).ToString('o')
    format     = 1
    counts     = [ordered]@{
        documents       = [int](Get-Sql $script:AppDb 'select count(*) from documents')
        audit_events    = [int](Get-Sql $script:AppDb 'select count(*) from audit_events')
        document_files  = $docFiles
        keycloak_users  = [int](Get-Sql $script:KeycloakDb 'select count(*) from user_entity')
    }
    files      = [ordered]@{}
}
foreach ($entry in $files.GetEnumerator()) {
    $item = Get-Item $entry.Value
    $manifest.files[$item.Name] = [ordered]@{
        sha256 = (Get-FileHash $item.FullName -Algorithm SHA256).Hash.ToLower()
        bytes  = $item.Length
    }
}
[IO.File]::WriteAllText(
    (Join-Path (Get-Location) "$relDir\manifest.json"),
    ($manifest | ConvertTo-Json -Depth 5),
    (New-Object Text.UTF8Encoding $false)
)

if ($RetentionDays -gt 0) {
    $cutoff = (Get-Date).AddDays(-$RetentionDays)
    Get-ChildItem backups -Directory |
        Where-Object { $_.Name -match '^\d{8}-\d{6}$' -and $_.LastWriteTime -lt $cutoff } |
        ForEach-Object { Remove-Item $_.FullName -Recurse -Force; Write-Host "  pruned $($_.Name)" }
}

if ($CopyTo) {
    New-Item -ItemType Directory -Force $CopyTo | Out-Null
    Copy-Item $relDir -Destination $CopyTo -Recurse -Force
    Write-Host "  copied to $CopyTo"
}

Write-Host "Done: $relDir"
Write-Host 'Keep BACKUP_PASSPHRASE and the document key (DOCUMENT_ENCRYPTION_KEY / APP_KEY) somewhere other than this backup.'
