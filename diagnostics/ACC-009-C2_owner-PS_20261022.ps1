#requires -Version 5.1
<# ACC-009-C2 OWNER-ONLY packet. Agents never execute SSH/SCP/key tools.
One separate step per invocation: Upload, Verify, Preflight, Apply.
C2 pre-fills the recipient courier address from the order snapshot. Existing
sender/recipient/document calls remain operator actions. No NP call by packet.
Source rollback cannot undo remote effects. No DB writes or cache cleanup.
An attempted Apply is never replayed automatically. Failure/unknown state => STOP.
Exit0 validated success; exit1 unchanged/failure; exit4 source changed; exit5 unknown.
Rollback needs its own reviewed owner packet: check every target and backup before
--rollback-check=NAME then --rollback=NAME. Reverse C2 before N3. No rollback here.
#>
param([Parameter(Mandatory=$true)][ValidateSet('Upload','Verify','Preflight','Apply')][string]$Step)
$ErrorActionPreference='Stop'
$Root='C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Name='ACC-009-C2_recipient-address-prefill-private-release_20261022.php'
$Local=Join-Path $Root ('patches\'+$Name)
$Sha='8645e8f247681529d0a8ec9e42b6a01fa1a656e246fa949a4fb08796206fb9d2'
$Bytes=224549
$Rcpt=Join-Path $Root 'work\acc009-c2-release-20261022\owner-receipts'
$Ssh=Join-Path $env:WINDIR 'System32\OpenSSH\ssh.exe'
$Scp=Join-Path $env:WINDIR 'System32\OpenSSH\scp.exe'
$Alias='booster'
$Target='extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php'
$Before='2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93'
$After='4d84bb039d7c43ede234d76deef698b8e0e7886184800e6bf6eeaaa7a19ca0af'
$Allow=@('WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.')
$SshOpt=@('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-T','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes',
 '-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no','-o','ForwardAgent=no','-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')
$Utf8=New-Object System.Text.UTF8Encoding($false)
$PacketSha=(Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant()
$Common=@'
set -euo pipefail
export LC_ALL=C
umask 077
H=/home2/boosters; W=$H/public_html; S=$H/private/booster-patches; P=$H/private/booster-pulls
F=$S/__NAME__; SHA=8645e8f247681529d0a8ec9e42b6a01fa1a656e246fa949a4fb08796206fb9d2; BYTES=224549
TARGET=$W/extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
die() { echo "pkt_fail=$1"; exit 1; }
real() { readlink -f -- "$1"; }
hash_ok() { [ -f "$1" ] && [ ! -L "$1" ] && [ "$(real "$1")" = "$1" ] && [ "$(stat -c %s -- "$1")" = "$BYTES" ] && [ "$(sha256sum -- "$1" | cut -d' ' -f1)" = "$SHA" ]; }
{ [ "$HOME" = "$H" ] && [ "$(cd -P ~ && pwd)" = "$H" ]; } || die real_home
for d in "$H" "$W" "$S" "$P"; do { [ -d "$d" ] && [ ! -L "$d" ] && [ "$(real "$d")" = "$d" ]; } || die unsafe_dir; done
case "$S/" in "$W/"*) die stage_inside_webroot ;; esac
PHPBIN=$(command -v php) || die php_missing
{ [ "$PHPBIN" = /usr/local/bin/php ] && [ -x "$PHPBIN" ]; } || die php_path_not_admitted
V=$("$PHPBIN" -n -r 'echo PHP_VERSION;') || die php_version
[[ "$V" =~ ^8\.0\.[0-9]+$ ]] || die php_version_not_8_0
echo "pkt_php=$PHPBIN"
echo "pkt_php_version=$V"
'@
$Upload=@'
if [ -e "$F" ] || [ -L "$F" ]; then
  hash_ok "$F" || die destination_exists_not_identical_nonlinked
  echo pkt_upload=already_present_same_hash
else
  echo pkt_upload=scp_required
fi
'@
$Verify=@'
hash_ok "$F" || die runner_hash_size_or_path
echo "pkt_remote_runner_sha256=$SHA"
"$PHPBIN" -n -l "$F" >/dev/null 2>&1 || die runner_lint
echo pkt_runner_lint=ok
echo pkt_verify=ok
'@
$Run=@'
hash_ok "$F" || die runner_hash_size_or_path
echo "pkt_remote_runner_sha256=$SHA"
cd "$W"
set +e
"$PHPBIN" "$F" __MODE__
rc=$?
set -e
echo "pkt_runner_exit=$rc"
if [ -f "$TARGET" ] && [ ! -L "$TARGET" ] && [ "$(real "$TARGET")" = "$TARGET" ]; then
  echo "pkt_current_target_sha256=$(sha256sum -- "$TARGET" | cut -d' ' -f1)"
else
  echo pkt_current_target_sha256=unknown
fi
if [ ! -e "$F" ] && [ ! -L "$F" ]; then echo pkt_stage_runner=absent; else echo pkt_stage_runner=present; fi
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
function Invoke-Remote([string]$Body) {
  $text = ("main() {`n" + $Common + "`n" + $Body + "`n}`nmain </dev/null`nexit `$?`n").Replace("`r", '')
  $text = $text.Replace('__NAME__', $Name).Replace('__SHA__', $Sha).Replace('__BYTES__', [string]$Bytes)
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

function Get-Prerequisite([string]$Kind,[int]$MaxSeconds) {
  $found=@(Get-ChildItem -LiteralPath $Rcpt -File -Filter ($Kind+'-*.json') | Sort-Object Name | ForEach-Object {
    Assert-NoReparse $_.FullName
    $j=$null;try {$j=[IO.File]::ReadAllText($_.FullName)|ConvertFrom-Json} catch {}
    if ($j -and $j.task -ceq 'ACC-009-C2' -and $j.step -ceq $Kind -and $j.status -ceq 'OK' -and $j.runnerSha -ceq $Sha -and $j.packetSha -ceq $PacketSha) {
      $age=([DateTime]::UtcNow-[DateTime]::Parse($j.utc).ToUniversalTime()).TotalSeconds
      if($age -ge 0 -and $age -le $MaxSeconds){$_.FullName}
    }
  })
  if($found.Count -eq 0){throw "MISSING_OR_EXPIRED_VALIDATED_$Kind"}
  $found[-1]
}
function Test-RunnerReceipt([string[]]$Lines,[string]$Mode) {
  $web=@($Lines|Where-Object {$_ -clike 'web_runtime=*'})
  if($web.Count -ne 1){return $false}
  try {$j=$web[0].Substring(12)|ConvertFrom-Json} catch {return $false}
  if(@($j.PSObject.Properties.Name).Count -ne 5 -or $j.php -cnotmatch '^8\.0\.[0-9]+$' -or $j.sapi -cne 'litespeed' -or $j.opcache_enabled -isnot [bool] -or $j.validate_timestamps -isnot [bool] -or $j.revalidate_freq -isnot [int] -or $j.revalidate_freq -lt 0 -or $j.revalidate_freq -gt 60 -or ($j.opcache_enabled -and -not $j.validate_timestamps)){return $false}
  $already=$Lines -ccontains 'already_applied=yes'
  $backup=@($Lines|Where-Object {$_ -clike 'backup_path=*'})
  $b='none';$cb=$Before;$ca=$Before;$action='planned';$count=0
  if($already){$cb=$After;$ca=$After;$action='already_applied'}
  elseif($Mode -ceq '--apply') {
    if($backup.Count -ne 1 -or $backup[0] -cnotmatch '^backup_path=/home2/boosters/_patch_backups/ACC-009-C2-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$'){return $false}
    $b=$backup[0].Substring(12)+'/internet_document.php';$ca=$After;$action='applied';$count=1
  }
  $exp=@('task=ACC-009-C2',"mode=$Mode",'~^time=\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$','runner_php_lint=ok',
    'runtime_sources=49_exact_identities_and_no_generated_ocmod','metadata=admin_system_events_and_namespace_inventory_unchanged','directory_schema=required_local_address_columns_textual',$web[0],'probe_cleanup=ok','source_preflight=ok')
  if(-not $already -and $Mode -ceq '--apply'){$exp+=@($backup[0])}
  $exp+=@("target=$Target","sha256_before=$Before","backup=$b",'syntax_gate=php_lint_ok',"sha256_after=$After","current_before=$cb","current_after=$ca","action=$action","changed_files=$count")
  if($already){$exp+=@('already_applied=yes')}
  $exp+=@('db_access=READ_ONLY_METADATA','cache_cleanup=NONE')
  if($Mode -ceq '--apply'){$exp+=@('self_deleted=yes')}
  $exp+=@('done=ok')
  Test-Lines $Lines $exp
}
$code=1;$status='FAILED';$r=$null;$prereq=$null;$err=$null;$clean=$false
try {
  if(-not(Test-Path -LiteralPath $Rcpt -PathType Container)){Assert-NoReparse (Split-Path -Parent $Rcpt);[void][IO.Directory]::CreateDirectory($Rcpt)}
  Assert-NoReparse $Rcpt
  if(-not(Test-Path -LiteralPath $Ssh -PathType Leaf)){throw 'NATIVE_OPENSSH_MISSING'}
  $runnerBytes=Read-LocalRunner
  if(@(Get-ChildItem -LiteralPath $Rcpt -File -Filter 'Apply-*.json').Count -gt 0){throw 'APPLY_ALREADY_ATTEMPTED_STOP_NO_RETRY'}
  if($Step -ceq 'Upload') {
    $r=Invoke-Remote $Upload
    $L=@(Split-Lines $r.Out)
    $head=@('pkt_php=/usr/local/bin/php','~^pkt_php_version=8\.0\.[0-9]+$')
    if($r.Exit -ne 0 -or $r.Err -ne ''){throw 'UPLOAD_PRECHECK_FAILED'}
    if(Test-Lines $L ($head+@('pkt_upload=scp_required'))) {
      if(-not(Test-Path -LiteralPath $Scp -PathType Leaf)){throw 'NATIVE_SCP_MISSING'}
      $copy=Invoke-OwnerScp
      [void](Save-Receipt ([ordered]@{task='ACC-009-C2';step='UploadSCP';runnerSha=$Sha;packetSha=$PacketSha;nativeExit=$copy.Exit;stdout=$copy.Out;stderr=$copy.Err}) 'UploadSCP')
      if($copy.Exit -ne 0 -or $copy.Err -ne ''){throw 'SCP_FAILED_STOP_NO_RETRY'}
      $r.Out+="pkt_scp_completed=0`n"
    } elseif(-not(Test-Lines $L ($head+@('pkt_upload=already_present_same_hash')))){throw 'UPLOAD_RECEIPT_INVALID'}
    $status='OK';$code=0
  } elseif($Step -ceq 'Verify') {
    $r=Invoke-Remote $Verify
    $L=@(Split-Lines $r.Out)
    if($r.Exit -eq 0 -and $r.Err -ceq '' -and (Test-Lines $L @('pkt_php=/usr/local/bin/php','~^pkt_php_version=8\.0\.[0-9]+$',"pkt_remote_runner_sha256=$Sha",'pkt_runner_lint=ok','pkt_verify=ok'))){$status='OK';$code=0}
  } else {
    if($Step -ceq 'Preflight'){$prereq=Get-Prerequisite 'Verify' 14400;$mode='--dry-run'}
    else {
      $prereq=Get-Prerequisite 'Preflight' 1800;$mode='--apply'
      [void](Save-Receipt ([ordered]@{task='ACC-009-C2';step='Apply';status='STARTED';runnerSha=$Sha;packetSha=$PacketSha;prerequisite=$prereq;utc=[DateTime]::UtcNow.ToString('o')}) 'Apply')
      $status='STATE_UNKNOWN';$code=5
    }
    $r=Invoke-Remote ($Run.Replace('__MODE__',$mode))
    $L=@(Split-Lines $r.Out);$wrapper=@($L|Where-Object {$_.StartsWith('pkt_')});$body=@($L|Where-Object {-not $_.StartsWith('pkt_')})
    $clean=@(Split-Lines $r.Err|Where-Object {$Allow -cnotcontains $_}).Count -eq 0
    $current=@($wrapper|Where-Object {$_ -clike 'pkt_current_target_sha256=*'})
    $expectCurrent=$Before;if($Step -ceq 'Apply' -or $body -ccontains 'already_applied=yes'){$expectCurrent=$After}
    $expectStage='present';if($Step -ceq 'Apply'){$expectStage='absent'}
    $expectedWrapper=@('pkt_php=/usr/local/bin/php','~^pkt_php_version=8\.0\.[0-9]+$',"pkt_remote_runner_sha256=$Sha",'pkt_runner_exit=0',"pkt_current_target_sha256=$expectCurrent","pkt_stage_runner=$expectStage")
    if($r.Exit -eq 0 -and $clean -and (Test-Lines $wrapper $expectedWrapper) -and (Test-RunnerReceipt $body $mode)){$status='OK';$code=0}
    elseif($Step -ceq 'Apply') {
      if($current.Count -eq 1 -and $current[0] -ceq "pkt_current_target_sha256=$Before"){$status='STOP_SOURCE_BEFORE';$code=1}
      elseif($current.Count -eq 1 -and $current[0] -ceq "pkt_current_target_sha256=$After"){$status='STOP_SOURCE_AFTER_POSTCHECK_FAILED';$code=4}
      else {$status='STATE_UNKNOWN';$code=5}
    }
  }
} catch {$err=$_.Exception.Message}
if($r){Write-Host $r.Out;if($r.Err){Write-Host 'STDERR:';Write-Host $r.Err}}
$rec=[ordered]@{task='ACC-009-C2';step=$Step;status=$status;localExit=$code;runnerSha=$Sha;packetSha=$PacketSha;runnerBytes=$Bytes;prerequisite=$prereq;utc=[DateTime]::UtcNow.ToString('o');error=$err;
 remoteScriptSha256=$(if($r){$r.ScriptSha});remoteExit=$(if($r){$r.Exit});stdout=$(if($r){$r.Out});stderr=$(if($r){$r.Err});grants='none';noAutomaticRetry=$true}
try {$rp=Save-Receipt $rec $Step} catch {$rp='RECEIPT_SAVE_FAILED';$status='STATE_UNKNOWN';$code=5}
Write-Host "step=$Step status=$status exit=$code error=$err receipt=$rp"
Write-Host 'No next step started. On any failure STOP; do not retry Apply or run rollback.'
exit $code
