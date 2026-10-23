#requires -Version 5.1
<# OWNER ONLY. NOT_REVIEWED / HELD until the exact batch review-release exists.
Separate invocations: Upload -> Verify -> Preflight -> Apply. No automatic next
step, retry or rollback. No key/service action, API call, login or mandatory Lock.
WP3c Apply additionally requires an existing recent owner-held full DB backup
and explicit -DbBackupConfirmed. The dump is hashed locally, never uploaded.
The confirmation attests backup consistency; file age/hash alone cannot prove it.
#>
param(
 [Parameter(Mandatory=$true)][ValidateSet('Upload','Verify','Preflight','Apply')][string]$Step,
 [string]$DbBackupPath='', [switch]$DbBackupConfirmed
)
$ErrorActionPreference='Stop'
$Root='C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Part='N2'
$Task='ACC-009-N2'
$Name='ACC-009-N2_unified-name-policy_HELD_r4_20261023.php'
$Sha='8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89'
$Bytes=900825
$Metadata=$false
$TargetCount=18
$Local=Join-Path $Root ('patches\'+$Name)
$Rcpt=Join-Path $Root ('work\acc009-remaining-repair-r5-20261023\owner-receipts\'+$Part)
$Ssh=Join-Path $env:WINDIR 'System32\OpenSSH\ssh.exe'
$Scp=Join-Path $env:WINDIR 'System32\OpenSSH\scp.exe'
$Alias='booster'
$Utf8=New-Object System.Text.UTF8Encoding($false)
$PacketSha=(Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant()
$SshOpt=@('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-T','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes',
 '-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no','-o','ForwardAgent=no','-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')
$Common=@'
set -euo pipefail
export LC_ALL=C
umask 077
H=/home2/boosters; W=$H/public_html; S=$H/private/booster-patches
F=$S/ACC-009-N2_unified-name-policy_HELD_r4_20261023.php; SHA=8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89; BYTES=900825
die() { echo "pkt_fail=$1"; exit 1; }
real() { readlink -f -- "$1"; }
for d in "$H" "$W" "$S"; do { [ -d "$d" ] && [ ! -L "$d" ] && [ "$(real "$d")" = "$d" ]; } || die unsafe_dir; done
[ "$HOME" = "$H" ] || die home_mismatch
case "$S/" in "$W/"*) die stage_inside_webroot ;; esac
hash_ok() { [ -f "$F" ] && [ ! -L "$F" ] && [ "$(real "$F")" = "$F" ] && [ "$(stat -c %s -- "$F")" = "$BYTES" ] && [ "$(sha256sum -- "$F" | cut -d' ' -f1)" = "$SHA" ]; }
PHPBIN=$(command -v php) || die php_missing
[ "$PHPBIN" = /usr/local/bin/php ] || die php_path_changed
V=$("$PHPBIN" -n -r 'echo PHP_VERSION;') || die php_version
[[ "$V" =~ ^8\.0\.[0-9]+$ ]] || die php_not_8_0
echo "pkt_php=$PHPBIN"
echo "pkt_php_version=$V"
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
  $text = $text.Replace('ACC-009-N2_unified-name-policy_HELD_r4_20261023.php', $Name).Replace('8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89', $Sha).Replace('900825', [string]$Bytes)
  $in = $Utf8.GetBytes($text)
  $psi = New-Object System.Diagnostics.ProcessStartInfo
  $psi.FileName = $Ssh; $psi.Arguments = (@($SshOpt) + @($Alias, 'bash', '--noprofile', '--norc', '-s')) -join ' '
  $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
  $psi.RedirectStandardInput = $true; $psi.RedirectStandardOutput = $true; $psi.RedirectStandardError = $true
  $psi.StandardOutputEncoding = $Utf8; $psi.StandardErrorEncoding = $Utf8
  $p = [Diagnostics.Process]::Start($psi)
  $o = $p.StandardOutput.ReadToEndAsync(); $e = $p.StandardError.ReadToEndAsync()
  try { $p.StandardInput.BaseStream.Write($in, 0, $in.Length); $p.StandardInput.BaseStream.Flush() } catch { } finally { $p.StandardInput.Close() }
  $timedOut = -not $p.WaitForExit(600000)
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
  $timeout = -not $proc.WaitForExit(600000)
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


function Assert-Release {
 $p=Join-Path $Root 'diagnostics\ACC-009_remaining_repair_r5_evidence_20261023\review-release.json'
 if (-not (Test-Path -LiteralPath $p -PathType Leaf)) { throw 'HELD: actual Claude batch review is not released' }
 Assert-NoReparse $p
 $j=Get-Content -LiteralPath $p -Raw -Encoding UTF8 | ConvertFrom-Json
 if ($j.schema -cne 'acc009.remaining-review-release.v1' -or $j.status -cne 'REVIEW_OK' -or $j.request_id -notmatch '^[a-f0-9-]{36}$') { throw 'HELD: invalid independent review receipt' }
 $a=$j.parts.$Part
 if ($a.runner_sha256 -cne $Sha -or $a.packet_sha256 -cne $PacketSha) { throw 'STOP: reviewed artifact identities do not match' }
}
function Get-Prior([string]$Kind,[int]$Seconds) {
 $p=Join-Path $Rcpt ($Kind+'-OK.json')
 Assert-NoReparse $p
 $j=Get-Content -LiteralPath $p -Raw -Encoding UTF8 | ConvertFrom-Json
 $age=([datetime]::UtcNow-[datetime]::Parse($j.utc).ToUniversalTime()).TotalSeconds
 if ($j.task -cne $Task -or $j.step -cne $Kind -or $j.status -cne 'OK' -or $j.runner_sha256 -cne $Sha -or $j.packet_sha256 -cne $PacketSha -or $age -lt -60 -or $age -gt $Seconds) { throw ('STOP: missing/stale '+$Kind) }
 return $j
}
function Read-Result([string]$Out,[string]$Err,[int]$Exit,[string]$Mode) {
 if ($Exit -ne 0) { throw 'STOP: remote result failed or unknown; no retry or rollback' }
 $stderr=@(Split-Lines $Err)
 $warning='WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.'
 if ($stderr.Count -gt 1 -or @($stderr | Where-Object { $_ -cne $warning }).Count -ne 0) { throw 'STOP: unexpected stderr requires assessment' }
 $lines=@(Split-Lines $Out)
 if ($Out.Length -gt 262144 -or @($lines | Where-Object { $_ -cmatch '^pkt_fail=' }).Count) { throw 'STOP: invalid bounded output' }
 $records=@($lines | Where-Object { $_.StartsWith('ACC009_RESULT=') })
 if ($records.Count -ne 1) { throw 'STOP: missing or duplicate runner result' }
 $known=@($lines | Where-Object { $_ -cmatch '^(pkt_php=/usr/local/bin/php|pkt_php_version=8\.0\.[0-9]+|pkt_cache_removed_php=[0-9]+|pkt_cache_dirs_and_nonphp=retained)$' })
 if ($known.Count+$records.Count -ne $lines.Count) { throw 'STOP: unexpected output line' }
 if (@($lines | Where-Object { $_ -ceq 'pkt_php=/usr/local/bin/php' }).Count -ne 1 -or @($lines | Where-Object { $_ -cmatch '^pkt_php_version=8\.0\.[0-9]+$' }).Count -ne 1) { throw 'STOP: PHP receipt missing' }
 $j=$records[0].Substring(14) | ConvertFrom-Json
 if ($j.schema -cne 'acc009.remaining.v1' -or $j.task -cne $Task -or $j.status -cne 'OK' -or $j.mode -cne $Mode -or $j.business_API_calls -cne 'NONE' -or $j.admission.probe_cleanup -cne 'ok') { throw 'STOP: runner receipt mismatch' }
 $rt=$j.admission.runtime
 if ($rt.php -cne '8.0.30' -or $rt.sapi -cne 'litespeed' -or $rt.opcache_enabled -isnot [bool] -or $rt.opcache_enabled -ne $true -or $rt.validate_timestamps -isnot [bool] -or $rt.validate_timestamps -ne $true -or ($rt.revalidate_freq -isnot [long] -and $rt.revalidate_freq -isnot [int]) -or $rt.revalidate_freq -ne 2) { throw 'STOP: web runtime changed' }
 if ($j.admission.metadata_sha256 -cnotmatch '^[a-f0-9]{64}$' -or @($j.admission.metadata.fields).Count -ne 27 -or @($j.admission.metadata.mapping).Count -ne 0) { throw 'STOP: incomplete metadata admission' }
 if (($Task -ceq 'ACC-009-N2' -or $Metadata) -and ($j.admission.sender_identity_unchanged -isnot [bool] -or $j.admission.sender_identity_unchanged -ne $true)) { throw 'STOP: configured sender identity admission missing' }
 if ($Mode -ceq '--dry-run') {
  if ($j.action -cne 'planned' -or $j.changed_files -ne 0 -or $j.backup -cne 'none' -or $j.db_access -cne 'READ_ONLY_METADATA') { throw 'STOP: preflight was not unchanged planned state' }
 } else {
  if ($j.self_deleted -ne $true -or $j.backup -cnotmatch ('^/home2/boosters/_patch_backups/'+[regex]::Escape($Task)+'-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$')) { throw 'STOP: exact apply backup/self-delete mismatch' }
  if ($Metadata) {
   if ($j.action -cne 'metadata_applied' -or $j.changed_files -ne 0 -or $j.business_rows_changed -ne 0 -or $j.db_access -cne 'METADATA_ONLY_INSERTS' -or @($j.inserted_field_ids.psobject.Properties).Count -ne 3) { throw 'STOP: metadata effect mismatch' }
   $keys=@($j.inserted_field_ids.psobject.Properties.Name | Sort-Object)
   if (($keys -join ',') -cne 'patronymic,telegram,viber' -or $j.after_metadata_sha256 -cnotmatch '^[a-f0-9]{64}$' -or $j.setting_id -lt 1) { throw 'STOP: incomplete inserted identities' }
   $ids=@($j.inserted_field_ids.psobject.Properties.Value)
   if (@($ids | Where-Object { ($_ -isnot [long] -and $_ -isnot [int]) -or $_ -lt 1 }).Count -or @($ids | Select-Object -Unique).Count -ne 3) { throw 'STOP: invalid inserted field identities' }
  } else {
   if ($j.action -cne 'applied' -or $j.changed_files -ne $TargetCount -or $j.db_access -cne 'READ_ONLY_METADATA') { throw 'STOP: source effect mismatch' }
   if (@($lines | Where-Object { $_ -cmatch '^pkt_cache_removed_php=[0-9]+$' }).Count -ne 1 -or @($lines | Where-Object { $_ -ceq 'pkt_cache_dirs_and_nonphp=retained' }).Count -ne 1) { throw 'STOP: source changed but cache result missing; no Apply retry' }
  }
 }
 return $j
}
function Assert-Transport([string]$Out,[string]$Err,[int]$Exit,[string]$Kind) {
 if ($Exit -ne 0 -or $Err -ne '') { throw 'STOP: transport admission failed' }
 $lines=@(Split-Lines $Out)
 $tail=if($Kind -ceq 'Upload'){'^pkt_upload=(scp_required|already_present_same_hash)$'}else{'^(pkt_runner_sha256='+$Sha+'|pkt_runner_lint=ok)$'}
 $allowed='^(pkt_php=/usr/local/bin/php|pkt_php_version=8\.0\.[0-9]+)$'
 if (@($lines | Where-Object { $_ -cnotmatch $tail -and $_ -cnotmatch $allowed }).Count -ne 0) { throw 'STOP: unexpected transport output' }
 if (@($lines | Where-Object { $_ -ceq 'pkt_php=/usr/local/bin/php' }).Count -ne 1 -or @($lines | Where-Object { $_ -cmatch '^pkt_php_version=8\.0\.[0-9]+$' }).Count -ne 1) { throw 'STOP: transport PHP receipt mismatch' }
 if ($Kind -ceq 'Upload') {
  if (@($lines | Where-Object { $_ -cmatch $tail }).Count -ne 1) { throw 'STOP: Upload disposition mismatch' }
 } else {
  if (@($lines | Where-Object { $_ -ceq ('pkt_runner_sha256='+$Sha) }).Count -ne 1 -or @($lines | Where-Object { $_ -ceq 'pkt_runner_lint=ok' }).Count -ne 1) { throw 'STOP: Verify identity mismatch' }
 }
}
$Upload=@'
if [ -e "$F" ] || [ -L "$F" ]; then
 hash_ok || die destination_not_identical
 echo pkt_upload=already_present_same_hash
else echo pkt_upload=scp_required
fi
'@
$Verify=@'
hash_ok || die runner_identity
"$PHPBIN" -n -l "$F" >/dev/null 2>&1 || die runner_lint
echo "pkt_runner_sha256=$SHA"
echo pkt_runner_lint=ok
'@
$Run=@'
hash_ok || die runner_identity
cd "$W" || die cwd
source_gate() {
 set +e
 out=$("$PHPBIN" "$F" __MODE__ '__AUTH__')
 code=$?
 set -e
 printf '%s\n' "$out"
 [ "$code" -eq 0 ] || return "$code"
 if [ '__MODE__' = --apply ] && [ '0' = 0 ]; then
  printf '%s\n' "$out" | "$PHPBIN" -n -r '$x=stream_get_contents(STDIN); $prefix="ACC009_RESULT="; if(substr($x,0,strlen($prefix))!==$prefix)exit(1); $j=json_decode(substr($x,strlen($prefix)),true); exit(is_array($j)&&($j["status"]??"")==="OK"&&($j["task"]??"")==="ACC-009-N2"&&($j["action"]??"")==="applied"&&($j["changed_files"]??-1)===18&&($j["self_deleted"]??false)===true?0:1);' || return 4
 fi
}
source_gate __CACHE__
'@
$Cache=@'
&& {
 T=/home2/boosters/ocartdata/storage/cache/template
 { [ -d "$T" ] && [ ! -L "$T" ] && [ "$(real "$T")" = "$T" ]; } || die source_changed_cache_path_STOP
 # Enumerate first, validate every candidate, then delete only regular template PHP.
 list=$(mktemp "$S/ACC-009-cache.XXXXXXXX") || die source_changed_cache_list_STOP
 if ! find "$T" -type f -name '*.php' -print0 > "$list"; then rm -f -- "$list"; die source_changed_cache_scan_STOP; fi
 while IFS= read -r -d '' path; do
  case "$path" in "$T/"*.php) ;; *) rm -f -- "$list"; die source_changed_cache_member_STOP ;; esac
  { [ -f "$path" ] && [ ! -L "$path" ] && [ "$(real "$path")" = "$path" ]; } || { rm -f -- "$list"; die source_changed_cache_member_STOP; }
 done < "$list"
 count=0
 while IFS= read -r -d '' path; do
  { [ -f "$path" ] && [ ! -L "$path" ] && [ "$(real "$path")" = "$path" ]; } || { rm -f -- "$list"; die source_changed_cache_drift_STOP; }
  rm -- "$path" || { rm -f -- "$list"; die source_changed_cache_remove_STOP; }; count=$((count+1))
 done < "$list"
 rm -- "$list" || die source_changed_cache_list_cleanup_STOP
 echo "pkt_cache_removed_php=$count"
 echo pkt_cache_dirs_and_nonphp=retained
}
'@
$record=$null;$r=$null
try {
 Assert-Release
 $null=Read-LocalRunner
 if (-not (Test-Path -LiteralPath $Rcpt)) { $null=New-Item -ItemType Directory -Path $Rcpt -Force }
 Assert-NoReparse $Rcpt
 if (Test-Path -LiteralPath (Join-Path $Rcpt 'Apply-attempt.json')) { throw 'STOP: Apply was attempted; this packet is consumed' }
 $record=[ordered]@{task=$Task;step=$Step;utc=[datetime]::UtcNow.ToString('o');runner_sha256=$Sha;packet_sha256=$PacketSha;status='STOP'}
 if ($Step -ceq 'Upload') {
  $r=Invoke-Remote $Upload
  Assert-Transport $r.Out $r.Err $r.Exit 'Upload'
  if ($r.Out -match 'pkt_upload=scp_required') { $copy=Invoke-OwnerScp; if ($copy.Exit -ne 0) { throw 'STOP: SCP failed; no next step' } }
 } elseif ($Step -ceq 'Verify') {
  $null=Get-Prior 'Upload' 7200
  $r=Invoke-Remote $Verify
  Assert-Transport $r.Out $r.Err $r.Exit 'Verify'
 } else {
  $null=Get-Prior 'Verify' 7200
  $mode='--dry-run';$auth='';$cleanup=''
  if ($Step -ceq 'Apply') {
   $prior=Get-Prior 'Preflight' 1800
   $mode='--apply'
   if ($Metadata) {
    if (-not $DbBackupConfirmed -or -not (Test-Path -LiteralPath $DbBackupPath -PathType Leaf)) { throw 'STOP: fresh full DB backup and explicit confirmation required' }
    Assert-NoReparse $DbBackupPath
    $item=Get-Item -LiteralPath $DbBackupPath
    $age=([datetime]::UtcNow-$item.LastWriteTimeUtc).TotalSeconds
    if ($age -lt -60 -or $age -gt 3600 -or $item.Length -lt 1) { throw 'STOP: owner backup is not recent/nonempty' }
    $a=[ordered]@{task=$Task;owner_go=$true;backup_sha256=(Get-FileHash -LiteralPath $DbBackupPath -Algorithm SHA256).Hash.ToLowerInvariant();backup_utc=$item.LastWriteTimeUtc.ToString('o');metadata_sha256=$prior.result.admission.metadata_sha256}
    $auth=[Convert]::ToBase64String($Utf8.GetBytes(($a | ConvertTo-Json -Compress)))
   } else { $cleanup=$Cache }
   # CreateNew is atomic: two owner invocations cannot both consume Apply.
   $marker=[IO.File]::Open((Join-Path $Rcpt 'Apply-attempt.json'),[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
   try { $markerBytes=$Utf8.GetBytes(($record | ConvertTo-Json -Depth 8));$marker.Write($markerBytes,0,$markerBytes.Length);$marker.Flush() } finally { $marker.Dispose() }
  }
  $body=$Run.Replace('__MODE__',$mode).Replace('__AUTH__',$auth).Replace('__CACHE__',$cleanup)
  $r=Invoke-Remote $body
  $record['result']=Read-Result $r.Out $r.Err $r.Exit $mode
 }
 $record['status']='OK';$record['exit']=$r.Exit;$record['stdout']=$r.Out;$record['stderr']=$r.Err;$record['remote_script_sha256']=$r.ScriptSha
 $json=$record | ConvertTo-Json -Depth 24
 [IO.File]::WriteAllText((Join-Path $Rcpt ($Step+'-OK.json')),$json,$Utf8)
 Write-Output $r.Out
 Write-Output ('step='+$Step+' status=OK; no next step started')
} catch {
 $failure=$_.Exception.Message
 if ($null -ne $record) {
  try {
   $record['status']='STOP';$record['error']=$failure
   if ($null -ne $r) { $record['exit']=$r.Exit;$record['stdout']=$r.Out;$record['stderr']=$r.Err;$record['remote_script_sha256']=$r.ScriptSha }
   $failed=Join-Path $Rcpt ($Step+'-STOP-'+[guid]::NewGuid().ToString('N')+'.json')
   [IO.File]::WriteAllText($failed,($record | ConvertTo-Json -Depth 24),$Utf8)
   Write-Output ('failure_receipt='+$failed)
   if ($null -ne $r) { Write-Output $r.Out }
  } catch { Write-Output 'failure_receipt_save_failed; preserve the terminal output and stop' }
 }
 Write-Error -Message $failure -ErrorAction Continue
 Write-Output 'STOP. Do not retry Apply, run rollback, create a shipment, or change authentication/key setup automatically.'
 exit 1
}
