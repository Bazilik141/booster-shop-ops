#requires -Version 5.1
<#
  ACC-009-C1-H1  Owner Preflight User-Agent variation packet  (DRAFT — owner-run only)
  File: diagnostics/ACC-009_C1-H1_user-agent_owner-PS_20261021.ps1
  Parent: ACC-009_C1-H1_schema_owner-agent-PS_20261020.ps1 sha256 82362b9f64061c916ae832749d01aff1d94123b52c9a784e2fce4d4183d26ae7
  Only delta: Preflight runs the SAME staged runner (sha256 3403f0f2d15c26e81963db5ed7511cd0d38f06eb37369de4874281a16bea6753)
  as: php -d user_agent=BoosterShop-ACC009-Runtime/1.0. Guarded hypothesis test for the
  observed HTTP 406; not a claimed fix; no WAF/ModSecurity attribution.
  -------------------------------------------------------------------------
  This script is DRAFTED for the owner to run. It is NOT executed here and is
  NOT approved. Codex checks it mechanically, then the separate required review
  runs. Owner alone invokes ssh/scp via the pre-configured 'booster' alias.
  Three SEPARATE invocations exist:  -Step Upload | Verify | Preflight
  Upload/Verify branches are byte-identical to the parent and NOT released by this
  packet; only -Step Preflight is proposed, reusing the existing verified state.
  No Apply / Rollback / Smoke / cache-deletion / mutation switch exists here.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Upload','Verify','Preflight')]
    [string]$Step
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
function Stop-Draft([string]$msg) {
    Write-Host ""
    Write-Host "[STOP] $msg" -ForegroundColor Red
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
