<#
.SYNOPSIS
    Runs a supplier feed sync against the local PrestaShop container.

.DESCRIPTION
    Wrapper for Windows Task Scheduler. It makes sure the stack is actually up
    before running, so a scheduled sync on a rebooted machine starts Docker
    rather than failing silently, and it exits with the importer's own status
    so Task Scheduler shows a real result.

.PARAMETER Config
    Job config, relative to tools/feed-import (e.g. config/supplier.json).

.PARAMETER DryRun
    Report changes without writing.

.EXAMPLE
    .\run-sync.ps1 -Config config/supplier.json
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Config,
    [switch]$DryRun,
    [string]$ProjectDir = (Resolve-Path "$PSScriptRoot\..\..\.."),
    [string]$Container  = 'spz-shop',
    [int]$StartupTimeoutSeconds = 300
)

$ErrorActionPreference = 'Stop'
$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'

function Write-Log($msg) {
    Write-Output ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg)
}

if (-not (Test-Path $docker)) {
    Write-Log "FATAL docker CLI not found at $docker"
    exit 2
}

# Bring the engine up if the machine rebooted since the last run.
& $docker info --format '{{.ServerVersion}}' *> $null
if ($LASTEXITCODE -ne 0) {
    Write-Log 'Docker engine down; starting Docker Desktop'
    Start-Service 'com.docker.service' -ErrorAction SilentlyContinue
    Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'

    $deadline = (Get-Date).AddSeconds($StartupTimeoutSeconds)
    do {
        Start-Sleep -Seconds 6
        & $docker info --format '{{.ServerVersion}}' *> $null
    } while ($LASTEXITCODE -ne 0 -and (Get-Date) -lt $deadline)

    if ($LASTEXITCODE -ne 0) {
        Write-Log "FATAL Docker did not start within $StartupTimeoutSeconds s"
        exit 2
    }
}

# The importer needs the database, not just the web container.
$running = & $docker ps --filter "name=$Container" --filter 'status=running' --format '{{.Names}}'
if ($running -notcontains $Container) {
    Write-Log "Container $Container not running; starting the stack"
    Push-Location $ProjectDir
    try {
        & $docker compose up -d 2>&1 | Out-Null
    } finally {
        Pop-Location
    }
    Start-Sleep -Seconds 20
}

$importArgs = @(
    'exec', '-w', '/var/www/html/themes/PRS935/tools/feed-import', $Container,
    'php', 'import.php', "--config=$Config", '--quiet'
)
if ($DryRun) { $importArgs += '--dry-run' }

Write-Log "Running: import.php --config=$Config$(if ($DryRun) { ' --dry-run' })"
& $docker @importArgs
$code = $LASTEXITCODE

switch ($code) {
    0 { Write-Log 'Sync completed cleanly' }
    1 { Write-Log 'Sync completed WITH ROW FAILURES - check var/log' }
    default { Write-Log "Sync FAILED (exit $code) - check var/log" }
}

exit $code
