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

<#
    Clear an orphaned AF_UNIX socket file.

    Docker Desktop creates these under AppData and deletes them on a clean
    exit. After an unclean one - a crash, a kill, or the out-of-disk event
    that remounted its filesystem read-only - they survive as entries Windows
    reports as "The file cannot be accessed by the system": undeletable, and
    unusable. Docker then refuses to start at all, and the only options its
    own dialog offers are Quit and "Reset to factory defaults", the latter
    destroying every container, image and volume.

    Renaming the parent directory does work, so that is what this does. The
    sockets are recreated on the next start. Both directories must be cleared
    in the same pass: fixing one just moves the failure to the next service.
#>
function Clear-StaleDockerSocketDir([string]$dir) {
    if (-not (Test-Path -LiteralPath $dir)) { return }

    $leaf = Split-Path $dir -Leaf
    $stale = "$leaf.stale-" + (Get-Date -Format 'yyyyMMddHHmmss')
    try {
        Rename-Item -LiteralPath $dir -NewName $stale -Force -ErrorAction Stop
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Log "Cleared stale Docker socket dir: $dir"
    } catch {
        Write-Log "WARN could not clear $dir : $($_.Exception.Message)"
    }
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

    # Still down: most often orphaned sockets rather than a slow start.
    if ($LASTEXITCODE -ne 0) {
        Write-Log 'Engine still down; clearing orphaned socket dirs and retrying once'
        Get-Process 'Docker Desktop', 'com.docker.backend' -ErrorAction SilentlyContinue |
            Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 5

        Clear-StaleDockerSocketDir "$env:LOCALAPPDATA\Docker\run"
        Clear-StaleDockerSocketDir "$env:LOCALAPPDATA\docker-secrets-engine"

        Start-Service 'com.docker.service' -ErrorAction SilentlyContinue
        Start-Process 'C:\Program Files\Docker\Docker\Docker Desktop.exe'

        # The named pipe appearing is a faster and more reliable readiness
        # signal than `docker info`, which blocks rather than failing when
        # the engine is absent.
        $deadline = (Get-Date).AddSeconds($StartupTimeoutSeconds)
        while (-not (Test-Path '\\.\pipe\dockerDesktopLinuxEngine') -and (Get-Date) -lt $deadline) {
            Start-Sleep -Seconds 6
        }
    }

    if (-not (Test-Path '\\.\pipe\dockerDesktopLinuxEngine')) {
        Write-Log "FATAL Docker did not start within $StartupTimeoutSeconds s (after socket cleanup)"
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
    'exec', '-w', '/opt/spz/tools/feed-import', $Container,
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
