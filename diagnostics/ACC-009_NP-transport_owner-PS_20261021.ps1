#requires -Version 5.1
<# OWNER ONLY. One Capture observation, no patch application or NP business call.
Stream exact local PHP bytes; temporary sealed endpoint then owned-byte cleanup.
No SSH/key/service changes. Use existing owner-controlled unlocked key.
Every outcome is retained. No repeat after an attempted Capture without assessment.
Exit0 = complete observations, NOT successful authenticated NP integration.
Nonzero = partial/unknown collection, STOP and send saved technical output.
#>
param([Parameter(Mandatory=$true)][ValidateSet('Capture')][string]$Step)
$ErrorActionPreference='Stop'
$Root='C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Name='ACC-009_NP-transport-inspect_20261021.php'
$Local=Join-Path $Root ('diagnostics\'+$Name)
$Sha='a80924850d1a40a81acaf6935fedca483cfeb3212c111176c7d09c93bbed5a3c'
$Bytes=16996
$Rcpt=Join-Path $Root 'work\acc009-np-transport-20261021\owner-receipts'
$Ssh=Join-Path $env:WINDIR 'System32\OpenSSH\ssh.exe'
$Alias='booster'
$Utf8=New-Object System.Text.UTF8Encoding($false)
$PacketSha=(Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant()
$SshOpt=@('-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-T','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes',
 '-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no','-o','ForwardAgent=no','-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')
$Common=@'
set -euo pipefail
export LC_ALL=C
umask 077
die() { echo "pkt_fail=$1"; exit 1; }
H=/home2/boosters;W=$H/public_html
{ [ "$HOME" = "$H" ] && [ "$(cd -P ~ && pwd)" = "$H" ]; } || die home
for d in "$H" "$W"; do { [ -d "$d" ] && [ ! -L "$d" ] && [ "$(readlink -f -- "$d")" = "$d" ]; } || die directory; done
PHPBIN=$(command -v php) || die php_missing
{ [ "$PHPBIN" = /usr/local/bin/php ] && [ -x "$PHPBIN" ]; } || die php_path
V=$("$PHPBIN" -n -r 'echo PHP_VERSION;') || die php_version
[[ "$V" =~ ^8\.0\.[0-9]+$ ]] || die php_version
echo "pkt_php=$PHPBIN"
echo "pkt_php_version=$V"
cd "$W"
'@
$Body=@'
CODE=$(cat <<'NPDIAG_CODE'
__CODE__
NPDIAG_CODE
)
echo pkt_diagnostic_sha256=a80924850d1a40a81acaf6935fedca483cfeb3212c111176c7d09c93bbed5a3c
set +e
"$PHPBIN" -r '$b=base64_decode($argv[1],true);if(!is_string($b)||strlen($b)!==(int)$argv[3]||hash("sha256",$b)!==$argv[2]||substr($b,0,5)!=="<?php"){fwrite(STDERR,"error=payload_identity\n");exit(1);}eval(substr($b,5));' "$CODE" a80924850d1a40a81acaf6935fedca483cfeb3212c111176c7d09c93bbed5a3c 16996
rc=$?
set -e
echo "pkt_diagnostic_exit=$rc"
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
function Split-Lines([string]$s) { @(([string]$s).Replace("`r", '') -split "`n" | Where-Object { $_ -ne '' }) }
function Test-Lines([string[]]$Got, [string[]]$Exp) {
  if (@($Got).Count -ne $Exp.Count) { return $false }
  for ($i = 0; $i -lt $Exp.Count; $i++) {
    if ($Exp[$i].StartsWith('~')) { if ($Got[$i] -cnotmatch $Exp[$i].Substring(1)) { return $false } }
    elseif ($Got[$i] -cne $Exp[$i]) { return $false }
  }
  $true
}


function Test-CaptureReceipt([string[]]$Lines) {
 if($Lines.Count -ne 6){return $false}
 if($Lines[0] -cne 'pkt_php=/usr/local/bin/php' -or $Lines[1] -cnotmatch '^pkt_php_version=8\.0\.[0-9]+$' -or $Lines[2] -cne "pkt_diagnostic_sha256=$Sha" -or -not $Lines[3].StartsWith('NPDIAG_JSON=') -or $Lines[4] -cne 'done=observed' -or $Lines[5] -cne 'pkt_diagnostic_exit=0'){return $false}
 try{$j=$Lines[3].Substring(12)|ConvertFrom-Json}catch{return $false}
 if($j.schema -cne 'acc009.np_transport.v1' -or $j.task -cne 'ACC-009-NP-TRANSPORT' -or $j.status -cne 'complete' -or $j.db_access -cne 'NONE' -or $j.business_API_calls -cne 'NONE' -or $j.source_mutations -ne 0 -or $j.probe_cleanup -cne 'ok' -or @($j.sources).Count -ne 3){return $false}
 $expected=@(
  @('extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php','2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93'),
  @('extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintacounterparty.php','44d138edca35b83eb7e124161cf8fc0fc19d084db8a635f32b901cc00b773bb7'),
  @('extension/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintanovaposhtaapi.php','729c3c6cee6b04f925967a9e42277b9702e51876b37d6df119ba40258b20b19c'))
 for($i=0;$i -lt 3;$i++){if($j.sources[$i].path -cne $expected[$i][0] -or $j.sources[$i].sha256 -cne $expected[$i][1] -or $j.sources[$i].matches -isnot [bool] -or -not $j.sources[$i].matches){return $false}}
 foreach($kind in @('cli','web')) {
  $r=$j.$kind;$sapi='cli';if($kind -ceq 'web'){$sapi='litespeed'}
  if($r.php -cnotmatch '^8\.0\.[0-9]+$' -or $r.sapi -cne $sapi -or @($r.samples).Count -ne 3){return $false}
  for($i=0;$i -lt 3;$i++){
   $s=$r.samples[$i]
   if($s.sample -cne @('default_1','default_2','IPv4')[$i] -or $s.method -cne 'HEAD' -or $s.curl_errno -isnot [int] -or $s.http_code -isnot [int] -or $s.timings.PSObject.Properties.Count -lt 1){return $false}
  }
 }
 return $true
}
$code=1;$status='FAILED';$r=$null;$err=$null
try {
 if(-not(Test-Path -LiteralPath $Rcpt -PathType Container)){Assert-NoReparse (Split-Path -Parent $Rcpt);[void][IO.Directory]::CreateDirectory($Rcpt)}
 Assert-NoReparse $Rcpt
 if(@(Get-ChildItem -LiteralPath $Rcpt -File -Filter 'Capture-*.json').Count -gt 0){throw 'CAPTURE_ALREADY_ATTEMPTED_STOP_NO_REPLAY'}
 $bytesLocal=Read-LocalRunner
 $run=$Body.Replace('__CODE__',[Convert]::ToBase64String($bytesLocal))
 [void](Save-Receipt ([ordered]@{task='ACC-009-NP-TRANSPORT';step='Capture';status='STARTED';diagnosticSha=$Sha;packetSha=$PacketSha;utc=[DateTime]::UtcNow.ToString('o')}) 'Capture')
 $status='STATE_UNKNOWN';$code=5
 $r=Invoke-Remote $run
 if($r.Exit -eq 0 -and $r.Err -ceq '' -and (Test-CaptureReceipt @(Split-Lines $r.Out))){$status='OBSERVED';$code=0}
 elseif($r.Exit -eq 2){$status='PARTIAL_STOP';$code=2}
}catch{$err=$_.Exception.Message}
if($r){Write-Host $r.Out;if($r.Err){Write-Host 'STDERR:';Write-Host $r.Err}}
$record=[ordered]@{task='ACC-009-NP-TRANSPORT';step='Capture';status=$status;localExit=$code;diagnosticSha=$Sha;packetSha=$PacketSha;utc=[DateTime]::UtcNow.ToString('o');error=$err;remoteExit=$(if($r){$r.Exit});remoteScriptSha256=$(if($r){$r.ScriptSha});stdout=$(if($r){$r.Out});stderr=$(if($r){$r.Err});interpretation='Observation only, not a repair or authenticated integration pass';noAutomaticReplay=$true}
try{$rp=Save-Receipt $record 'Capture'}catch{$rp='RECEIPT_SAVE_FAILED';$status='STATE_UNKNOWN';$code=5}
Write-Host "step=Capture status=$status exit=$code error=$err receipt=$rp"
Write-Host 'Send complete technical output. Do not repeat Capture or create a shipment to test.'
exit $code
