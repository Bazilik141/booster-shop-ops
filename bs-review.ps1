# bs-review.ps1 — AUTO-002: Codex patch auto-review
# Usage:
#   .\bs-review.ps1              # auto-detect latest diagnostic
#   .\bs-review.ps1 TASK-ID      # review specific task (e.g. R-13.1, AUTO-001)
#   .\bs-review.ps1 --dry-run    # print only, skip Notion + file save

param(
    [string]$TaskId = "",
    [switch]$DryRun
)

$scriptDir = $PSScriptRoot
Set-Location $scriptDir

# Build args list
$pyArgs = @("scripts/auto_review.py")
if ($TaskId) { $pyArgs += $TaskId }
if ($DryRun) { $pyArgs += "--dry-run" }

Write-Host "[bs-review] Starting AUTO-002 review..." -ForegroundColor Cyan

# Check python
$python = Get-Command python -ErrorAction SilentlyContinue
if (-not $python) {
    Write-Error "Python not found. Install Python 3.10+ and add to PATH."
    exit 1
}

# Check anthropic installed
$check = python -c "import anthropic" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "[bs-review] Installing anthropic SDK..." -ForegroundColor Yellow
    python -m pip install -r scripts/requirements.txt --quiet
}

python @pyArgs
