# OPS-CLEANUP — local repository cleanup for booster-shop-ops.
# Owner-executed only. Claude does not run this.
#
# Design:
#   - Nothing is deleted. Everything is MOVED to a trash folder outside the repo.
#   - BLOCK 1 touches only git-ignored files -> no commit needed, zero git risk.
#   - BLOCK 2 touches tracked files -> needs its own commit (BLOCK 3).
#   - Run BLOCK 1 first, verify the site tooling still works, then BLOCK 2/3.
#
# Trash location: C:\Users\14bez\Downloads\Booster Shop\_trash_20260805
# Review it for a week, then delete it manually.

# =====================================================================
# BLOCK 1 — git-ignored junk. No commit. Safe to run any time.
# =====================================================================

$Repo  = "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops"
$Trash = "C:\Users\14bez\Downloads\Booster Shop\_trash_20260805"

Set-Location $Repo
New-Item -ItemType Directory -Force -Path $Trash | Out-Null

# ncrm/.next  — Next.js build output, ~695 MB.
# Regenerated automatically on the next `npm run dev` or `npm run build`.
# ncrm/node_modules is deliberately KEPT so NCRM work can resume without
# a re-download; it is regenerable with `npm install` if you ever want the space.
$items = @(
  "ncrm\.next",
  "ncrm\tsconfig.tsbuildinfo",
  "scripts\__pycache__",
  "dashboard.zip",
  "testwrite.tmp",
  "_writetest.tmp"
)

foreach ($i in $items) {
  $src = Join-Path $Repo $i
  if (Test-Path $src) {
    $size = "{0:N1} MB" -f ((Get-ChildItem $src -Recurse -File -EA SilentlyContinue |
             Measure-Object Length -Sum).Sum / 1MB)
    Move-Item -Path $src -Destination (Join-Path $Trash ($i -replace '\\','__')) -Force
    Write-Host ("moved  {0,-28} {1}" -f $i, $size) -ForegroundColor Green
  } else {
    Write-Host ("skip   {0,-28} not found" -f $i) -ForegroundColor DarkGray
  }
}

Write-Host "`nBLOCK 1 done. git status should be unchanged:" -ForegroundColor Cyan
git status --short


# =====================================================================
# BLOCK 2 — patches/ (162 tracked files, 5.8 MB).
# Run only after BLOCK 1 looks right.
#
# Why this is safe: every one of these 162 files is committed. Full content
# stays retrievable forever with:
#     git log --oneline -- patches/<filename>
#     git show <commit>:patches/<filename>
# context-index.md references patches/ exactly once, so task-context lookup
# does not depend on them.
# =====================================================================

$Repo  = "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops"
$Trash = "C:\Users\14bez\Downloads\Booster Shop\_trash_20260805\patches"

Set-Location $Repo
New-Item -ItemType Directory -Force -Path $Trash | Out-Null

# Guard: refuse to run if the working tree has uncommitted changes in patches/
$dirty = git status --porcelain -- patches/ | Where-Object { $_ -notmatch 'PASTE-THIS-BLOCK' }
if ($dirty) {
  Write-Host "STOP: uncommitted changes in patches/ — commit or stash them first:" -ForegroundColor Red
  $dirty
  return
}

# .gitkeep stays so the folder survives in git.
$moved = 0
Get-ChildItem "$Repo\patches" -File | Where-Object { $_.Name -ne '.gitkeep' } | ForEach-Object {
  Move-Item $_.FullName (Join-Path $Trash $_.Name) -Force
  $moved++
}
Write-Host "moved $moved files out of patches/" -ForegroundColor Green
Write-Host "`nStaged deletions preview:" -ForegroundColor Cyan
git status --short -- patches/ | Select-Object -First 10
Write-Host "... (run BLOCK 3 to commit)"


# =====================================================================
# BLOCK 3 — commit the patches/ removal only.
# Stages patches/ and nothing else, so the pending 3D-P work is untouched.
# =====================================================================

try {
  Set-Location "C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops"
  New-Item -ItemType File -Force -Path ".autosync-pause" | Out-Null

  git add -A -- patches/

  # Validate: every staged path must be under patches/
  $staged = git diff --cached --name-only
  $bad    = $staged | Where-Object { $_ -notlike 'patches/*' }
  if ($bad) {
    Write-Host "STOP: unexpected paths staged, aborting:" -ForegroundColor Red
    $bad
    git reset
    return
  }
  Write-Host ("staged {0} paths, all under patches/" -f $staged.Count) -ForegroundColor Green

  git commit -m "ops: retire applied patch runners from working tree (history kept in git)"
  git push
}
finally {
  Remove-Item ".autosync-pause" -Force -ErrorAction SilentlyContinue
  Write-Host "autosync sentinel removed." -ForegroundColor Cyan
}
