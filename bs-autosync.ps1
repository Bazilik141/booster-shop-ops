# bs-autosync.ps1 - background auto-pull for booster-shop-ops
# Pulls the current branch every 60s so Codex pushes appear locally
# (Claude reads them without you running git). Push your own files via bspush.
# Run:  powershell -ExecutionPolicy Bypass -File "<path>\bs-autosync.ps1"
# Stop: Ctrl+C in that window.

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repo

Write-Host "bs-autosync: watching '$repo' - pull every 60s (Ctrl+C to stop)" -ForegroundColor Cyan

while ($true) {
    $ts = Get-Date -Format 'HH:mm:ss'
    $lockFile = Join-Path $repo '.git\index.lock'
    if (Test-Path $lockFile) {
        Write-Host "[$ts] lock exists, skipping pull" -ForegroundColor DarkYellow
        Start-Sleep -Seconds 10
        continue
    }
    try {
        $branch = (git -C $repo rev-parse --abbrev-ref HEAD).Trim()
        $out = git -C $repo pull --ff-only 2>&1
        $text = ($out | Out-String).Trim()
        if ($text -match 'Already up to date') {
            # nothing new; stay quiet
        }
        elseif ($text -match 'fatal|error|CONFLICT|Aborting') {
            Write-Host "[$ts] ($branch) pull skipped: $text" -ForegroundColor Yellow
        }
        else {
            Write-Host "[$ts] ($branch) updated:" -ForegroundColor Green
            Write-Host $text
        }
    }
    catch {
        $err = $_.ToString()
        Write-Host "[$ts] pull error: $err" -ForegroundColor Yellow
    }
    Start-Sleep -Seconds 120
}
