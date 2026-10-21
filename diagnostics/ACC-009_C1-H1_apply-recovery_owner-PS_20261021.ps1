#requires -Version 5.1
<#
  ACC-009-C1-H1  Owner Preflight User-Agent variation packet  (DRAFT — owner-run only)
  File: diagnostics/ACC-009_C1-H1_apply-recovery_owner-PS_20261021.ps1
  Parent: ACC-009_C1-H1_user-agent_owner-PS_20261021.ps1 sha256 ffb828695bc1c4be9e5ced097ab870362280c34f0ca483a71449c260ae5eb36a
  Grandparent: ACC-009_C1-H1_schema_owner-agent-PS_20261020.ps1 sha256 82362b9f64061c916ae832749d01aff1d94123b52c9a784e2fce4d4183d26ae7
  Only delta: Preflight runs the SAME staged runner (sha256 3403f0f2d15c26e81963db5ed7511cd0d38f06eb37369de4874281a16bea6753)
  as: php -d user_agent=BoosterShop-ACC009-Runtime/1.0. Guarded hypothesis test for the
  observed HTTP 406; not a claimed fix; no WAF/ModSecurity attribution.
  -------------------------------------------------------------------------
  This script is DRAFTED for the owner to run. It is NOT executed here and is
  NOT approved. Codex checks it mechanically, then the separate required review
  runs. Owner alone invokes ssh/scp via the pre-configured 'booster' alias.
  SEPARATE invocations:  -Step Upload | Verify | Preflight | Apply | Rollback -BackupName <exact>
  Upload/Verify/Preflight branches are unchanged from parent ffb82869.
  Apply/Rollback (appendix) are guarded owner-run paths, NOT released by this draft:
  they need Codex checks, a distinct independent ready review and a separate final owner Apply go.
  No CacheOnly / Smoke / setup / diagnostic switch. No automatic rerun, retry or rollback.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Upload','Verify','Preflight','Apply','Rollback')]
    [string]$Step,

    [Parameter(Mandatory = $false)]
    [ValidateScript({ $_ -cmatch '^ACC-009-C1-H1-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$' })]
    [string]$BackupName
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------- constants
$TaskId            = 'ACC-009-C1-H1'
$LocalRunner       = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\ACC-009-C1-H1_courier-notifications-schema_20261020.php'
$PinnedSha         = '3403f0f2d15c26e81963db5ed7511cd0d38f06eb37369de4874281a16bea6753'
$PinnedBytes       = 1087629
$SshAlias          = 'booster'
$ExpectedDirStorage= '/home2/boosters/ocartdata/storage/'
$PinnedTwigSha     = '92a6ac0af6ff88053ecafe0acd86e249bddaab664080a549c0a502179f537a69'

$KnownMysqlWarning   = 'WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.'
$DoneLine            = 'done=ok'
$SourcePreflightLine = 'source_preflight=ok_C1_then_H1_runtime_browser_and_owner_release_gates_remain'

# Owner work-period agent mode: the existing encrypted key must already be loaded by the owner via
# scripts/booster_ssh_session.ps1 -Action Unlock. No prompt, password or key-file decrypt fallback is allowed.
$SshOpts = @('-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',
             '-o','IdentitiesOnly=yes','-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no',
             '-o','ForwardAgent=no','-o','PreferredAuthentications=publickey',
             '-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')

# bounded validated technical state + complete owner stdout receipts (exact repo path)
$RepoRoot  = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$StateDir  = Join-Path $RepoRoot 'work\acc009-private-stage-20261019\owner-receipts'
$StateFile = Join-Path $StateDir ('acc009_state_' + $PinnedSha.Substring(0,8) + '.json')

# ---------------------------------------------------------------- helpers
$script:RetainNotice = ''
function Stop-Draft([string]$msg) {
    Write-Host ""
    Write-Host "[STOP] $msg" -ForegroundColor Red
    if ($script:RetainNotice) { Write-Host "[STOP] $($script:RetainNotice)" -ForegroundColor Red }
    exit 2
}
function Note([string]$msg)  { Write-Host "[note] $msg" }
function Line()              { Write-Host ('-' * 70) }

function Assert-Sha([string]$v,[string]$what) {
    if ($v -notmatch '^[0-9a-f]{64}$') { Stop-Draft "Malformed sha for $what : '$v'" }
}
function Assert-SafeRemoteName([string]$n) {
    if ($n -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]*$') { Stop-Draft "Unsafe remote filename: '$n'" }
}
function Assert-AbsUnixPath([string]$p) {
    if ($p.Length -gt 256 -or $p -cnotmatch '^/[A-Za-z0-9._/\-]+$') { Stop-Draft "Unsafe absolute path: '$p'" }
    if ($p -match '(^|/)\.{1,2}(/|$)' -or $p.Contains('//')) { Stop-Draft "Unsafe path component (dot/dot-dot/empty): '$p'" }
}

function Quote-Arg([string]$a) {
    if ($null -eq $a -or $a -eq '') { return '""' }
    if ($a -match '[\s"]') { return '"' + ($a -replace '"','\"') + '"' }
    return $a
}

function Invoke-Proc {
    param(
        [Parameter(Mandatory=$true)][string]$File,
        [Parameter(Mandatory=$true)][string[]]$ArgList,
        [string]$StdinText = $null
    )
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    switch -Regex ($File) {
        '^(?i:ssh)(\.exe)?$' { $psi.FileName = Join-Path ([Environment]::SystemDirectory) 'OpenSSH\ssh.exe' }
        '^(?i:scp)(\.exe)?$' { $psi.FileName = Join-Path ([Environment]::SystemDirectory) 'OpenSSH\scp.exe' }
        default               { $psi.FileName = $File }
    }
    $psi.Arguments = (($ArgList | ForEach-Object { Quote-Arg $_ }) -join ' ')
    $psi.UseShellExecute        = $false
    $psi.CreateNoWindow         = $false   # owner console: visible passphrase prompt
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $false   # inherited: ssh/scp prompts and errors stay on owner console; never captured
    $psi.StandardOutputEncoding = [System.Text.Encoding]::UTF8
    $useStdin = $PSBoundParameters.ContainsKey('StdinText')   # [string] coerces $null to ''; scp must not get stdin redirected
    if ($useStdin) { $psi.RedirectStandardInput = $true }

    $p = New-Object System.Diagnostics.Process
    $p.StartInfo = $psi
    [void]$p.Start()
    $outTask = $p.StandardOutput.ReadToEndAsync()   # drain stdout BEFORE writing stdin

    if ($useStdin) {
        $enc = New-Object System.Text.UTF8Encoding($false)          # UTF8, no BOM
        $lf  = ($StdinText -replace "`r`n","`n") -replace "`r","`n" # LF only
        $bytes = $enc.GetBytes($lf)
        try {
            $p.StandardInput.BaseStream.Write($bytes,0,$bytes.Length)
            $p.StandardInput.BaseStream.Flush()
            $p.StandardInput.Close()
        } catch {
            Write-Host "[note] stdin pipe closed early (ssh ended, e.g. auth failure); exit code decides" -ForegroundColor Yellow
        }
    }

    $p.WaitForExit()
    $o = $outTask.Result
    $code = $p.ExitCode
    $p.Dispose()

    return [pscustomobject]@{ StdOut = $o; StdErr = '(stderr not captured: shown on owner console)'; ExitCode = $code }
}

function Get-TaggedValue([string]$text,[string]$key) {
    $found = New-Object 'System.Collections.Generic.List[string]'
    foreach ($raw in ($text -split "`n")) {
        $l = $raw.TrimEnd("`r")
        if ($l.StartsWith($key + '=', [System.StringComparison]::Ordinal)) { $found.Add($l.Substring($key.Length + 1)) }
    }
    if ($found.Count -gt 1) { Stop-Draft "Duplicate '$key=' tag rows ($($found.Count)); refusing ambiguous output" }
    if ($found.Count -eq 1) { return $found[0] }
    return $null
}
function Get-Block([string]$text,[string]$begin,[string]$end) {
    $lines = @($text -split "`n" | ForEach-Object { $_.TrimEnd("`r") })
    $bi = @(); $ei = @()
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -ceq $begin) { $bi += $i }
        if ($lines[$i] -ceq $end)   { $ei += $i }
    }
    if ($bi.Count -ne 1 -or $ei.Count -ne 1 -or $ei[0] -le $bi[0]) { Stop-Draft "Markers $begin / $end are not exactly one ordered pair" }
    $sb = New-Object System.Text.StringBuilder
    for ($i = $bi[0] + 1; $i -lt $ei[0]; $i++) { [void]$sb.Append($lines[$i]).Append("`n") }
    return $sb.ToString()
}
function Assert-RemoteOk($res,[string]$ctx) {
    foreach ($raw in ($res.StdOut -split "`n")) {
        $l = $raw.TrimEnd("`r")
        if ($l.StartsWith('FAIL')) { Stop-Draft "$ctx remote reported: $l" }
    }
    if ($res.ExitCode -ne 0) {
        Stop-Draft "$ctx ssh exit=$($res.ExitCode). $($res.StdErr) No retry/cleanup."
    }
}

function Ensure-StateDir() {
    if (-not (Test-Path -LiteralPath $RepoRoot -PathType Container)) { Stop-Draft "Repo root missing: $RepoRoot" }
    [void][System.IO.Directory]::CreateDirectory($StateDir)
}
# Complete owner stdout receipt, written immediately after each owner ssh/scp returns,
# BEFORE any assertion/parsing. Unique exclusive name (CreateNew); never overwrites.
function Save-Receipt($res,[string]$label) {
    Ensure-StateDir
    $stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssfffZ')
    $name  = '{0}_{1}_{2}_{3}_{4}.log' -f $TaskId,$label,$stamp,$PinnedSha.Substring(0,8),([guid]::NewGuid().ToString('N').Substring(0,8))
    $path  = Join-Path $StateDir $name
    $body  = "# captured owner receipt; awaiting Codex assessment (not approval)`n# step=$Step label=$label exit=$($res.ExitCode) stderr=owner-console-not-captured`n" + [string]$res.StdOut
    $bytes = (New-Object System.Text.UTF8Encoding($false)).GetBytes($body)
    $fs = New-Object System.IO.FileStream($path,[System.IO.FileMode]::CreateNew,[System.IO.FileAccess]::Write)
    try { $fs.Write($bytes,0,$bytes.Length) } finally { $fs.Dispose() }
    Note "complete owner stdout receipt captured: $path"
    return $path
}

function Load-State() {
    if (Test-Path -LiteralPath $StateFile) {
        try { return (Get-Content -LiteralPath $StateFile -Raw | ConvertFrom-Json) }
        catch { return $null }
    }
    return $null
}
function Save-State($obj) {
    Ensure-StateDir
    ($obj | ConvertTo-Json -Depth 6) | Set-Content -LiteralPath $StateFile -Encoding UTF8
}
function State-Prop($state,[string]$name) {
    if ($null -eq $state) { return $null }
    $pp = $state.PSObject.Properties[$name]
    if ($null -eq $pp) { return $null }
    return $pp.Value
}

# ------------------------------------------------- local runner validation
function Assert-LocalRunner() {
    if (-not (Test-Path -LiteralPath $LocalRunner)) {
        Stop-Draft "Local runner not found: $LocalRunner"
    }
    $item = Get-Item -LiteralPath $LocalRunner -Force
    if ($item.PSIsContainer) { Stop-Draft "Local runner is a directory" }
    if ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
        Stop-Draft "Local runner is a reparse point / link; refusing"
    }
    if ($item.Length -ne $PinnedBytes) {
        Stop-Draft "Local byte count $($item.Length) != pinned $PinnedBytes"
    }
    $h = (Get-FileHash -LiteralPath $LocalRunner -Algorithm SHA256).Hash.ToLower()
    Assert-Sha $h 'local runner'
    if ($h -ne $PinnedSha) {
        Stop-Draft "Local SHA256 $h != pinned $PinnedSha"
    }
    Note "local runner OK  regular/non-reparse  bytes=$PinnedBytes  sha256=$h"
    return $h
}

$runnerName = Split-Path -Leaf $LocalRunner
Assert-SafeRemoteName $runnerName
Assert-Sha $PinnedSha 'pinned'
Assert-Sha $PinnedTwigSha 'pinned twig'
if ($Step -eq 'Rollback') {
    if ([string]::IsNullOrEmpty($BackupName) -or $BackupName -cnotmatch '^ACC-009-C1-H1-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$') { Stop-Draft 'Rollback requires -BackupName = exact basename from the actual Apply receipt (no latest/glob/sorting).' }
} elseif ($PSBoundParameters.ContainsKey('BackupName')) { Stop-Draft '-BackupName is accepted only with -Step Rollback' }

Write-Host ""
Write-Host "task=$TaskId;step=$Step  (DRAFT - owner-run; not executed here; not approved)" -ForegroundColor Cyan
Line

# =========================================================== BASH TEMPLATES
# Static ASCII bash, single-quoted here-strings (no PS interpolation). Only the
# pre-validated placeholders __FILENAME__ / __PINNED_SHA__ / __EXPECTED_PHP__ are
# replaced. $BashAdmit = NON-mutating directory admission (Verify, Preflight, and
# after Upload's precheck). Only Upload's precheck may mkdir/chmod policy dirs.

$BashAdmit = @'
set -u
for d in "$HOME" "$HOME/public_html" "$HOME/private" "$HOME/private/booster-patches" "$HOME/private/booster-pulls"; do
  if [ -L "$d" ]; then echo "FAIL linked directory: $d"; exit 30; fi
  if [ ! -d "$d" ]; then echo "FAIL missing or not a directory: $d"; exit 33; fi
done
home=$(readlink -f "$HOME") || home=""
pub=$(readlink -f "$HOME/public_html") || pub=""
stage=$(readlink -f "$HOME/private/booster-patches") || stage=""
pulls=$(readlink -f "$HOME/private/booster-pulls") || pulls=""
if [ -z "$home" ] || [ -z "$pub" ] || [ -z "$stage" ] || [ -z "$pulls" ]; then echo "FAIL path resolution"; exit 35; fi
echo "RESOLVED_HOME=$home"
echo "RESOLVED_PUB=$pub"
echo "RESOLVED_STAGE=$stage"
echo "RESOLVED_PULLS=$pulls"
case "$stage/" in "$pub/"*) echo "FAIL stage inside webroot"; exit 35;; esac
case "$pulls/" in "$pub/"*) echo "FAIL pulls inside webroot"; exit 35;; esac
echo "ADMIT_DONE=ok"
'@

$BashPrecheck = @'
set -u
for d in "$HOME" "$HOME/public_html"; do
  if [ -L "$d" ]; then echo "FAIL linked home/webroot: $d"; exit 31; fi
  if [ ! -d "$d" ]; then echo "FAIL missing home/webroot: $d"; exit 31; fi
done
for d in "$HOME/private" "$HOME/private/booster-patches" "$HOME/private/booster-pulls"; do
  if [ -L "$d" ]; then echo "FAIL symlink component: $d"; exit 30; fi
  if [ -e "$d" ] && [ ! -d "$d" ]; then echo "FAIL not a directory: $d"; exit 33; fi
  if [ ! -e "$d" ]; then mkdir -m 700 "$d" || { echo "FAIL mkdir $d"; exit 32; }; fi
  chmod 700 "$d" || { echo "FAIL chmod $d"; exit 34; }
done
'@

$BashDestCheck = @'
set -u
dest="$HOME/private/booster-patches/__FILENAME__"
if [ -L "$dest" ]; then echo "FAIL dest is link"; exit 36; fi
if [ -e "$dest" ]; then
  if [ ! -f "$dest" ]; then echo "FAIL dest not regular"; exit 37; fi
  echo "DEST_EXISTS=$(sha256sum "$dest" | awk '{print $1}')"
else
  echo "DEST_ABSENT=1"
fi
echo "PRECHECK_DONE=ok"
'@

$BashVerify = @'
set -u
cfg="$HOME/public_html/config.php"
runner="$HOME/private/booster-patches/__FILENAME__"
if [ -L "$runner" ]; then echo "FAIL staged runner is link"; exit 21; fi
if [ ! -f "$runner" ]; then echo "FAIL staged runner missing"; exit 20; fi
got=$(sha256sum "$runner" 2>/dev/null | awk '{print $1}')
if [ -z "$got" ]; then echo "FAIL staged runner hash unavailable"; exit 28; fi
echo "STAGED_SHA=$got"
if [ "$got" != "__PINNED_SHA__" ]; then echo "FAIL staged runner hash mismatch (no PHP invoked on runner)"; exit 29; fi
phpbin=$(command -v php 2>/dev/null)
if [ -z "$phpbin" ]; then echo "FAIL no php on PATH"; exit 22; fi
if [ ! -x "$phpbin" ]; then echo "FAIL php not executable"; exit 23; fi
ver=$("$phpbin" -r 'echo PHP_VERSION;' 2>/dev/null)
echo "PHP_BIN=$phpbin"
echo "PHP_VERSION=$ver"
case "$ver" in 8.0.*) ;; *) echo "FAIL php version not 8.0.x: $ver"; exit 24;; esac
echo "---PHP_V-BEGIN---"
"$phpbin" -v
echo "---PHP_V-END---"
echo "---PHP_LINT-BEGIN---"
"$phpbin" -l "$runner"
lintrc=$?
echo "---PHP_LINT-END---"
echo "PHP_LINT_RC=$lintrc"
if [ ! -f "$cfg" ]; then echo "FAIL catalog config.php missing"; exit 25; fi
cd "$HOME/public_html" || { echo "FAIL cd webroot"; exit 25; }
consts=$("$phpbin" -r 'ob_start(); require $argv[1]; ob_end_clean(); foreach (array("DIR_STORAGE", "DIR_CACHE", "DIR_MODIFICATION") as $c) { echo $c, "=", (defined($c) ? constant($c) : "UNDEFINED"), "\n"; }' "$cfg") || { echo "FAIL runtime constant evaluation"; exit 26; }
dirstorage=""; dircache=""; dirmod=""; n=0
while IFS= read -r ln; do
  n=$((n+1))
  case "$ln" in
    DIR_STORAGE=*) dirstorage="${ln#DIR_STORAGE=}";;
    DIR_CACHE=*) dircache="${ln#DIR_CACHE=}";;
    DIR_MODIFICATION=*) dirmod="${ln#DIR_MODIFICATION=}";;
    *) echo "FAIL unexpected constant evaluation row"; exit 26;;
  esac
done <<< "$consts"
if [ "$n" -ne 3 ]; then echo "FAIL constant evaluation row count $n"; exit 26; fi
if [ "$dirstorage" = "UNDEFINED" ] || [ "$dircache" = "UNDEFINED" ]; then echo "FAIL required DIR_STORAGE/DIR_CACHE undefined"; exit 27; fi
for v in "$dirstorage" "$dircache" "$dirmod"; do
  [ "$v" = "UNDEFINED" ] && continue
  case "$v" in /*) ;; *) echo "FAIL constant path not absolute"; exit 27;; esac
  case "$v" in *[!A-Za-z0-9._/-]*|*/../*|*/..|*/./*|*/.|*//*) echo "FAIL unsafe constant path (not printed)"; exit 27;; esac
  if [ "${#v}" -gt 256 ]; then echo "FAIL constant path too long"; exit 27; fi
done
echo "DIR_STORAGE=$dirstorage"
echo "DIR_CACHE=$dircache"
echo "DIR_MODIFICATION=$dirmod"
twig="$HOME/public_html/system/library/template/twig.php"
if [ -f "$twig" ]; then
  echo "TWIG_SHA=$(sha256sum "$twig" | awk '{print $1}')"
else
  echo "TWIG_ABSENT=1"
fi
cache="${dircache%/}"
if [ -L "$cache" ]; then echo "FAIL DIR_CACHE is link"; exit 38; fi
if [ -d "$cache" ]; then echo "DIR_CACHE_STATE=dir"; elif [ -e "$cache" ]; then echo "FAIL DIR_CACHE not a directory"; exit 38; else echo "DIR_CACHE_STATE=absent"; fi
tmpl="$cache/template"
echo "TEMPLATE_PATH=$tmpl/"
if [ -L "$tmpl" ]; then echo "FAIL cache template path is link"; exit 39;
elif [ -d "$tmpl" ]; then echo "TEMPLATE_STATE=dir";
elif [ -e "$tmpl" ]; then echo "TEMPLATE_STATE=other";
else echo "TEMPLATE_STATE=absent"; fi
echo "VERIFY_DONE=ok"
'@

$BashPreflight = @'
set -u
umask 077
runner="$HOME/private/booster-patches/__FILENAME__"
if [ -L "$runner" ]; then echo "FAIL runner is link"; exit 51; fi
if [ ! -f "$runner" ]; then echo "FAIL runner missing"; exit 50; fi
got=$(sha256sum "$runner" 2>/dev/null | awk '{print $1}')
if [ -z "$got" ]; then echo "FAIL runner hash unavailable"; exit 58; fi
echo "RUNNER_SHA=$got"
if [ "$got" != "__PINNED_SHA__" ]; then echo "FAIL runner hash mismatch (runner not executed)"; exit 59; fi
phpbin=$(command -v php 2>/dev/null)
if [ -z "$phpbin" ]; then echo "FAIL no php on PATH"; exit 52; fi
if [ "$phpbin" != "__EXPECTED_PHP__" ]; then echo "FAIL php path mismatch: $phpbin"; exit 53; fi
if [ ! -x "$phpbin" ]; then echo "FAIL php not executable"; exit 54; fi
ver=$("$phpbin" -r 'echo PHP_VERSION;' 2>/dev/null)
case "$ver" in 8.0.*) ;; *) echo "FAIL php version not 8.0.x: $ver"; exit 55;; esac
echo "PHP_BIN=$phpbin"
echo "PHP_VERSION=$ver"
rdir="$HOME/private/booster-pulls"
ts=$(date -u +%Y%m%dT%H%M%SZ)
out=$(mktemp "$rdir/ACC-009-C1-H1_preflight_${ts}_stdout.XXXXXXXXXX") || { echo "FAIL mktemp stdout receipt"; exit 56; }
err=$(mktemp "$rdir/ACC-009-C1-H1_preflight_${ts}_stderr.XXXXXXXXXX") || { echo "FAIL mktemp stderr receipt"; exit 56; }
chmod 600 "$out" "$err" || { echo "FAIL chmod receipts"; exit 56; }
cd "$HOME/public_html" || { echo "FAIL cd webroot"; exit 57; }
echo "HTTP_USER_AGENT=BoosterShop-ACC009-Runtime/1.0"
"$phpbin" -d user_agent=BoosterShop-ACC009-Runtime/1.0 "$runner" --dry-run >"$out" 2>"$err"
rc=$?
echo "CLI_EXIT=$rc"
echo "STDOUT_FILE=$out"
echo "STDERR_FILE=$err"
echo "UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "---DRYRUN-STDOUT-BEGIN---"
cat "$out"
echo "---DRYRUN-STDOUT-END---"
echo "---DRYRUN-STDERR-BEGIN---"
cat "$err"
echo "---DRYRUN-STDERR-END---"
echo "PREFLIGHT_WRAPPER_DONE=ok"
'@

# ================================================================== UPLOAD
if ($Step -eq 'Upload') {
    $localSha = Assert-LocalRunner

    $bash = ($BashPrecheck + "`n" + $BashAdmit + "`n" + $BashDestCheck).Replace('__FILENAME__',$runnerName)
    Note "owner ssh -> stage precheck (require real home/webroot; create/chmod only ~/private, ~/private/booster-patches, ~/private/booster-pulls @0700; reject links; stage+pulls outside webroot)"
    $pc = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $bash
    $pcReceipt = Save-Receipt $pc 'upload-precheck'
    Write-Host $pc.StdOut
    Assert-RemoteOk $pc 'Upload precheck'
    if ((Get-TaggedValue $pc.StdOut 'ADMIT_DONE') -cne 'ok') { Stop-Draft "Directory admission did not complete" }
    if ((Get-TaggedValue $pc.StdOut 'PRECHECK_DONE') -cne 'ok') { Stop-Draft "Precheck did not complete" }

    $destSha = Get-TaggedValue $pc.StdOut 'DEST_EXISTS'
    $destAbs = Get-TaggedValue $pc.StdOut 'DEST_ABSENT'

    if ($null -ne $destSha) {
        Assert-Sha $destSha 'existing destination'
        if ($destSha -eq $PinnedSha) {
            Note "destination already identical (sha matches pin) - skipping scp; no bytes overwritten"
        } else {
            Stop-Draft "Destination exists with DIFFERENT bytes (sha=$destSha). Refusing to overwrite unrelated destination."
        }
    }
    elseif ($null -ne $destAbs) {
        $remoteTarget = "${SshAlias}:private/booster-patches/${runnerName}"
        Note "owner scp -> $remoteTarget"
        $sc = Invoke-Proc -File 'scp' -ArgList ($SshOpts + @($LocalRunner, $remoteTarget))
        $scReceipt = Save-Receipt $sc 'upload-scp'
        if ($sc.StdOut) { Write-Host $sc.StdOut }
        if ($sc.ExitCode -ne 0) { Stop-Draft "scp failed exit=$($sc.ExitCode). $($sc.StdErr) No retry/cleanup." }
        Note "scp completed exit=0"
    }
    else { Stop-Draft "Precheck gave neither DEST_EXISTS nor DEST_ABSENT" }

    # no post-upload reconnect: the next SEPARATE -Step Verify compares the remote hash to the pin

    Save-State ([pscustomobject]@{
        task        = $TaskId
        runner_sha  = $PinnedSha
        runner_name = $runnerName
        upload_done = $true
        upload_utc  = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    })
    Line
    Note "UPLOAD captured (scp exit=0 or identical destination) at ~/private/booster-patches/$runnerName. Remote hash NOT verified here; awaiting the SEPARATE -Step Verify comparison."
    Note "Next SEPARATE owner invocation: -Step Verify"
    exit 0
}

# ================================================================== VERIFY
if ($Step -eq 'Verify') {
    $localSha = Assert-LocalRunner

    $bash = ($BashAdmit + "`n" + $BashVerify).Replace('__FILENAME__',$runnerName).Replace('__PINNED_SHA__',$PinnedSha)
    Note "owner ssh -> nonmutating dir admission, remote sha==pin BEFORE any PHP touches runner, PHP 8.0.x CLI, -v / -l, runtime constants, cache/template state, twig pin"
    $vr = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $bash
    $vrReceipt = Save-Receipt $vr 'verify'
    Write-Host $vr.StdOut
    Assert-RemoteOk $vr 'Verify'
    if ((Get-TaggedValue $vr.StdOut 'ADMIT_DONE') -cne 'ok') { Stop-Draft "Directory admission did not complete" }
    if ((Get-TaggedValue $vr.StdOut 'VERIFY_DONE') -cne 'ok') { Stop-Draft "Verify did not complete" }

    $stagedSha = Get-TaggedValue $vr.StdOut 'STAGED_SHA'
    Assert-Sha $stagedSha 'staged'
    if ($stagedSha -ne $PinnedSha) { Stop-Draft "Staged sha $stagedSha != pinned $PinnedSha" }

    $phpBin = Get-TaggedValue $vr.StdOut 'PHP_BIN'
    $phpVer = Get-TaggedValue $vr.StdOut 'PHP_VERSION'
    if ([string]::IsNullOrEmpty($phpBin)) { Stop-Draft "No resolved PHP binary reported" }
    Assert-AbsUnixPath $phpBin
    if ($phpVer -notmatch '^8\.0\.') { Stop-Draft "PHP version not 8.0.x: $phpVer" }

    $lintRc = Get-TaggedValue $vr.StdOut 'PHP_LINT_RC'
    if ($lintRc -ne '0') { Stop-Draft "php -l returned rc=$lintRc" }

    $dirStorage = Get-TaggedValue $vr.StdOut 'DIR_STORAGE'
    $dirCache   = Get-TaggedValue $vr.StdOut 'DIR_CACHE'
    $dirMod     = Get-TaggedValue $vr.StdOut 'DIR_MODIFICATION'
    if ([string]::IsNullOrEmpty($dirStorage) -or $dirStorage -ceq 'UNDEFINED') { Stop-Draft "DIR_STORAGE runtime constant not defined" }
    Assert-AbsUnixPath $dirStorage
    if ($dirStorage -cne $ExpectedDirStorage) { Stop-Draft "DIR_STORAGE '$dirStorage' != expected '$ExpectedDirStorage'" }
    if ([string]::IsNullOrEmpty($dirCache) -or $dirCache -ceq 'UNDEFINED') { Stop-Draft "DIR_CACHE runtime constant required but not defined" }
    Assert-AbsUnixPath $dirCache
    if ($null -eq $dirMod) { Stop-Draft "DIR_MODIFICATION row missing" }
    if ($dirMod -cne 'UNDEFINED') { Assert-AbsUnixPath $dirMod }
    Note "DIR_MODIFICATION (report-only, not used): $dirMod"

    if ($null -ne (Get-TaggedValue $vr.StdOut 'TWIG_ABSENT')) {
        Stop-Draft "Pinned twig.php absent at system/library/template/twig.php"
    }
    $twigSha = Get-TaggedValue $vr.StdOut 'TWIG_SHA'
    Assert-Sha $twigSha 'twig'
    if ($twigSha -ne $PinnedTwigSha) {
        Stop-Draft "twig.php sha $twigSha != pinned $PinnedTwigSha (template rule assumption changed)"
    }
    $cacheState = Get-TaggedValue $vr.StdOut 'DIR_CACHE_STATE'
    $tmplPath   = Get-TaggedValue $vr.StdOut 'TEMPLATE_PATH'
    $tmplState  = Get-TaggedValue $vr.StdOut 'TEMPLATE_STATE'
    if (@('dir','absent') -cnotcontains $cacheState) { Stop-Draft "DIR_CACHE state missing/unexpected: $cacheState" }
    if (@('dir','absent','other') -cnotcontains $tmplState) { Stop-Draft "Template path state missing/unexpected: $tmplState" }
    Assert-AbsUnixPath $tmplPath
    Note "resolved DIR_CACHE state=$cacheState ; template path $tmplPath state=$tmplState (report-only; nothing created/deleted)"

    Save-State ([pscustomobject]@{
        task        = $TaskId
        runner_sha  = $PinnedSha
        runner_name = $runnerName
        verify_done = $true
        php_bin     = $phpBin
        php_version = $phpVer
        dir_cache   = $dirCache
        dir_storage = $dirStorage
        dir_cache_state = $cacheState
        template_state  = $tmplState
        verify_receipt  = $vrReceipt
        verify_utc  = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    })
    Line
    Note "VERIFY captured: mechanical gates passed; awaiting Codex assessment (not approval). php=$phpBin ($phpVer); remote sha matched pin before any PHP touched the runner."
    Note "Saved validated technical data (php path bound to runner sha) for Preflight re-resolution."
    Note "Next SEPARATE owner invocation: -Step Preflight"
    exit 0
}

# =============================================================== PREFLIGHT
if ($Step -eq 'Preflight') {
    $localSha = Assert-LocalRunner

    $state = Load-State
    if ($null -eq $state) { Stop-Draft "No saved state. Run -Step Verify first." }
    if ((State-Prop $state 'runner_sha') -ne $PinnedSha) { Stop-Draft "Saved state runner_sha not bound to pinned sha." }
    if ((State-Prop $state 'verify_done') -ne $true)     { Stop-Draft "Verify not recorded. Run -Step Verify first." }
    $expectedPhp = State-Prop $state 'php_bin'
    if ([string]::IsNullOrEmpty($expectedPhp)) { Stop-Draft "No saved php_bin. Run -Step Verify first." }
    Assert-AbsUnixPath $expectedPhp   # treated as data, validated before shell use

    $bash = ($BashAdmit + "`n" + $BashPreflight).Replace('__FILENAME__',$runnerName).Replace('__PINNED_SHA__',$PinnedSha).Replace('__EXPECTED_PHP__',$expectedPhp)
    Note "owner ssh -> nonmutating dir admission, remote sha==pin BEFORE execution, re-resolve PHP (must equal saved path), then: cd ~/public_html && <php> -d user_agent=BoosterShop-ACC009-Runtime/1.0 ~/private/booster-patches/$runnerName --dry-run (UA variation; hypothesis test only)"
    $pf = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $bash
    $pfReceipt = Save-Receipt $pf 'preflight'
    Write-Host $pf.StdOut        # print ALL of it, retained complete
    Assert-RemoteOk $pf 'Preflight wrapper'
    if ((Get-TaggedValue $pf.StdOut 'ADMIT_DONE') -cne 'ok') { Stop-Draft "Directory admission did not complete" }
    if ((Get-TaggedValue $pf.StdOut 'PREFLIGHT_WRAPPER_DONE') -cne 'ok') { Stop-Draft "Preflight wrapper did not complete" }

    $runnerSha = Get-TaggedValue $pf.StdOut 'RUNNER_SHA'
    Assert-Sha $runnerSha 'preflight runner'
    if ($runnerSha -ne $PinnedSha) { Stop-Draft "Staged runner sha $runnerSha != pinned $PinnedSha" }

    $cliExit  = Get-TaggedValue $pf.StdOut 'CLI_EXIT'
    $outFile  = Get-TaggedValue $pf.StdOut 'STDOUT_FILE'
    $errFile  = Get-TaggedValue $pf.StdOut 'STDERR_FILE'
    $utc      = Get-TaggedValue $pf.StdOut 'UTC'
    $stdoutBlk= Get-Block $pf.StdOut '---DRYRUN-STDOUT-BEGIN---' '---DRYRUN-STDOUT-END---'
    $stderrBlk= Get-Block $pf.StdOut '---DRYRUN-STDERR-BEGIN---' '---DRYRUN-STDERR-END---'

    # stderr: only the full exact known deprecation line (CR/LF normalized); empty lines ignored
    $badErr = @()
    foreach ($raw in ($stderrBlk -split "`n")) {
        $t = $raw.TrimEnd("`r")
        if ($t.Length -eq 0) { continue }
        if ($t -ceq $KnownMysqlWarning) { continue }
        $badErr += $t
    }
    if ($badErr.Count -gt 0) { Stop-Draft ("Unexpected stderr (no retry/cleanup): " + ($badErr -join ' | ')) }

    $outLines = @($stdoutBlk -split "`n" | ForEach-Object { $_.TrimEnd("`r") })
    $errRows  = @($outLines | Where-Object { $_.StartsWith('error=', [System.StringComparison]::Ordinal) })
    if ($errRows.Count -gt 0) { Stop-Draft ("Dry-run reported error= rows (no retry/cleanup): " + ($errRows -join ' | ')) }
    if ($cliExit -cne '0') { Stop-Draft "CLI exit=$cliExit (expected 0). Stopping; runner kept staged, no cleanup." }
    $doneRows = @($outLines | Where-Object { $_ -ceq $DoneLine })
    if ($doneRows.Count -ne 1) { Stop-Draft "Dry-run stdout must contain exactly one whole line '$DoneLine' (found $($doneRows.Count)). No retry/cleanup." }
    $srcRows  = @($outLines | Where-Object { $_ -ceq $SourcePreflightLine })
    if ($srcRows.Count -ne 1) { Stop-Draft "Dry-run stdout must contain exactly one whole line '$SourcePreflightLine' (found $($srcRows.Count)). No retry/cleanup." }

    # complete owner stdout receipt was already saved before any assertion
    $localReceipt = $pfReceipt

    Save-State ([pscustomobject]@{
        task           = $TaskId
        runner_sha     = $PinnedSha
        runner_name    = $runnerName
        verify_done    = $true
        php_bin        = $expectedPhp
        php_version    = (State-Prop $state 'php_version')
        dir_cache      = (State-Prop $state 'dir_cache')
        dir_storage    = (State-Prop $state 'dir_storage')
        preflight_done = $true
        preflight_utc  = $utc
        remote_stdout  = $outFile
        remote_stderr  = $errFile
        local_receipt  = $localReceipt
    })

    Line
    Note "PREFLIGHT captured: mechanical wrapper gates passed; awaiting Codex/ledger assessment (NOT approval, NOT an apply release)."
    Note "mode=--dry-run  php_ini_user_agent=BoosterShop-ACC009-Runtime/1.0  cli_exit=$cliExit  utc=$utc"
    Note "remote owner-only receipts: $outFile ; $errFile"
    Note "local complete receipt: $localReceipt"
    Note "Main runner kept staged at ~/private/booster-patches/$runnerName (not in public_html)."
    Note "APPLY HELD: pending complete host evidence + separate unchanged owner-browser probe receipts + owner release gates (C1 then H1)."
    Note "Codex checks the complete retained result mechanically against the current ledger; this script does not approve."
    Note "UA variation is a guarded hypothesis test only: a pass does not attribute the prior HTTP 406 to any WAF/ModSecurity rule. Any future Apply (NOT released here) must use the same -d user_agent flag."
    exit 0
}

# =====================================================================
# APPENDIX  ACC-009-C1-H1 owner Apply / Rollback  (DRAFT; owner-run only)
# Not executed here, not approved, not released. Reached only for
# -Step Apply | Rollback: the Upload/Verify/Preflight branches above exit first.
# Needs Codex checks, a distinct independent ready review and a separate final
# owner Apply go. No CacheOnly/Smoke/setup/diagnostic mode. No automatic rerun,
# retry, rollback or deletion of evidence.
# =====================================================================

$ExpectedHome         = '/home2/boosters'
$ExpectedPhp          = '/usr/local/bin/php'
$ExpectedPhpVer       = '8.0.30'
$ExpectedDirCache     = '/home2/boosters/ocartdata/storage/cache/'
$BackupRoot           = '/home2/boosters/_patch_backups'
$MaxTemplateEntries   = 5000
$RunUA                = 'BoosterShop-ACC009-Runtime/1.0'
$AcceptedPreflightLog = 'ACC-009-C1-H1_preflight_20261021T072436505Z_3403f0f2_dc01300a.log'
$AcceptedPreflightSha = '78810e1aca1aa1a2df3007a5e21c7c4658a97a69d47dd724d05d6c3253db75e0'
$BarrierLine          = 'composition_barrier=C1_23_after_H1_3_before_hashes_match'
$BackupNameRx         = '^ACC-009-C1-H1-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$'

# knownspec: 26 targets in runner order -> relative path, before sha ('absent' = created by Apply), after sha
$Spec = @(
    @('system/library/booster_checkout_readiness.php','absent','69a7d5fdfb9ee77e34f623f3e4ace59fd3553da102e908ff03f98bfa9e3cb9a8'),
    @('catalog/view/javascript/acc009-checkout.js','absent','8137d8b6a6b79cef1ad8a7910c452e7420c9fa8273bdf63cbbbf3f4c4d0b4771'),
    @('catalog/controller/checkout/checkout.php','dbf4b8b98c5be25286c7e44254484bb78830179092c2340c50f3a81b2949c3a2','761c051c9286c3022efc1e5deea02979023f2d45600d762cb2d222eda03e4cbf'),
    @('catalog/controller/checkout/confirm.php','6365c2a36bb575848cf4f578252f3642454ab8e37626ce7c1c14eabf4f60ac17','333c43b9da2c6101d44e5676a78648b8ab9b75296380675944fef88a3ee69288'),
    @('catalog/controller/checkout/payment_method.php','0497216e9267f92c385ea43b43e09fc0a9531ff5df71a6e7d954d6f816c9b79c','dc34248a36f05445502aecb4eff2b03accc6e68bf9318b7f699252023821fbe9'),
    @('catalog/controller/checkout/register.php','db5537a07b093354a8763e3361307e11c89c0948fd646dadec8f0570f9b5a25a','5858276323363e09ae727e47a5aa13e2918d2d455991feeefa6daf3e52890c2f'),
    @('catalog/controller/checkout/shipping_address.php','51a83f3453fc0027c4d0c34f7eecba335abff11375b9c70f8c723c1b1974ad64','c5e456585fe2da3f9ede3435b1522848ff2a147dc0ad5418d1773bb65cd832b5'),
    @('catalog/view/javascript/checkout-reskin.js','ab2403e5aca0de41f83b5d0d88a269302c33534dbec09fcb45daa8212f1fb100','9af1c78e3c12059e06685e5fcc58f39eaa241f734617ad6f5bf638ed2a334392'),
    @('catalog/view/javascript/checkout-state.js','1bafab528a73d5f5cbb6ecaacd0e384b1a875ab80d3ed101f34e1eed32641a29','83a9de5bbb2c6184abd5c9ceb89b23841532b415ad1e4cb14cf48315e4c9f97c'),
    @('catalog/view/template/checkout/checkout.twig','e334d78880c00022095a8767f5788edeb450044f3fcbad0e3f674faee2acd34a','d989bf48ff149d3cf46f1c827b94a9b3e400e457f7c210f9232331317db1a7f6'),
    @('catalog/view/template/checkout/payment_method.twig','974d73f62b98b4a562db1478abcea040cd47b7270675e368173a83b3e43bf7c6','a6de326763d2b27ca214328b6098513cc62974956b40872162e3b935e2117d57'),
    @('catalog/view/template/checkout/register.twig','5624640133e4ab0050958e2b3768fed406efbbcc70c554acdcde4a986cf4541b','354d390457dbfb056e5243defe59f4ff587cd690c657e04b853549c6c8cddd20'),
    @('catalog/view/template/checkout/shipping_address.twig','b16eddbc3916f4331d1452e9a82a182f8c606aa137554c107fc3c2092f9528a6','627047b08f77597aa7eac0224cc0a55b0c8e9f9d3eecb7d6d23a1be9b2380d2a'),
    @('catalog/view/template/checkout/shipping_method.twig','21c4f1982f357e428f3a6c843c6cd42ebedce0dd2a1307ec1128c6833268f1db','dbaa962422edf8e9b5d78a28780f0762153bd056970ee63dd8653ac962f37adc'),
    @('extension/PintaNovaPoshtaCod/catalog/controller/payment/pinta_nova_poshta_cod.php','67b541503a32fa9621cb65698f387f65aea0de47755a3a2210b011c5ca0b1b41','ab6ee3f6a61194bf5002389b3419a5e4ae5ead65d4a85f546fe274c4876578d2'),
    @('extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig','d4421dfe312771b15213b5e65306a03424a5462f03952ca014d359a816c4dc08','65d527db2f1a821b520ab80c0be6bf5c600e369268603de10c6dce136d7b94d6'),
    @('extension/hutko/catalog/controller/payment/hutko.php','1bb8fe3deec5dc843c4a93d45cc4ac3c23e7cef620b96bb8f032de999baf6b3c','84c5683b034a7517cab97f3c82df9b7d0745ba5e1885c757630b779f252f57af'),
    @('extension/mono_chast/catalog/controller/payment/mono_chast.php','c0cfbb4540c0fb123f2ba31d346b919b5d55300eeaa7b5e45046bf87a153992f','6c53f4129166dfeb9c8654b33edc99a663925f61d58b0a6cfed700dac76bd2ac'),
    @('extension/opencart/catalog/controller/payment/bank_transfer.php','f3f573881906c9397c575749d16771b029ec0ef95301d9bef9ca5ab9a1303462','34042f46f2be7821be463f75cf23b04c15b1ce784d9eb244666eae8403b4d5d0'),
    @('extension/opencart/catalog/controller/payment/cheque.php','6dd4abd4a7009db8d784d05852f7818bb4feb0140befbe2a0c6f03f5188cc867','d72668982c2b16891bf27e949ea9a2120d67eb4d8c9f65f0d760c7f2d71ccc83'),
    @('extension/opencart/catalog/controller/payment/cod.php','be6ce61041d8dc8afd1ef488ece31b5a9e568b52ab11165720ccc479c01cd0da','e09dec0344f8dde205402a2904d3ca78d4c25ea2ec4d01ed791b999e1af511af'),
    @('extension/opencart/catalog/controller/payment/free_checkout.php','ce9ea349262195f52b8a98c3da774bef7ceeefae6e3c9567885065b5b3f96630','e235cacd7e7b3aa9e9c3a459c89df3cdf9ada928431a9577b6f4c301f89e842d'),
    @('extension/pumb_credit/catalog/controller/payment/pumb_credit.php','951dcf43a4f4c2712b1f1a124fbac08ed80b57879a5f9bc91ab5cd9bf80a41dd','9cdcfccd5cd5189de44af2d01bd7c276eb0918aaf9d1ac79275e3c23b32e4885'),
    @('system/library/booster_order_notification_guard.php','absent','5a9c0ad9840b3f420e2aaa11cc9c736894548ac795d986d5b7b1d18a1ca3832d'),
    @('extension/telegram_notify/catalog/controller/event/telegram.php','119ee3340a9797b30f1545c68d2539ed50ce85d52bc1ce8ce1bd810430c56461','d46a49ea556fadc7a901726c12c3d6ea215b73a4a7a882125eb09fea716fd1ee'),
    @('catalog/model/checkout/order.php','0324c221cb75cb1aaa04d8b28be5865b295aff3b727139791e03413342c3a800','2ed58b2d537506e47b01dfc3232f3add75e620194f6089f1fc8e9d6729dabe46')
)
if ($Spec.Count -ne 26) { Stop-Draft 'knownspec table must contain exactly 26 rows' }
foreach ($r in $Spec) {
    if ($r.Count -ne 3) { Stop-Draft 'knownspec row shape' }
    if ($r[0] -cnotmatch '^[A-Za-z0-9_][A-Za-z0-9_./-]*$' -or $r[0].Contains('..')) { Stop-Draft "unsafe knownspec path $($r[0])" }
    if ($r[1] -cne 'absent') { Assert-Sha $r[1] 'knownspec before' }
    Assert-Sha $r[2] 'knownspec after'
}
foreach ($p in @($ExpectedHome,$ExpectedPhp,$ExpectedDirStorage,$ExpectedDirCache,$BackupRoot)) { Assert-AbsUnixPath $p }
if (-not $ExpectedDirCache.StartsWith($ExpectedDirStorage,[System.StringComparison]::Ordinal)) { Stop-Draft 'pinned DIR_CACHE not under pinned DIR_STORAGE' }
Assert-Sha $AcceptedPreflightSha 'accepted preflight receipt'

# =========================================================== APPENDIX BASH
# Static single-quoted templates. Placeholders are replaced only with pinned or
# PS-validated values; an unresolved placeholder stops the script.

$BashPins = @'
umask 077
ACC009_PIN_HOME="__PIN_HOME__"
ACC009_PIN_STORAGE="__PIN_STORAGE__"
ACC009_PIN_CACHE="__PIN_CACHE__"
ACC009_MAX_TEMPLATE_FILES="__MAX_TEMPLATE__"
if [ "$home" != "$ACC009_PIN_HOME" ] || [ "$pub" != "$ACC009_PIN_HOME/public_html" ]; then echo "FAIL resolved home/webroot differ from pins"; exit 40; fi
echo "HOME_PINS=match"
'@

$BashCanonHelper = @'
# ---- BEGIN acc009 pure helper acc009_canon_dir <abs dir>: no side effects ----
acc009_canon_dir() {
  local p="${1-}" rest="" part="" acc=""
  case "$p" in /?*) ;; *) return 1;; esac
  case "$p" in */|*//*|*/./*|*/.|*/../*|*/..|*[!A-Za-z0-9._/-]*) return 1;; esac
  if [ "${#p}" -gt 256 ]; then return 1; fi
  rest="${p#/}"
  while [ -n "$rest" ]; do
    part="${rest%%/*}"
    if [ "$part" = "$rest" ]; then rest=""; else rest="${rest#*/}"; fi
    acc="$acc/$part"
    if [ -L "$acc" ] || [ ! -d "$acc" ]; then return 1; fi
  done
  [ "$(readlink -f -- "$p")" = "$p" ] || return 1
  return 0
}
# ---- END acc009_canon_dir ----
'@

$BashCacheHelper = @'
# ---- BEGIN acc009 pure helper acc009_template_clean <storage> <cache> ----
# Caller sets outer pins ACC009_PIN_STORAGE / ACC009_PIN_CACHE / ACC009_MAX_TEMPLATE_FILES
# (fictional values in local tests). Deletes ONLY regular *.php files below <cache>/template
# after a complete inventory; never mkdir; refuses links/special entries; keeps directories,
# non-php files and every other cache area. No hash-name guessing, no modification cleanup.
acc009_template_clean() {
  local s c s0 c0 t0 inv lst ln ty p n_dir=0 n_php=0 n_keep=0 n_list=0 n_del=0 n_gone=0
  if [ "$#" -ne 2 ]; then echo "FAIL cache helper argument count"; return 70; fi
  s="$1"; c="$2"
  if [ -z "${ACC009_PIN_STORAGE:-}" ] || [ -z "${ACC009_PIN_CACHE:-}" ] || [ -z "${ACC009_MAX_TEMPLATE_FILES:-}" ]; then echo "FAIL cache helper outer pins unset"; return 70; fi
  case "$ACC009_MAX_TEMPLATE_FILES" in ''|*[!0-9]*) echo "FAIL cache helper bound not numeric"; return 70;; esac
  if [ "$s" != "$ACC009_PIN_STORAGE" ] || [ "$c" != "$ACC009_PIN_CACHE" ]; then echo "FAIL cache helper arguments differ from outer pins"; return 71; fi
  s0="${s%/}"; c0="${c%/}"; t0="$c0/template"
  case "$c0" in "$s0"/?*) ;; *) echo "FAIL cache dir not under storage dir"; return 71;; esac
  acc009_canon_dir "$s0" || { echo "FAIL storage not exact canonical non-link directory"; return 72; }
  acc009_canon_dir "$c0" || { echo "FAIL cache not exact canonical non-link directory"; return 72; }
  if [ -L "$t0" ]; then echo "FAIL template path is link"; return 73; fi
  if [ ! -e "$t0" ]; then echo "TEMPLATE_CLEAN_STATE=absent_nothing_deleted"; return 0; fi
  acc009_canon_dir "$t0" || { echo "FAIL template not exact canonical non-link directory"; return 73; }
  inv=$(find "$t0" -mindepth 1 -printf '%y %p\n') || { echo "FAIL template inventory"; return 74; }
  while IFS= read -r ln; do
    [ -z "$ln" ] && continue
    ty="${ln%% *}"; p="${ln#* }"
    case "$p" in "$t0"/?*) ;; *) echo "FAIL inventory entry outside template dir"; return 75;; esac
    case "$p" in *[!A-Za-z0-9._/-]*|*//*|*/./*|*/.|*/../*|*/..) echo "FAIL unsupported template entry name (refused before deletion)"; return 75;; esac
    case "$ty" in
      d) n_dir=$((n_dir+1));;
      f) case "$p" in *.php) n_php=$((n_php+1));; *) n_keep=$((n_keep+1));; esac;;
      *) echo "FAIL unsupported template entry type $ty (link/special refused before deletion)"; return 76;;
    esac
  done <<< "$inv"
  echo "TEMPLATE_INVENTORY=dirs:$n_dir,php:$n_php,other_regular_kept:$n_keep"
  if [ $((n_dir+n_php+n_keep)) -gt "$ACC009_MAX_TEMPLATE_FILES" ]; then echo "FAIL template entry count above bound $ACC009_MAX_TEMPLATE_FILES"; return 77; fi
  acc009_canon_dir "$t0" || { echo "FAIL template changed before deletion"; return 78; }
  lst=$(find "$t0" -mindepth 1 -type f -name '*.php' -print) || { echo "FAIL template deletion listing"; return 78; }
  while IFS= read -r p; do [ -n "$p" ] && n_list=$((n_list+1)); done <<< "$lst"
  if [ "$n_list" -gt "$ACC009_MAX_TEMPLATE_FILES" ]; then echo "FAIL deletion list above bound"; return 77; fi
  while IFS= read -r p; do
    [ -z "$p" ] && continue
    case "$p" in "$t0"/?*.php) ;; *) echo "FAIL deletion entry outside template dir"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 79;; esac
    case "$p" in *[!A-Za-z0-9._/-]*|*//*|*/./*|*/../*) echo "FAIL unsupported deletion entry name"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 79;; esac
    acc009_canon_dir "${p%/*}" || { echo "FAIL deletion parent not canonical non-link directory"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 79; }
    if [ -L "$p" ]; then echo "FAIL deletion entry became link"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 79; fi
    if [ ! -e "$p" ]; then n_gone=$((n_gone+1)); continue; fi
    if [ ! -f "$p" ]; then echo "FAIL deletion entry not regular"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 79; fi
    rm -f -- "$p" || { echo "FAIL delete"; echo "TEMPLATE_DELETED_BEFORE_FAILURE=$n_del"; return 80; }
    n_del=$((n_del+1))
  done <<< "$lst"
  echo "TEMPLATE_DELETED=$n_del"
  echo "TEMPLATE_VANISHED_CONCURRENTLY=$n_gone"
  echo "TEMPLATE_CLEAN_STATE=cleared"
  return 0
}
# ---- END acc009_template_clean ----
'@

$BashRunnerGate = @'
runner="$HOME/private/booster-patches/__FILENAME__"
if [ -L "$runner" ]; then echo "FAIL runner is link"; exit 51; fi
if [ ! -f "$runner" ]; then echo "FAIL runner missing (not executed; re-stage only via the separate Upload then Verify steps)"; exit 50; fi
if [ "$(readlink -f -- "$runner")" != "$stage/__FILENAME__" ]; then echo "FAIL runner not in canonical private stage"; exit 51; fi
got=$(sha256sum "$runner" 2>/dev/null | awk '{print $1}')
if [ -z "$got" ]; then echo "FAIL runner hash unavailable"; exit 58; fi
echo "RUNNER_SHA=$got"
if [ "$got" != "__PINNED_SHA__" ]; then echo "FAIL runner hash mismatch (runner not executed)"; exit 59; fi
'@

$BashPhpGate = @'
phpbin=$(command -v php 2>/dev/null)
if [ -z "$phpbin" ]; then echo "FAIL no php on PATH"; exit 52; fi
if [ "$phpbin" != "__EXPECTED_PHP__" ]; then echo "FAIL php path mismatch: $phpbin"; exit 53; fi
if [ ! -x "$phpbin" ]; then echo "FAIL php not executable"; exit 54; fi
ver=$("$phpbin" -r 'echo PHP_VERSION;' </dev/null 2>/dev/null)
case "$ver" in __PHP_VER_GLOB__) ;; *) echo "FAIL php version not admitted: $ver"; exit 55;; esac
echo "PHP_BIN=$phpbin"
echo "PHP_VERSION=$ver"
'@

$BashConstGate = @'
cfg="$pub/config.php"
if [ -L "$cfg" ] || [ ! -f "$cfg" ]; then echo "FAIL catalog config.php missing or link"; exit 60; fi
cd "$pub" || { echo "FAIL cd webroot"; exit 60; }
consts=$("$phpbin" -r 'ob_start(); require $argv[1]; ob_end_clean(); foreach (array("DIR_STORAGE", "DIR_CACHE") as $c) { echo $c, "=", (defined($c) ? constant($c) : "UNDEFINED"), "\n"; }' "$cfg" </dev/null) || { echo "FAIL runtime constant evaluation"; exit 61; }
ds=""; dc=""; n=0
while IFS= read -r ln; do
  n=$((n+1))
  case "$ln" in
    DIR_STORAGE=*) ds="${ln#DIR_STORAGE=}";;
    DIR_CACHE=*) dc="${ln#DIR_CACHE=}";;
    *) echo "FAIL unexpected constant evaluation row (not printed)"; exit 61;;
  esac
done <<< "$consts"
if [ "$n" -ne 2 ]; then echo "FAIL constant evaluation row count $n"; exit 61; fi
if [ "$ds" != "$ACC009_PIN_STORAGE" ] || [ "$dc" != "$ACC009_PIN_CACHE" ]; then echo "FAIL catalog DIR_STORAGE/DIR_CACHE differ from pins (values not printed)"; exit 62; fi
case "$dc" in "$ds"?*) ;; *) echo "FAIL DIR_CACHE not under DIR_STORAGE"; exit 62;; esac
acc009_canon_dir "${ds%/}" || { echo "FAIL DIR_STORAGE not exact canonical non-link directory"; exit 62; }
acc009_canon_dir "${dc%/}" || { echo "FAIL DIR_CACHE not exact canonical non-link directory"; exit 62; }
echo "CONST_PINS=match"
twig="$pub/system/library/template/twig.php"
if [ -L "$twig" ] || [ ! -f "$twig" ]; then echo "FAIL pinned twig.php missing or link"; exit 63; fi
tw=$(sha256sum "$twig" 2>/dev/null | awk '{print $1}')
echo "TWIG_SHA=$tw"
if [ "$tw" != "__PINNED_TWIG__" ]; then echo "FAIL twig.php sha differs from pin"; exit 63; fi
echo "CONST_GATE_DONE=ok"
'@

$BashApplyPre = @'
tp="${ACC009_PIN_CACHE%/}/template"
acc009_canon_dir "$tp" || { echo "FAIL cache template path not exact canonical non-link directory"; exit 65; }
echo "TEMPLATE_STATE=dir"
bb="$home/_patch_backups"
if [ -L "$bb" ]; then echo "FAIL backup root is link"; exit 67; fi
if [ -e "$bb" ]; then
  acc009_canon_dir "$bb" || { echo "FAIL backup root not exact canonical non-link directory"; exit 67; }
  echo "BACKUP_ROOT_STATE=dir"
else
  echo "BACKUP_ROOT_STATE=absent_runner_creates_0700"
fi
'@

$BashRollbackPre = @'
bb="$home/_patch_backups"
bk="$bb/__BACKUP_NAME__"
acc009_canon_dir "$bb" || { echo "FAIL backup root not exact canonical non-link directory"; exit 67; }
acc009_canon_dir "$bk" || { echo "FAIL named backup not exact canonical non-link directory"; exit 67; }
mf="$bk/manifest.json"
if [ -L "$mf" ] || [ ! -f "$mf" ]; then echo "FAIL backup manifest missing or link"; exit 67; fi
echo "BACKUP_DIR=$bk"
echo "BACKUP_MANIFEST_SHA=$(sha256sum "$mf" 2>/dev/null | awk '{print $1}')"
tp="${ACC009_PIN_CACHE%/}/template"
if [ -L "$tp" ]; then echo "FAIL cache template path is link"; exit 65; fi
if [ -d "$tp" ]; then
  acc009_canon_dir "$tp" || { echo "FAIL cache template path not canonical"; exit 65; }
  echo "TEMPLATE_STATE=dir"
elif [ -e "$tp" ]; then
  echo "FAIL cache template path not a directory"; exit 65
else
  echo "TEMPLATE_STATE=absent"
fi
'@

$BashNewTargetParents = @'
for rel in system/library catalog/view/javascript; do
  acc009_canon_dir "$pub/$rel" || { echo "FAIL new-target parent not exact canonical non-link directory: $rel"; exit 66; }
done
for rel in system/library/booster_checkout_readiness.php catalog/view/javascript/acc009-checkout.js system/library/booster_order_notification_guard.php; do
  t="$pub/$rel"
  if [ -L "$t" ]; then echo "FAIL new target is link: $rel"; exit 66; fi
  if [ -e "$t" ] && [ ! -f "$t" ]; then echo "FAIL new target not regular: $rel"; exit 66; fi
done
echo "NEW_TARGET_PARENTS=ok"
'@

$BashRun = @'
label="__RUN_LABEL__"
mode="__MODE_ARG__"
rdir="$pulls"
for f in "$rdir"/ACC-009-C1-H1_"$label"_*_stdout.*; do
  if [ -e "$f" ] || [ -L "$f" ]; then echo "FAIL prior $label runner receipt exists on host: $f (assessment required; no rerun)"; exit 64; fi
done
ts=$(date -u +%Y%m%dT%H%M%SZ)
out=$(mktemp "$rdir/ACC-009-C1-H1_${label}_${ts}_stdout.XXXXXXXXXX") || { echo "FAIL mktemp stdout receipt"; exit 56; }
err=$(mktemp "$rdir/ACC-009-C1-H1_${label}_${ts}_stderr.XXXXXXXXXX") || { echo "FAIL mktemp stderr receipt"; exit 56; }
chmod 600 "$out" "$err" || { echo "FAIL chmod receipts"; exit 56; }
echo "STDOUT_FILE=$out"
echo "STDERR_FILE=$err"
cd "$pub" || { echo "FAIL cd webroot"; exit 57; }
echo "HTTP_USER_AGENT=BoosterShop-ACC009-Runtime/1.0"
echo "RUN_MODE=$mode"
trap '' HUP PIPE
echo "RUNNER_INVOKED=1"
"$phpbin" -d user_agent=BoosterShop-ACC009-Runtime/1.0 "$runner" "$mode" </dev/null >"$out" 2>"$err"
rc=$?
echo "CLI_EXIT=$rc"
echo "UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
if [ -L "$runner" ]; then echo "RUNNER_POST_STATE=link"; elif [ -e "$runner" ]; then echo "RUNNER_POST_STATE=present"; else echo "RUNNER_POST_STATE=absent"; fi
echo "---RUN-STDOUT-BEGIN---"
cat "$out"
echo ""
echo "---RUN-STDOUT-END---"
echo "---RUN-STDERR-BEGIN---"
cat "$err"
echo ""
echo "---RUN-STDERR-END---"
echo "RUN_WRAPPER_DONE=ok"
'@

$BashCleanupCall = @'
echo "TEMPLATE_PATH=${ACC009_PIN_CACHE%/}/template/"
acc009_template_clean "$ds" "$dc" && echo "CACHE_CLEANUP_DONE=ok"
echo "CLEANUP_WRAPPER_DONE=ok"
'@

# ====================================================== APPENDIX HELPERS
function Get-Lines([string]$text) { return @($text -split "`n" | ForEach-Object { $_.TrimEnd("`r") }) }
function Get-TagList([string]$text,[string]$key) {
    foreach ($l in (Get-Lines $text)) { if ($l.StartsWith($key + '=',[System.StringComparison]::Ordinal)) { $l.Substring($key.Length + 1) } }
}
function Get-PrefixIdx([string[]]$lines,[string]$prefix) {
    for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i].StartsWith($prefix,[System.StringComparison]::Ordinal)) { $i } }
}
function Get-ExactIdx([string[]]$lines,[string]$exact) {
    $ix = @(for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i] -ceq $exact) { $i } })
    if ($ix.Count -ne 1) { Stop-Draft "Expected exactly one whole line '$exact', found $($ix.Count)" }
    return $ix[0]
}
function Get-LastIdx([string[]]$lines) {
    $last = -1
    for ($i = 0; $i -lt $lines.Count; $i++) { if ($lines[$i].Length -gt 0) { $last = $i } }
    return $last
}
function Get-ReceiptRx([string]$label) {
    return '^' + [regex]::Escape($TaskId + '_' + $label + '_') + '[0-9]{8}T[0-9]{9}Z_' + $PinnedSha.Substring(0,8) + '_[0-9a-f]{8}[.]log$'
}

function Expand-Bash([string]$b,[hashtable]$extra) {
    $map = [ordered]@{ '__FILENAME__' = $runnerName; '__PINNED_SHA__' = $PinnedSha; '__EXPECTED_PHP__' = $ExpectedPhp; '__PIN_HOME__' = $ExpectedHome; '__PIN_STORAGE__' = $ExpectedDirStorage; '__PIN_CACHE__' = $ExpectedDirCache; '__PINNED_TWIG__' = $PinnedTwigSha; '__MAX_TEMPLATE__' = [string]$MaxTemplateEntries }
    foreach ($k in $extra.Keys) { $map[$k] = [string]$extra[$k] }
    foreach ($k in $map.Keys) { $b = $b.Replace([string]$k,[string]$map[$k]) }
    if ($b -cmatch '__[A-Z][A-Z_]*__') { Stop-Draft "Unresolved bash placeholder $($Matches[0])" }
    return $b
}
function Build-RunBash([string]$label,[string]$modeArg,[string]$pre,[string]$phpGlob,[string]$name) {
    if (@('apply','rollback') -cnotcontains $label) { Stop-Draft "bad run label $label" }
    if ($label -ceq 'apply' -and $modeArg -cne '--apply') { Stop-Draft 'bad apply mode' }
    if ($label -ceq 'rollback' -and ($name -cnotmatch $BackupNameRx -or $modeArg -cne ('--rollback=' + $name))) { Stop-Draft 'bad rollback mode/name' }
    if (@('8.0.30','8.0.*') -cnotcontains $phpGlob) { Stop-Draft 'bad php version glob' }
    $x = @{ '__RUN_LABEL__' = $label; '__MODE_ARG__' = $modeArg; '__PHP_VER_GLOB__' = $phpGlob }
    if ($label -ceq 'rollback') { $x['__BACKUP_NAME__'] = $name }
    $b = $BashAdmit + "`n" + $BashPins + "`n" + $BashCanonHelper + "`n" + $BashRunnerGate + "`n" + $BashPhpGate + "`n" + $BashConstGate + "`n" + $pre + "`n" + $BashNewTargetParents + "`n" + $BashRun
    return Expand-Bash $b $x
}

function Assert-SavedRun([bool]$needPreflight) {
    $s = Load-State
    if ($null -eq $s) { Stop-Draft 'No saved state. The separate -Step Verify (and, for Apply, the accepted Preflight) must exist first.' }
    $want = [ordered]@{ runner_sha = $PinnedSha; runner_name = $runnerName; php_bin = $ExpectedPhp; dir_storage = $ExpectedDirStorage; dir_cache = $ExpectedDirCache }
    foreach ($k in $want.Keys) {
        if ([string](State-Prop $s $k) -cne $want[$k]) { Stop-Draft "Saved state '$k' differs from the pinned value; no execution." }
    }
    if ((State-Prop $s 'verify_done') -ne $true) { Stop-Draft 'Saved state has no completed Verify; no execution.' }
    $ver = [string](State-Prop $s 'php_version')
    if (-not $ver.StartsWith('8.0.',[System.StringComparison]::Ordinal)) { Stop-Draft "Saved PHP version '$ver' is not 8.0.x" }
    if (-not $needPreflight) { Note 'saved Verify state bound to pinned runner/PHP/constants'; return }
    if ($ver -cne $ExpectedPhpVer) { Stop-Draft "Saved PHP version '$ver' != accepted $ExpectedPhpVer" }
    if ((State-Prop $s 'preflight_done') -ne $true) { Stop-Draft 'Saved state has no Preflight; no execution.' }
    $acc = Join-Path $StateDir $AcceptedPreflightLog
    if ([string](State-Prop $s 'local_receipt') -cne $acc) { Stop-Draft 'Saved Preflight is not the accepted 2026-10-21T07:24Z receipt; a newer Preflight needs its own assessment. No execution.' }
    if (-not (Test-Path -LiteralPath $acc -PathType Leaf)) { Stop-Draft "Accepted Preflight receipt missing: $acc" }
    if ((Get-Item -LiteralPath $acc -Force).Attributes -band [System.IO.FileAttributes]::ReparsePoint) { Stop-Draft 'Accepted Preflight receipt is a reparse point' }
    $h = (Get-FileHash -LiteralPath $acc -Algorithm SHA256).Hash.ToLower()
    if ($h -cne $AcceptedPreflightSha) { Stop-Draft "Accepted Preflight receipt sha $h != pinned $AcceptedPreflightSha" }
    $ll = @(Get-Lines ([System.IO.File]::ReadAllText($acc)))
    foreach ($m in @('CLI_EXIT=0','PREFLIGHT_WRAPPER_DONE=ok',$DoneLine,$SourcePreflightLine,('RUNNER_SHA=' + $PinnedSha),('HTTP_USER_AGENT=' + $RunUA))) {
        $n = @($ll | Where-Object { $_ -ceq $m }).Count
        if ($n -ne 1) { Stop-Draft "Accepted Preflight receipt marker '$m' found $n times (expected 1)" }
    }
    Note "accepted Preflight receipt bound: $acc sha256=$h"
}

function Assert-NoPriorLocalRun([string]$label) {
    Ensure-StateDir
    $rx = Get-ReceiptRx $label
    foreach ($f in @(Get-ChildItem -LiteralPath $StateDir -File -Force)) {
        if ($f.Name -cnotmatch $rx) { continue }
        $l = @(Get-Lines ([System.IO.File]::ReadAllText($f.FullName)))
        $inv = @($l | Where-Object { $_ -ceq 'RUNNER_INVOKED=1' }).Count
        $guard = @($l | Where-Object { $_.StartsWith('FAIL',[System.StringComparison]::Ordinal) }).Count
        if ($inv -gt 0 -or $guard -eq 0) { Stop-Draft "Prior $label receipt $($f.FullName) shows a runner invocation or an unconfirmed outcome; assessment required, no rerun by this packet." }
    }
}

function Assert-BackupFromApplyReceipt([string]$bp) {
    Ensure-StateDir
    $rx = Get-ReceiptRx 'apply'
    $want = 'backup_path=' + $bp
    $hits = 0
    foreach ($f in @(Get-ChildItem -LiteralPath $StateDir -File -Force)) {
        if ($f.Name -cnotmatch $rx) { continue }
        if ($f.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { continue }
        $hits += @(@(Get-Lines ([System.IO.File]::ReadAllText($f.FullName))) | Where-Object { $_ -ceq $want }).Count
    }
    if ($hits -ne 1) { Stop-Draft "Backup $bp must appear exactly once as a whole backup_path row in captured Apply receipts (found $hits). No latest/glob selection; assessment required." }
    Note "rollback backup bound to a captured Apply receipt row: $bp"
}

function Get-BackupNameFromPath([string]$bp) {
    $rx = '^' + [regex]::Escape($BackupRoot + '/') + '(ACC-009-C1-H1-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8})$'
    if ($bp -cmatch $rx) { return $Matches[1] }
    Stop-Draft "backup_path '$bp' is not an exact unique name under the pinned private root $BackupRoot"
}

# Written immediately after Save-Receipt and BEFORE any assertion: exact backup_path rows as data.
function Save-RunRecord($res,[string]$label,[string]$receipt) {
    $bps = @(Get-TagList $res.StdOut 'backup_path')
    try {
        Ensure-StateDir
        $rec = [ordered]@{
            task = $TaskId; step = $Step; label = $label; runner_sha = $PinnedSha; receipt = $receipt
            ssh_or_wrapper_exit = $res.ExitCode
            runner_invoked_rows = @((Get-Lines $res.StdOut) | Where-Object { $_ -ceq 'RUNNER_INVOKED=1' }).Count
            cli_exit_rows = @(Get-TagList $res.StdOut 'CLI_EXIT')
            backup_path_rows = $bps
            remote_stdout_rows = @(Get-TagList $res.StdOut 'STDOUT_FILE')
            remote_stderr_rows = @(Get-TagList $res.StdOut 'STDERR_FILE')
            utc = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
        }
        $name = '{0}_{1}-record_{2}_{3}_{4}.json' -f $TaskId,$label,(Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssfffZ'),$PinnedSha.Substring(0,8),([guid]::NewGuid().ToString('N').Substring(0,8))
        $path = Join-Path $StateDir $name
        $bytes = (New-Object System.Text.UTF8Encoding($false)).GetBytes(($rec | ConvertTo-Json -Depth 4))
        $fs = New-Object System.IO.FileStream($path,[System.IO.FileMode]::CreateNew,[System.IO.FileAccess]::Write)
        try { $fs.Write($bytes,0,$bytes.Length) } finally { $fs.Dispose() }
        Note "run record (backup_path data, no glob): $path"
    } catch {
        Write-Host "[warn] run record not written ($($_.Exception.Message)); complete receipt $receipt still holds every row" -ForegroundColor Yellow
    }
    foreach ($b in $bps) { Write-Host "[RETAIN] backup_path=$b" -ForegroundColor Yellow }
    return $bps
}

function Read-Run($res,[string]$modeArg,[hashtable]$extraWant,[string]$notice) {
    $lines = @(Get-Lines $res.StdOut)
    $invoked = @($lines | Where-Object { $_ -ceq 'RUNNER_INVOKED=1' }).Count
    $fails = @($lines | Where-Object { $_.StartsWith('FAIL',[System.StringComparison]::Ordinal) })
    if ($invoked -eq 0) {
        if ($fails.Count -gt 0) { Stop-Draft ('Guard refused BEFORE runner invocation (no production write by this invocation). wrapper exit=' + $res.ExitCode + ' : ' + ($fails -join ' | ')) }
        Stop-Draft ('TRANSPORT_OR_WRAPPER_FAILURE: runner invocation NOT confirmed either way (wrapper/transport exit=' + $res.ExitCode + '). Before any further step the owner inspects ~/private/booster-pulls for ACC-009-C1-H1_apply_* / ACC-009-C1-H1_rollback_* receipt files; do_not_reapply.')
    }
    if ($invoked -ne 1) { Stop-Draft "RUNNER_INVOKED marker count $invoked (expected 1)" }
    $script:RetainNotice = $notice
    $wrap = @(Get-TagList $res.StdOut 'RUN_WRAPPER_DONE')
    if ($res.ExitCode -ne 0 -or $fails.Count -gt 0 -or $wrap.Count -ne 1 -or $wrap[0] -cne 'ok') {
        Stop-Draft ('TRANSPORT_OR_WRAPPER_FAILURE_AFTER_RUNNER_INVOCATION wrapper/transport exit=' + $res.ExitCode + ' cli_exit_rows=[' + (@(Get-TagList $res.StdOut 'CLI_EXIT') -join ',') + ']; production source state UNKNOWN until the remote receipt files are assessed. ' + ($fails -join ' | '))
    }
    $want = [ordered]@{ ADMIT_DONE = 'ok'; HOME_PINS = 'match'; RESOLVED_HOME = $ExpectedHome; RESOLVED_PUB = ($ExpectedHome + '/public_html'); RUNNER_SHA = $PinnedSha; PHP_BIN = $ExpectedPhp; CONST_PINS = 'match'; TWIG_SHA = $PinnedTwigSha; CONST_GATE_DONE = 'ok'; NEW_TARGET_PARENTS = 'ok'; HTTP_USER_AGENT = $RunUA; RUN_MODE = $modeArg }
    foreach ($k in $extraWant.Keys) { $want[$k] = $extraWant[$k] }
    foreach ($k in $want.Keys) {
        $v = Get-TaggedValue $res.StdOut $k
        if ($v -cne $want[$k]) { Stop-Draft "Wrapper row $k='$v' differs from expected '$($want[$k])'" }
    }
    $pv = Get-TaggedValue $res.StdOut 'PHP_VERSION'
    if ($null -eq $pv -or -not $pv.StartsWith('8.0.',[System.StringComparison]::Ordinal)) { Stop-Draft "PHP_VERSION row '$pv' not 8.0.x" }
    $cli = Get-TaggedValue $res.StdOut 'CLI_EXIT'
    if ($cli -cnotmatch '^[0-9]{1,3}$') { Stop-Draft "CLI_EXIT row missing/malformed: '$cli'" }
    $post = Get-TaggedValue $res.StdOut 'RUNNER_POST_STATE'
    if (@('absent','present','link') -cnotcontains $post) { Stop-Draft "RUNNER_POST_STATE missing/unexpected: '$post'" }
    $outFile = Get-TaggedValue $res.StdOut 'STDOUT_FILE'
    $errFile = Get-TaggedValue $res.StdOut 'STDERR_FILE'
    Assert-AbsUnixPath $outFile
    Assert-AbsUnixPath $errFile
    $outBlk = Get-Block $res.StdOut '---RUN-STDOUT-BEGIN---' '---RUN-STDOUT-END---'
    $errBlk = Get-Block $res.StdOut '---RUN-STDERR-BEGIN---' '---RUN-STDERR-END---'
    $bad = @()
    foreach ($t in (Get-Lines $errBlk)) {
        if ($t.Length -eq 0 -or $t -ceq $KnownMysqlWarning) { continue }
        $bad += $t
    }
    return [pscustomobject]@{ Cli = $cli; Post = $post; Lines = @(Get-Lines $outBlk); BadErr = $bad; OutFile = $outFile; ErrFile = $errFile }
}

function Format-TargetRow([int]$i,[string]$bk,[string]$sg,[string]$chg,[string]$act,[string]$cb,[string]$ca) {
    $r = $Spec[$i]
    return ('target={0} sha256_before={1} backup={2} syntax_gate={3} sha256_after={4} changed_files={5} action={6} current_before={7} current_after={8}' -f $r[0],$r[1],$bk,$sg,$r[2],$chg,$act,$cb,$ca)
}

function Test-ApplyStdout([string[]]$lines,[string]$bp) {
    foreach ($bad in @('error=','restored=','restored_file','already_applied=','rollback')) {
        if (@(Get-PrefixIdx $lines $bad).Count -gt 0) { Stop-Draft "Apply stdout contains unexpected '$bad' row" }
    }
    $null = Get-ExactIdx $lines 'task=ACC-009-C1-H1'
    $null = Get-ExactIdx $lines 'mode=--apply'
    $probe = Get-ExactIdx $lines 'web_probe_cleanup=ok'
    $seal  = Get-ExactIdx $lines 'seal_headers=request_and_response_ok'
    $plint = Get-ExactIdx $lines 'payload_php_lint=ok'
    $bpi   = Get-ExactIdx $lines ('backup_path=' + $bp)
    $t = @(Get-PrefixIdx $lines 'target=')
    if ($t.Count -ne 26) { Stop-Draft "Apply stdout has $($t.Count) target= rows (expected 26)" }
    if (-not (($probe -lt $bpi) -and ($seal -lt $bpi) -and ($plint -lt $bpi) -and ($bpi -lt $t[0]))) { Stop-Draft 'Apply stdout order: web/seal/payload gates and backup_path must precede the first target write row' }
    for ($i = 0; $i -lt 26; $i++) {
        $r = $Spec[$i]
        $bk = if ($r[1] -ceq 'absent') { 'none' } else { '{0}/{1}.source' -f $bp,$i }
        $sg = if ($r[0].EndsWith('.php',[System.StringComparison]::Ordinal)) { 'php_lint_ok' } else { 'not_php' }
        $exp = Format-TargetRow $i $bk $sg '1' 'applied' $r[1] $r[2]
        if ($lines[$t[$i]] -cne $exp) { Stop-Draft "Apply target row $i differs from knownspec: $($lines[$t[$i]])" }
    }
    $bar = Get-ExactIdx $lines $BarrierLine
    if (-not (($t[22] -lt $bar) -and ($bar -lt $t[23]))) { Stop-Draft 'composition barrier not between C1 row 22 and H1 row 23' }
    $cf = @(Get-PrefixIdx $lines 'changed_file=')
    if ($cf.Count -ne 26 -or $cf[0] -lt $t[25]) { Stop-Draft "changed_file rows: $($cf.Count) (expected 26 after the last target row)" }
    for ($i = 0; $i -lt 26; $i++) { if ($lines[$cf[$i]] -cne ('changed_file=' + $Spec[$i][0])) { Stop-Draft "changed_file row $i not in spec order" } }
    $lint = Get-ExactIdx $lines 'host_php_lint=ok'
    $ok   = Get-ExactIdx $lines 'apply=ok'
    $sd   = Get-ExactIdx $lines 'self_deleted=yes'
    $dn   = Get-ExactIdx $lines $DoneLine
    if (-not (($cf[25] -lt $lint) -and ($lint -lt $ok) -and ($ok -lt $sd) -and ($sd -lt $dn) -and ($dn -eq (Get-LastIdx $lines)))) { Stop-Draft 'Apply stdout tail order must be changed_file x26, host_php_lint=ok, apply=ok, self_deleted=yes, done=ok (last)' }
}

function Test-RollbackStdout([string[]]$lines,[string]$bp) {
    foreach ($bad in @('error=','restored=','restored_file','already_applied=','changed_file=','apply=','composition_barrier=')) {
        if (@(Get-PrefixIdx $lines $bad).Count -gt 0) { Stop-Draft "Rollback stdout contains unexpected '$bad' row" }
    }
    $null = Get-ExactIdx $lines 'task=ACC-009-C1-H1'
    $null = Get-ExactIdx $lines ('mode=--rollback=' + $BackupName)
    $pre = Get-ExactIdx $lines 'rollback_preflight=all_targets_and_required_backups_verified'
    $bpi = Get-ExactIdx $lines ('backup_path=' + $bp)
    $t = @(Get-PrefixIdx $lines 'target=')
    if ($t.Count -ne 26) { Stop-Draft "Rollback stdout has $($t.Count) target= rows (expected 26)" }
    if (-not (($pre -lt $bpi) -and ($bpi -lt $t[0]))) { Stop-Draft 'Rollback stdout order: rollback_preflight then backup_path must precede the first target row' }
    $restored = 0
    for ($k = 0; $k -lt 26; $k++) {
        $i = 25 - $k; $r = $Spec[$i]; $created = ($r[1] -ceq 'absent')
        $bk = if ($created) { 'none' } else { '{0}/{1}.source' -f $bp,$i }
        $sg = if ((-not $created) -and $r[0].EndsWith('.php',[System.StringComparison]::Ordinal)) { 'php_lint_ok' } else { 'not_php' }
        $line = $lines[$t[$k]]
        if ($line -ceq (Format-TargetRow $i $bk $sg '1' 'restored' $r[2] $r[1])) { $restored++ }
        elseif ($line -ceq (Format-TargetRow $i 'none' 'not_written' '0' 'already_before' $r[1] $r[1])) { }
        else { Stop-Draft "Rollback row $k (spec index $i) is neither exact restored nor already_before: $line" }
    }
    $rb   = Get-ExactIdx $lines 'rollback=ok'
    $lint = Get-ExactIdx $lines 'host_php_lint=ok'
    $sd   = Get-ExactIdx $lines 'self_deleted=yes'
    $dn   = Get-ExactIdx $lines $DoneLine
    if (-not (($t[25] -lt $rb) -and ($rb -lt $lint) -and ($lint -lt $sd) -and ($sd -lt $dn) -and ($dn -eq (Get-LastIdx $lines)))) { Stop-Draft 'Rollback stdout tail order must be rollback=ok, host_php_lint=ok, self_deleted=yes, done=ok (last)' }
    return $restored
}

# Template cleanup: separate ssh, only after complete source receipt gates. Never Stop-Draft here so a
# failure is always reported as SOURCE_CHANGED_CACHE_FAILED by the caller.
function Invoke-TemplateCleanup([string]$label) {
    $b = Expand-Bash ($BashAdmit + "`n" + $BashPins + "`n" + $BashCanonHelper + "`n" + $BashCacheHelper + "`n" + $BashPhpGate + "`n" + $BashConstGate + "`n" + $BashCleanupCall) @{ '__PHP_VER_GLOB__' = '8.0.*' }
    Note 'owner ssh -> re-admit catalog DIR_CACHE (==pin, canonical, non-link) and exact template dir; full inventory first, refuse links/unsupported entries, bounded count; delete only regular *.php under it; directories and other cache areas kept'
    $r = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $b
    $rc = Save-Receipt $r $label
    Write-Host $r.StdOut
    $why = @()
    if ($r.ExitCode -ne 0) { $why += "wrapper/transport exit=$($r.ExitCode)" }
    $why += @((Get-Lines $r.StdOut) | Where-Object { $_.StartsWith('FAIL',[System.StringComparison]::Ordinal) })
    $want = [ordered]@{ ADMIT_DONE = 'ok'; HOME_PINS = 'match'; PHP_BIN = $ExpectedPhp; CONST_PINS = 'match'; TWIG_SHA = $PinnedTwigSha; CONST_GATE_DONE = 'ok'; TEMPLATE_PATH = ($ExpectedDirCache + 'template/'); CACHE_CLEANUP_DONE = 'ok'; CLEANUP_WRAPPER_DONE = 'ok' }
    foreach ($k in $want.Keys) {
        $v = @(Get-TagList $r.StdOut $k)
        if ($v.Count -ne 1 -or $v[0] -cne $want[$k]) { $why += "$k row missing/unexpected" }
    }
    $st = @(Get-TagList $r.StdOut 'TEMPLATE_CLEAN_STATE')
    $dl = @(Get-TagList $r.StdOut 'TEMPLATE_DELETED')
    $state = ''; $del = ''
    if ($st.Count -eq 1) { $state = $st[0] }
    if ($dl.Count -eq 1) { $del = $dl[0] }
    if ($state -ceq 'cleared') { if ($del -cnotmatch '^[0-9]+$') { $why += 'TEMPLATE_DELETED count missing' } }
    elseif ($state -ceq 'absent_nothing_deleted') { $del = '0 (template dir absent; nothing created or deleted)' }
    else { $why += "TEMPLATE_CLEAN_STATE missing/unexpected: $state" }
    return [pscustomobject]@{ Ok = ($why.Count -eq 0); Why = ($why -join ' | '); State = $state; Deleted = $del; Receipt = $rc }
}

function Exit-CacheFailed([string]$why,[string]$bp,[string]$receipts) {
    Write-Host ""
    Write-Host "[STOP] SOURCE_CHANGED_CACHE_FAILED / do_not_reapply : $why" -ForegroundColor Red
    Write-Host "[STOP] Source receipt gates had passed; template cleanup did not complete. No automatic rollback or retry. Retain backup_path=$bp and receipts: $receipts" -ForegroundColor Red
    exit 3
}

# ==================================================================== APPLY
if ($Step -eq 'Apply') {
    $null = Assert-LocalRunner
    Assert-SavedRun $true
    Assert-NoPriorLocalRun 'apply'
    Assert-NoPriorLocalRun 'rollback'
    $bash = Build-RunBash 'apply' '--apply' $BashApplyPre $ExpectedPhpVer ''
    Note "owner ssh -> admit dirs + home/webroot pins; staged sha==pin; PHP==$ExpectedPhp $ExpectedPhpVer; catalog DIR_STORAGE/DIR_CACHE==pins (canonical, non-link); twig pin; exact template dir; canonical parents of the 3 new targets; refuse if a prior host apply receipt exists"
    Note "then exactly ONE run: cd ~/public_html && $ExpectedPhp -d user_agent=$RunUA ~/private/booster-patches/$runnerName --apply (stdout/stderr into private receipt files first)"
    Note 'This is the production source write (26 files). No automatic rerun, rollback or retry follows any outcome.'
    $res = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $bash
    $receipt = Save-Receipt $res 'apply'
    $script:RetainNotice = 'Retain complete receipt ' + $receipt
    $bps = @(Save-RunRecord $res 'apply' $receipt)
    Write-Host $res.StdOut
    $notice = 'do_not_reapply; no automatic rerun/rollback/cache cleanup. Retain receipt ' + $receipt + ' ; backup_path rows: [' + ($bps -join ',') + '] ; remote STDOUT/STDERR files named in the receipt.'
    $run = Read-Run $res '--apply' @{ TEMPLATE_STATE = 'dir' } $notice
    $brs = Get-TaggedValue $res.StdOut 'BACKUP_ROOT_STATE'
    if (@('dir','absent_runner_creates_0700') -cnotcontains $brs) { Stop-Draft "BACKUP_ROOT_STATE missing/unexpected: $brs" }
    if (@($run.Lines | Where-Object { $_ -ceq 'already_applied=yes' }).Count -gt 0) {
        Stop-Draft "ALREADY_APPLIED_ASSESSMENT_ONLY: runner found the complete after-state; NOT a new deployment (php exit=$($run.Cli), runner_post_state=$($run.Post)). No cache cleanup by this packet."
    }
    if ($run.Cli -cne '0' -or $run.BadErr.Count -gt 0) {
        $rs = @($run.Lines | Where-Object { $_ -ceq 'restored=yes' }).Count
        Stop-Draft ("SOURCE_APPLY_NOT_CONFIRMED actual_php_exit=$($run.Cli) wrapper_exit=$($res.ExitCode) restored_yes_rows=$rs runner_post_state=$($run.Post) stderr: " + ($run.BadErr -join ' | '))
    }
    if ($bps.Count -ne 1) { Stop-Draft "Expected exactly one backup_path row, found $($bps.Count)" }
    $bp = $bps[0]
    $bn = Get-BackupNameFromPath $bp
    Test-ApplyStdout $run.Lines $bp
    if ($run.Post -cne 'absent') { Stop-Draft "SOURCE_RECEIPTS_MATCH_BUT_RUNNER_POST_STATE=$($run.Post): staged runner absence not confirmed; nothing deleted by this packet; no cache cleanup." }
    Note 'source Apply gates passed: 26 knownspec applied rows, composition barrier, 26 changed_file rows, host_php_lint=ok, apply=ok, self_deleted=yes (staged runner verified absent), done=ok, php exit 0, known warning only'
    $c = Invoke-TemplateCleanup 'apply-cache'
    if (-not $c.Ok) { Exit-CacheFailed $c.Why $bp ($receipt + ' ; ' + $c.Receipt) }
    Line
    Note "APPLY captured (awaiting Codex assessment; not approval). template cleanup: state=$($c.State) deleted=$($c.Deleted)"
    Note "[RETAIN] backup_path=$bp  exact rollback name: $bn"
    Note "receipts: $receipt ; $($c.Receipt) ; remote: $($run.OutFile) ; $($run.ErrFile)"
    Note 'Wait >= 3 s (OPcache revalidate_freq=2) before owner QA. No observation window, exactly-once, delivery or full-input-range claim.'
    Note "Recovery only by separate owner decision: -Step Upload, -Step Verify, then -Step Rollback -BackupName $bn (roll back ACC-009 before the earlier Mono task; never mix backup roots)."
    exit 0
}

# ================================================================= ROLLBACK
if ($Step -eq 'Rollback') {
    $null = Assert-LocalRunner
    Assert-SavedRun $false
    $bp = $BackupRoot + '/' + $BackupName
    Assert-BackupFromApplyReceipt $bp
    Assert-NoPriorLocalRun 'rollback'
    $mode = '--rollback=' + $BackupName
    $bash = Build-RunBash 'rollback' $mode $BashRollbackPre '8.0.*' $BackupName
    Note "owner ssh -> same guards (staged sha==pin, PHP==$ExpectedPhp 8.0.x, catalog constants/twig pins, canonical new-target parents) + exact named backup dir/manifest under $BackupRoot; refuse if a prior host rollback receipt exists"
    Note "then exactly ONE run: cd ~/public_html && $ExpectedPhp -d user_agent=$RunUA ~/private/booster-patches/$runnerName $mode"
    Note 'Runner itself classifies all 26 targets and verifies every required backup/manifest/hash/metadata BEFORE the first restore; partial states allowed, drift blocks.'
    $res = Invoke-Proc -File 'ssh' -ArgList ($SshOpts + @('-T',$SshAlias,'bash','-s')) -StdinText $bash
    $receipt = Save-Receipt $res 'rollback'
    $script:RetainNotice = 'Retain complete receipt ' + $receipt + ' and backup ' + $bp
    $bps = @(Save-RunRecord $res 'rollback' $receipt)
    Write-Host $res.StdOut
    $notice = 'STOP: no retry, no deletion, no automatic re-apply. Retain backup ' + $bp + ' , receipt ' + $receipt + ' and the remote STDOUT/STDERR files named in it for assessment.'
    $run = Read-Run $res $mode @{ BACKUP_DIR = $bp } $notice
    $msha = Get-TaggedValue $res.StdOut 'BACKUP_MANIFEST_SHA'
    Assert-Sha $msha 'backup manifest'
    $tst = Get-TaggedValue $res.StdOut 'TEMPLATE_STATE'
    if (@('dir','absent') -cnotcontains $tst) { Stop-Draft "TEMPLATE_STATE missing/unexpected: $tst" }
    if ($run.Cli -cne '0' -or $run.BadErr.Count -gt 0) {
        Stop-Draft ("ROLLBACK_NOT_CONFIRMED actual_php_exit=$($run.Cli) wrapper_exit=$($res.ExitCode) runner_post_state=$($run.Post) stderr: " + ($run.BadErr -join ' | '))
    }
    if ($bps.Count -ne 1 -or $bps[0] -cne $bp) { Stop-Draft "Rollback backup_path rows [$($bps -join ',')] differ from requested $bp" }
    $restored = Test-RollbackStdout $run.Lines $bp
    if ($run.Post -cne 'absent') { Stop-Draft "ROLLBACK_RECEIPTS_MATCH_BUT_RUNNER_POST_STATE=$($run.Post): staged runner absence not confirmed; nothing deleted by this packet; no cache cleanup." }
    Note "source Rollback gates passed: 26 reverse-order rows (restored=$restored already_before=$(26 - $restored)), rollback=ok, host_php_lint=ok, self_deleted=yes (runner verified absent), done=ok, php exit 0; manifest sha256=$msha"
    $cstate = 'not_run (no restored rows; no source change by this invocation)'
    if ($restored -gt 0) {
        $c = Invoke-TemplateCleanup 'rollback-cache'
        if (-not $c.Ok) { Exit-CacheFailed $c.Why $bp ($receipt + ' ; ' + $c.Receipt) }
        $cstate = "state=$($c.State) deleted=$($c.Deleted) receipt=$($c.Receipt)"
    }
    Line
    Note "ROLLBACK captured (awaiting Codex assessment; not approval). template cleanup: $cstate"
    Note "[RETAIN] backup $bp (not deleted) ; receipt $receipt ; remote: $($run.OutFile) ; $($run.ErrFile)"
    Note 'Wait >= 3 s (OPcache revalidate_freq=2) before owner QA. No observation window or delivery claim; source-only rollback, no business/DB effects undone.'
    exit 0
}

Stop-Draft "Unhandled step '$Step'"
