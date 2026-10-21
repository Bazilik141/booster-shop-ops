#requires -Version 5.1
<#
ACC-009-QA owner-only packet: diagnostics/ACC-009-QA_owner-PS_20261021.ps1. Draft only; agents never run it.
The owner runs exactly ONE step per invocation. No step starts another:  -Step Upload | Verify | Preflight | Apply
This file grants nothing. All existing gates and the independent review must pass before the owner runs any step.
Exit codes: 0 OK | 1 FAILED, no source change detected | 3 SOURCE_CHANGED_CACHE_FAILED | 4 SOURCE_CHANGED_* postcheck failure
      | 5 STATE_UNKNOWN. After 3, 4 or 5 this script does no retry, rollback or reapply. Escalate to review.
Rollback facts only (no command here): runner --rollback-check=/absolute/backup, then --rollback=/absolute/backup. It admits
ALL 3 targets and ALL 3 backups before any write, accepts partial before/after, and refuses drift. Roll back this follow-up
BEFORE parent C1/H1. There is no business undo. Rollback needs separate owner permission.
PHP lint covers runner/nonPHP syntax only. It is not native Twig compile proof.
#>
param([Parameter(Mandatory = $true)][ValidateSet('Upload', 'Verify', 'Preflight', 'Apply')][string]$Step)
$ErrorActionPreference = 'Stop'
$Root   = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Name   = 'ACC-009-QA_checkout-recipient-hints_20261021.php'
$Local  = Join-Path $Root ('patches\' + $Name)
$Sha    = '55f7efc8d23a1a18959926cbc1c4dd9c3193d06249ba30a54b920ca73171c2a6'
$Bytes  = 89448
$Rcpt   = Join-Path $Root 'work\acc009-checkout-qa-20261021\owner-receipts'
$Ssh    = Join-Path $env:WINDIR 'System32\OpenSSH\ssh.exe'
$Scp    = Join-Path $env:WINDIR 'System32\OpenSSH\scp.exe'
$Alias  = 'booster'
$Allow  = @('WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.')
$SshOpt = @('-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=2', '-T', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', 'IdentitiesOnly=yes',
  '-o', 'IdentityAgent=//./pipe/openssh-ssh-agent', '-o', 'AddKeysToAgent=no', '-o', 'ForwardAgent=no',
  '-o', 'PreferredAuthentications=publickey', '-o', 'PasswordAuthentication=no', '-o', 'KbdInteractiveAuthentication=no')
$Targets = @(
  @('catalog/view/javascript/acc009-checkout.js', '8137d8b6a6b79cef1ad8a7910c452e7420c9fa8273bdf63cbbbf3f4c4d0b4771', '0cb1acff55038642851649c4bc5b82b55faf62769d9993310b45ee5e5305f4f1'),
  @('catalog/view/javascript/checkout-reskin.js', '9af1c78e3c12059e06685e5fcc58f39eaa241f734617ad6f5bf638ed2a334392', 'a73a0209021266618ef395bc30d50be0fd3071a02035c43d79c0b5b9c79db8e6'),
  @('catalog/view/template/checkout/checkout.twig', 'd989bf48ff149d3cf46f1c827b94a9b3e400e457f7c210f9232331317db1a7f6', '397b85ccfd84c881779d0fc6ebadf40205060e440af7b588d0e249cf250c2146'))
$Deps = @(
  'catalog/view/javascript/checkout-state.js|83a9de5bbb2c6184abd5c9ceb89b23841532b415ad1e4cb14cf48315e4c9f97c',
  'catalog/view/template/checkout/register.twig|354d390457dbfb056e5243defe59f4ff587cd690c657e04b853549c6c8cddd20',
  'catalog/view/template/checkout/shipping_address.twig|627047b08f77597aa7eac0224cc0a55b0c8e9f9d3eecb7d6d23a1be9b2380d2a',
  'catalog/view/template/checkout/shipping_method.twig|dbaa962422edf8e9b5d78a28780f0762153bd056970ee63dd8653ac962f37adc',
  'catalog/view/template/checkout/payment_method.twig|a6de326763d2b27ca214328b6098513cc62974956b40872162e3b935e2117d57',
  'catalog/view/stylesheet/bootstrap.css|3e6b85215061cd33fc02676958ee706c0e96b877893c244bff473ce4fb9ecc57',
  'catalog/view/stylesheet/boostershop-ds.css|c5af44b72b362a8090b2f5699d41f06d0e2d1663ca9b82173c66d9d67413af0d',
  'catalog/view/stylesheet/booster-typography.css|9a8ddc85fb6adc85db81df387c3b0c06df46db1ee4f14856fda5728b926d34b5',
  'catalog/view/javascript/common.js|58605f6df5f8ded195f994d44446920b478e331243c983d75df56e49c48d7c02')
$Pins = (@($Deps) + @($Targets | ForEach-Object { $_[0] + '|' + $_[1] })) -join "`n"
$Utf8 = New-Object System.Text.UTF8Encoding($false)
$PacketSha = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant()

$Common = @'
set -euo pipefail
export LC_ALL=C
umask 077
H=/home2/boosters; W=$H/public_html; S=$H/private/booster-patches; P=$H/private/booster-pulls
T=$H/ocartdata/storage/cache/template; F=$S/__NAME__; SHA=__SHA__; BYTES=__BYTES__
die() { echo "pkt_fail=$1"; exit 1; }
real() { readlink -f -- "$1"; }
hash_ok() { [ -f "$1" ] && [ ! -L "$1" ] && [ "$(real "$1")" = "$1" ] && [ "$(stat -c %s -- "$1")" = "$BYTES" ] && [ "$(sha256sum -- "$1" | cut -d' ' -f1)" = "$SHA" ]; }
tpl_ok() { [ -d "$T" ] && [ ! -L "$T" ] && [ "$(real "$T")" = "$T" ] || return 1; local links; links=$(find -P "$T" -xdev -type l -print -quit) || return 1; [ -z "$links" ]; }
{ [ "$HOME" = "$H" ] && [ "$(cd -P ~ && pwd)" = "$H" ]; } || die real_home
for d in "$H" "$W" "$S" "$P"; do { [ -d "$d" ] && [ ! -L "$d" ] && [ "$(real "$d")" = "$d" ]; } || die "unsafe_dir:$d"; done
case "$S/" in "$W/"*) die stage_inside_webroot ;; esac
case "$P/" in "$W/"*) die pulls_inside_webroot ;; esac
PHPBIN=$(command -v php) || die php_missing
{ [ "$PHPBIN" = /usr/local/bin/php ] && [ -x "$PHPBIN" ]; } || die php_path_not_admitted
V=$("$PHPBIN" -n -r 'echo PHP_VERSION;') || die php_version
[[ "$V" =~ ^8\.0\.[0-9]+$ ]] || die php_version_not_8_0
echo "pkt_php=$PHPBIN"
echo "pkt_php_version=$V"
'@

$Upload = @'
if [ -e "$F" ] || [ -L "$F" ]; then
  hash_ok "$F" || die destination_exists_not_identical_nonlinked
  echo pkt_upload=already_present_same_hash
else
  echo pkt_upload=scp_required
fi
'@

$Verify = @'
hash_ok "$F" || die runner_sha_mismatch_before_lint
echo "pkt_remote_runner_sha256=$SHA"
"$PHPBIN" -n -l "$F" >/dev/null 2>&1 || die runner_lint
echo pkt_runner_lint=ok
C=$W/config.php
{ [ -f "$C" ] && [ ! -L "$C" ] && [ "$(real "$C")" = "$C" ]; } || die config_file
cd "$W"
"$PHPBIN" -n -r 'ob_start(function () { return ""; }); require "./config.php"; ob_end_clean(); $c = defined("DIR_CACHE") ? DIR_CACHE : ""; $s = defined("DIR_STORAGE") ? DIR_STORAGE : ""; $ok = $c === "/home2/boosters/ocartdata/storage/cache/" && $s === "/home2/boosters/ocartdata/storage/"; echo "pkt_dir_cache=", ($ok ? $c : "INVALID"), "\n", "pkt_dir_storage=", ($ok ? $s : "INVALID"), "\n", "pkt_dir_modification=", (defined("DIR_MODIFICATION") ? "defined_report_only" : "undefined"), "\n"; exit($ok ? 0 : 1);' || die config_constants
tpl_ok || die template_dir_or_symlink
echo pkt_template_dir=ok
echo pkt_verify=ok
'@

$Pre = @'
hash_ok "$F" || die runner_sha_mismatch_before_execution
echo "pkt_remote_runner_sha256=$SHA"
cd "$W"
while IFS='|' read -r rel want; do
  [ -n "$rel" ] || continue
  { [ -f "$rel" ] && [ ! -L "$rel" ] && [ "$(real "$rel")" = "$W/$rel" ] && [ "$(sha256sum -- "$rel" | cut -d' ' -f1)" = "$want" ]; } || die "pin_mismatch:$rel"
done <<'PINS'
__PINS__
PINS
echo pkt_pins=ok_12
tpl_ok || die template_dir_or_symlink
N=$(find "$T" -xdev -type f -name '*.php' | wc -l)
[ "$N" -le 10000 ] || die template_php_count_cap
echo "pkt_template_php_count=$N"
'@

$Preflight = @'
set +e
"$PHPBIN" "$F" --dry-run
rc=$?
set -e
echo "pkt_runner_exit=$rc"
exit "$rc"
'@

$ApplyTail = @'
set +e
source_apply() {
  out=$("$PHPBIN" "$F" --apply)
  rc=$?
  printf '%s\n' "$out"
  echo "pkt_source_exit=$rc"
  [ "$rc" -eq 0 ] || return 1
  [ "$(printf '%s\n' "$out" | tail -n 1)" = done=ok ] || { echo pkt_status=SOURCE_RECEIPT_INVALID_NO_CACHE; return 4; }
}
cf() { echo "pkt_cache_fail=$1"; echo pkt_status=SOURCE_CHANGED_CACHE_FAILED; exit 3; }
cleanup_apply() {
  tpl_ok || cf template_dir_or_symlink
  list=$(mktemp "$P/ACC-009-QA-cache.XXXXXXXX") || cf list_create
  trap 'rm -f -- "$list"' EXIT
  find -P "$T" -xdev -type f -name '*.php' -print0 >"$list" || cf list_find
  files=(); N=0
  while IFS= read -r -d '' path; do
    N=$((N+1)); [ "$N" -le 10000 ] || cf template_php_count_cap
    case "$path" in "$T/"*.php) ;; *) cf unsafe_cache_member ;; esac
    files+=("$path")
  done <"$list"
  D=0
  for path in ${files[@]+"${files[@]}"}; do
    if [ ! -e "$path" ] && [ ! -L "$path" ]; then continue; fi
    { [ -f "$path" ] && [ ! -L "$path" ] && [ "$(real "$path")" = "$path" ]; } || cf cache_member_changed
    rm -f -- "$path" || cf delete_failed
    D=$((D+1))
  done
  rm -f -- "$list" || cf list_remove
  trap - EXIT
  echo "pkt_cache_removed_php=$D"
  echo pkt_cache_dirs_and_nonphp=retained
  { [ ! -e "$F" ] && [ ! -L "$F" ]; } || { echo pkt_status=SOURCE_CHANGED_STAGE_RUNNER_PRESENT; return 4; }
  echo pkt_stage_runner_absent=yes
  echo pkt_status=APPLY_OK
}
# Canonical source-before-cache gate in the SAME Bash invocation.
source_apply && cleanup_apply
rc=$?
exit "$rc"
'@

function Assert-NoReparse([string]$Path) {
  $i = Get-Item -LiteralPath $Path -Force
  while ($null -ne $i) {
    if ($i.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "LOCAL_REPARSE_REJECTED:$($i.FullName)" }
    if ($i -is [IO.FileInfo]) { $i = $i.Directory } else { $i = $i.Parent }
  }
}
function Get-Sha([byte[]]$Data) {
  $h = [Security.Cryptography.SHA256]::Create()
  try { -join ($h.ComputeHash($Data) | ForEach-Object { $_.ToString('x2') }) } finally { $h.Dispose() }
}
function Read-LocalRunner {
  if (-not (Test-Path -LiteralPath $Local -PathType Leaf)) { throw 'LOCAL_RUNNER_MISSING' }
  Assert-NoReparse $Local
  $b = [IO.File]::ReadAllBytes($Local)
  if ($b.Length -ne $Bytes -or (Get-Sha $b) -ne $Sha) { throw 'LOCAL_RUNNER_BYTES_OR_SHA256_MISMATCH' }
  , $b
}
function Save-Receipt([System.Collections.IDictionary]$Data, [string]$Kind) {
  $p = Join-Path $Rcpt ('{0}-{1}-{2}.json' -f $Kind, [DateTime]::UtcNow.ToString("yyyyMMdd'T'HHmmssfff'Z'"), [guid]::NewGuid().ToString('N').Substring(0, 8))
  $b = $Utf8.GetBytes(($Data | ConvertTo-Json -Depth 5))
  $fs = [IO.File]::Open($p, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
  try { $fs.Write($b, 0, $b.Length) } finally { $fs.Close() }
  $p
}
function Get-Ok([string]$Kind) {
  @(Get-ChildItem -LiteralPath $Rcpt -File -Filter ($Kind + '-*.json') | Sort-Object Name | ForEach-Object {
    $j = $null; try { $j = [IO.File]::ReadAllText($_.FullName) | ConvertFrom-Json } catch { }
    if ($null -ne $j -and $j.step -eq $Kind -and $j.status -eq 'OK' -and $j.state -eq 'VALIDATED' -and $j.runnerSha -eq $Sha -and $j.packetSha -eq $PacketSha) { $_.FullName }
  })
}
function Invoke-Remote([string]$Body) {
  $text = ("main() {`n" + $Common + "`n" + $Body + "`n}`nmain </dev/null`nexit `$?`n").Replace("`r", '')
  $text = $text.Replace('__NAME__', $Name).Replace('__SHA__', $Sha).Replace('__BYTES__', [string]$Bytes).Replace('__PINS__', $Pins)
  $in = $Utf8.GetBytes($text)
  $psi = New-Object System.Diagnostics.ProcessStartInfo
  $psi.FileName = $Ssh; $psi.Arguments = (@($SshOpt) + @($Alias, 'bash', '--noprofile', '--norc', '-s')) -join ' '
  $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
  $psi.RedirectStandardInput = $true; $psi.RedirectStandardOutput = $true; $psi.RedirectStandardError = $true
  $psi.StandardOutputEncoding = $Utf8; $psi.StandardErrorEncoding = $Utf8
  $p = [Diagnostics.Process]::Start($psi)
  $o = $p.StandardOutput.ReadToEndAsync(); $e = $p.StandardError.ReadToEndAsync()
  try { $p.StandardInput.BaseStream.Write($in, 0, $in.Length); $p.StandardInput.BaseStream.Flush() } catch { } finally { $p.StandardInput.Close() }
  $timedOut = -not $p.WaitForExit(180000)
  if ($timedOut) { try { $p.Kill() } catch { } }
  $p.WaitForExit()
  [pscustomobject]@{ Exit = $(if ($timedOut) { -1 } else { $p.ExitCode }); Out = $o.Result; Err = $e.Result; ScriptSha = (Get-Sha $in); Args = $psi.Arguments }
}
function Invoke-OwnerScp {
  $psi = New-Object System.Diagnostics.ProcessStartInfo
  $psi.FileName = $Scp
  $opts = @($SshOpt | Where-Object { $_ -cne '-T' })
  $psi.Arguments = ($opts -join ' ') + ' "' + $Local + '" ' + $Alias + ':private/booster-patches/' + $Name
  $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
  $psi.RedirectStandardOutput = $true; $psi.RedirectStandardError = $true
  $psi.StandardOutputEncoding = $Utf8; $psi.StandardErrorEncoding = $Utf8
  $proc = [Diagnostics.Process]::Start($psi)
  $out = $proc.StandardOutput.ReadToEndAsync(); $err = $proc.StandardError.ReadToEndAsync()
  $timeout = -not $proc.WaitForExit(180000)
  if ($timeout) { try { $proc.Kill() } catch {} }
  $proc.WaitForExit()
  [pscustomobject]@{ Exit = $(if ($timeout) {-1} else {$proc.ExitCode}); Out=$out.Result; Err=$err.Result }
}
function Split-Lines([string]$s) { @(([string]$s).Replace("`r", '') -split "`n" | Where-Object { $_ -ne '' }) }
function Test-Lines([string[]]$Got, [string[]]$Exp) {
  if (@($Got).Count -ne $Exp.Count) { return $false }
  for ($i = 0; $i -lt $Exp.Count; $i++) {
    if ($Exp[$i].StartsWith('~')) { if ($Got[$i] -cnotmatch $Exp[$i].Substring(1)) { return $false } }
    elseif ($Got[$i] -cne $Exp[$i]) { return $false }
  }
  $true
}
function Get-Head([string]$Mode) {
  @('task=ACC-009-QA', "mode=$Mode", '~^time=\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$', 'runner_php_lint=ok', 'dependencies=ok_9', 'source_preflight=ok') +
  @(for ($i = 0; $i -lt 3; $i++) { 'target_{0}={1}|{2}|{3}' -f $i, $Targets[$i][0], $Targets[$i][1], $Targets[$i][2] })
}

$code = 1; $status = 'FAILED'; $state = 'NONE'; $prereq = $null; $r = $null; $err = $null; $clean = $false
try {
  if (-not (Test-Path -LiteralPath $Rcpt -PathType Container)) { Assert-NoReparse (Split-Path -Parent $Rcpt); [void][IO.Directory]::CreateDirectory($Rcpt) }
  Assert-NoReparse $Rcpt
  if (-not (Test-Path -LiteralPath $Ssh -PathType Leaf)) { throw 'NATIVE_OPENSSH_MISSING' }
  $runnerBytes = Read-LocalRunner
  if ($Step -eq 'Upload') {
    $r = Invoke-Remote $Upload
    if ($r.Exit -eq 0 -and $r.Err -eq '' -and (@(Split-Lines $r.Out) -ccontains 'pkt_upload=scp_required')) {
      if (-not (Test-Path -LiteralPath $Scp -PathType Leaf)) { throw 'NATIVE_SCP_MISSING' }
      $copy = Invoke-OwnerScp
      $copyReceipt = Save-Receipt ([ordered]@{task='ACC-009-QA';step='UploadSCP';runnerSha=$Sha;nativeExit=$copy.Exit;stdout=$copy.Out;stderr=$copy.Err}) 'UploadSCP'
      Write-Host $copy.Out; if ($copy.Err) {Write-Host $copy.Err}
      Write-Host "scp_exit=$($copy.Exit) receipt=$copyReceipt"
      if ($copy.Exit -ne 0 -or $copy.Err -ne '') { throw 'SCP_FAILED_NO_RETRY' }
      # scp completion is not a remote-hash assertion. Verify is separate.
      $r.Out += "pkt_scp_completed=0`n"
    }
  } elseif ($Step -eq 'Verify') {
    $r = Invoke-Remote $Verify
  } elseif ($Step -eq 'Preflight') {
    $v = @(Get-Ok 'Verify'); if ($v.Count -lt 1) { throw 'NO_VALIDATED_VERIFY_STATE_FOR_RUNNER_SHA' }; $prereq = $v[-1]
    $r = Invoke-Remote ($Pre + "`n" + $Preflight)
  } else {
    $pf = @(Get-Ok 'Preflight'); if ($pf.Count -lt 1) { throw 'NO_VALIDATED_PREFLIGHT_STATE_FOR_RUNNER_SHA' }; $prereq = $pf[-1]
    if (@(Get-ChildItem -LiteralPath $Rcpt -File -Filter 'Apply-*.json').Count -gt 0) { throw 'APPLY_ALREADY_ATTEMPTED_NO_RETRY' }
    [void](Save-Receipt ([ordered]@{ task = 'ACC-009-QA'; step = 'Apply'; status = 'STARTED'; runnerSha = $Sha; packetSha = $PacketSha; prerequisite = $prereq; utc = [DateTime]::UtcNow.ToString('o') }) 'Apply')
    $status = 'STATE_UNKNOWN'; $code = 5
    $r = Invoke-Remote ($Pre + "`n" + $ApplyTail)
  }
  $L = @(Split-Lines $r.Out)
  $clean = (@(Split-Lines $r.Err | Where-Object { $Allow -cnotcontains $_ }).Count -eq 0)
  $wrapper = @($L | Where-Object { $_.StartsWith('pkt_') })
  $known = '^(pkt_php=/usr/local/bin/php|pkt_php_version=8\.0\.[0-9]+|pkt_remote_runner_sha256=' + $Sha + '|pkt_upload=(scp_required|already_present_same_hash)|pkt_scp_completed=0|pkt_runner_lint=ok|pkt_dir_cache=/home2/boosters/ocartdata/storage/cache/|pkt_dir_storage=/home2/boosters/ocartdata/storage/|pkt_dir_modification=(defined_report_only|undefined)|pkt_template_dir=ok|pkt_verify=ok|pkt_pins=ok_12|pkt_template_php_count=[0-9]+|pkt_runner_exit=0|pkt_source_exit=0|pkt_cache_removed_php=[0-9]+|pkt_cache_dirs_and_nonphp=retained|pkt_stage_runner_absent=yes|pkt_status=APPLY_OK)$'
  if ($r.Exit -eq 0 -and @($wrapper | Where-Object {$_ -cnotmatch $known}).Count -gt 0) {throw 'UNEXPECTED_WRAPPER_ROW'}
  if ($r.Exit -eq 0 -and @($wrapper | Group-Object {($_ -split '=',2)[0]} | Where-Object {$_.Count -gt 1}).Count -gt 0) {throw 'DUPLICATE_WRAPPER_ROW'}
  $RL = @($L | Where-Object { -not $_.StartsWith('pkt_') })
  if ($r.Exit -eq 0 -and (($L -cnotcontains 'pkt_php=/usr/local/bin/php') -or @($L -cmatch '^pkt_php_version=8\.0\.[0-9]+$').Count -ne 1)) {throw 'MISSING_RUNTIME_HEADER'}
  if ($r.Exit -eq 0 -and $Step -in @('Upload','Verify') -and $RL.Count -ne 0) {throw 'UNEXPECTED_NONWRAPPER_ROW'}
  if ($Step -eq 'Upload') {
    if ($r.Exit -eq 0 -and $clean -and (($L -ccontains 'pkt_scp_completed=0') -or ($L -ccontains 'pkt_upload=already_present_same_hash'))) { $status = 'OK'; $code = 0 }
  } elseif ($Step -eq 'Verify') {
    $need = @("pkt_remote_runner_sha256=$Sha", 'pkt_php=/usr/local/bin/php', 'pkt_runner_lint=ok', 'pkt_dir_cache=/home2/boosters/ocartdata/storage/cache/', 'pkt_dir_storage=/home2/boosters/ocartdata/storage/', 'pkt_template_dir=ok', 'pkt_verify=ok')
    if ($r.Exit -eq 0 -and $clean -and @($need | Where-Object { $L -cnotcontains $_ }).Count -eq 0 -and @($L -cmatch '^pkt_php_version=8\.0\.\d+$').Count -eq 1) { $status = 'OK'; $state = 'VALIDATED'; $code = 0 }
  } elseif ($Step -eq 'Preflight') {
    $exp = @(Get-Head '--dry-run') + @('would_change_files=3', 'db_access=NONE', 'done=ok')
    $need = @("pkt_remote_runner_sha256=$Sha", 'pkt_pins=ok_12', 'pkt_runner_exit=0')
    if ($r.Exit -eq 0 -and $clean -and @($need | Where-Object { $L -cnotcontains $_ }).Count -eq 0 -and (Test-Lines $RL $exp)) { $status = 'OK'; $state = 'VALIDATED'; $code = 0 }
  } else {
    $bp = @($RL -cmatch '^backup_path=/home2/boosters/_patch_backups/ACC-009-QA-\d{8}T\d{6}Z-[a-f0-9]{8}$')
    if ($r.Exit -eq 0) {
      $status = 'SOURCE_CHANGED_POSTCHECK_FAILED'; $code = 4
      if ($bp.Count -eq 1) {
        $b = $bp[0].Substring(12)
        $exp = @(Get-Head '--apply') + @($bp[0]) +
          @(for ($i = 0; $i -lt 3; $i++) { $t = $Targets[$i]; "target=$($t[0])"; "sha256_before=$($t[1])"; "backup=$b/$i.source"; 'syntax_gate=ok_nonPHP_target'; "sha256_after=$($t[2])"; "changed_file=$($t[0])" }) +
          @(for ($i = 0; $i -lt 3; $i++) { 'after_{0}={1}|{2}' -f $i, $Targets[$i][0], $Targets[$i][2] }) +
          @('host_php_lint=ok', 'lint_scope=runner_and_nonPHP_target_syntax_only_not_Twig_compilation', 'db_access=NONE', 'self_deleted=yes', 'done=ok')
        $need = @("pkt_remote_runner_sha256=$Sha", 'pkt_pins=ok_12', 'pkt_source_exit=0', 'pkt_cache_dirs_and_nonphp=retained', 'pkt_stage_runner_absent=yes', 'pkt_status=APPLY_OK')
        if ($clean -and @($need | Where-Object { $L -cnotcontains $_ }).Count -eq 0 -and @($L -cmatch '^pkt_cache_removed_php=\d+$').Count -eq 1 -and (Test-Lines $RL $exp)) { $status = 'OK'; $state = 'APPLIED'; $code = 0 }
      }
    } elseif ($r.Exit -eq 3) { $status = 'SOURCE_CHANGED_CACHE_FAILED'; $code = 3 }
    elseif ($r.Exit -eq 4) { $status = 'SOURCE_CHANGED_POSTCHECK_FAILED'; $code = 4 }
    elseif ($r.Exit -eq 1 -and @($L -cmatch '^pkt_source_exit=').Count -eq 0) { $status = 'FAILED_BEFORE_SOURCE_NO_CACHE'; $code = 1 }
    elseif ($r.Exit -eq 1 -and ([string]$r.Err).Contains('error=self_delete_failed_source_may_have_changed')) { $status = 'SOURCE_CHANGED_SELF_DELETE_FAILED_NO_CACHE'; $code = 4 }
    elseif ($r.Exit -eq 1 -and $bp.Count -eq 0) { $status = 'SOURCE_FAILED_BEFORE_BACKUP_NO_CACHE'; $code = 1 }
    elseif ($r.Exit -eq 1 -and ($RL -ccontains 'auto_restore=ok')) { $status = 'SOURCE_FAILED_AUTO_RESTORED_NO_CACHE'; $code = 1 }
    else { $status = 'STATE_UNKNOWN'; $code = 5 }
  }
} catch { $err = $_.Exception.Message }
if ($r) { Write-Host $r.Out; if ($r.Err) { Write-Host 'STDERR:'; Write-Host $r.Err } }
$rec = [ordered]@{ task = 'ACC-009-QA'; step = $Step; status = $status; state = $state; localExit = $code; runnerSha = $Sha; packetSha = $PacketSha; runnerBytes = $Bytes
  prerequisite = $prereq; utc = [DateTime]::UtcNow.ToString('o'); error = $err; sshExe = $Ssh; sshArgs = $(if ($r) { $r.Args })
  remoteScriptSha256 = $(if ($r) { $r.ScriptSha }); remoteExit = $(if ($r) { $r.Exit }); stdout = $(if ($r) { $r.Out }); stderr = $(if ($r) { $r.Err })
  stderrClean = $clean; hashProof = $(if($Step -eq 'Upload'){'scp is not remote proof; separate Verify required'}else{'remote sha256sum only'}); lintScope = 'runner/nonPHP syntax only, not Twig compilation'; grants = 'none' }
try { $rp = Save-Receipt $rec $Step } catch { $rp = 'RECEIPT_SAVE_FAILED:' + $_.Exception.Message }
Write-Host "step=$Step status=$status exit=$code error=$err receipt=$rp"
Write-Host 'No next step was started. Any next step requires the existing gates/review and a separate owner invocation.'
exit $code
