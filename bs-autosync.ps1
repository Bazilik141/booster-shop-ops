# bs-autosync.ps1 - background auto-pull for booster-shop-ops (hardened 2026-06-24)
# Pulls the current branch periodically so Codex pushes appear locally.
# Push your own files via bspush.
# Run:  powershell -ExecutionPolicy Bypass -File "<path>\bs-autosync.ps1"
# Stop: Ctrl+C in that window.
#
# Guards against the git collisions that corrupted the index on 2026-06-24:
#   1. Pause sentinel  (.autosync-pause)  -> full back-off while an agent/owner does git work
#   2. Stale-lock reaper                  -> clears index.lock left by a crashed process
#   3. Corrupt-index auto-recovery        -> rebuilds .git/index from HEAD (working tree preserved)
#   4. Skip pull while working tree dirty -> never merge into a tree mid-commit
#
# To pause manually:  New-Item .autosync-pause   (delete the file to resume)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repo

$lockFile  = Join-Path $repo '.git\index.lock'
$indexFile = Join-Path $repo '.git\index'
$pauseFile = Join-Path $repo '.autosync-pause'
$staleSec  = 120   # index.lock older than this AND no git.exe running = stale -> reap

function Git-Running {
    [bool](Get-Process git -ErrorAction SilentlyContinue)
}

Write-Host "bs-autosync (hardened): watching '$repo' (Ctrl+C to stop)" -ForegroundColor Cyan

while ($true) {
    $ts = Get-Date -Format 'HH:mm:ss'

    # 1. explicit pause — an agent/owner is doing git work; do not touch the repo at all
    if (Test-Path $pauseFile) {
        Write-Host "[$ts] paused (.autosync-pause), skipping" -ForegroundColor DarkGray
        Start-Sleep -Seconds 15; continue
    }

    # 2. stale-lock reaper — only if old AND no git process is alive (never kill a live op)
    if (Test-Path $lockFile) {
        $age = [int](New-TimeSpan -Start (Get-Item $lockFile).LastWriteTime -End (Get-Date)).TotalSeconds
        if ($age -gt $staleSec -and -not (Git-Running)) {
            Write-Host "[$ts] removing stale index.lock (age ${age}s, no git running)" -ForegroundColor Yellow
            Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
        } else {
            Write-Host "[$ts] index.lock present (age ${age}s), skipping pull" -ForegroundColor DarkYellow
            Start-Sleep -Seconds 10; continue
        }
    }

    # read state once (also surfaces a corrupt index)
    $state = git -C $repo status --porcelain 2>&1 | Out-String

    # 3. corrupt-index auto-recovery — rebuild from HEAD; working-tree changes are preserved
    if ($state -match 'index file corrupt|bad signature') {
        Write-Host "[$ts] index corrupt -> rebuilding (del index; git reset)" -ForegroundColor Red
        Remove-Item $indexFile -Force -ErrorAction SilentlyContinue
        git -C $repo reset -q 2>&1 | Out-Null
        Start-Sleep -Seconds 5; continue
    }

    # 4. dirty working tree -> a commit may be in flight; skip pull to avoid collision
    if ($state.Trim()) {
        Write-Host "[$ts] working tree dirty, skipping pull (avoid commit collision)" -ForegroundColor DarkGray
        Start-Sleep -Seconds 30; continue
    }

    # 5. the pull (fast-forward only)
    try {
        $branch = (git -C $repo rev-parse --abbrev-ref HEAD).Trim()
        $out  = git -C $repo pull --ff-only 2>&1
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
        Write-Host "[$ts] pull error: $($_.ToString())" -ForegroundColor Yellow
    }

    Start-Sleep -Seconds 120
}
