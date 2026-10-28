#requires -Version 5.1
# OWNER ONLY. Separate Upload -> Verify -> Apply. No agent SSH/key/server access.
# Rollback is a separate owner choice: RollbackUpload -> RollbackVerify -> Rollback.
[CmdletBinding()]
param([Parameter(Mandatory=$true)][ValidateSet('Upload','Verify','Apply','RollbackUpload','RollbackVerify','Rollback')][string]$Step)
Set-StrictMode -Version 2.0
$ErrorActionPreference='Stop'
$Root='C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Name='RD-17-WP1A_profile-receiver-r3_20261026.php'
$Sha='ef0d7a7d87c58340b73cfdbb0fc26a497920adb7c38eeceaf8913dbe803f8e85'
$Local=Join-Path $Root ('patches\'+$Name)
$Receipts=Join-Path $Root 'work\rd17-main-20261026\owner-profile-r3-receipts'
$StateFile=Join-Path $Receipts 'state.json'
$Utf8=New-Object Text.UTF8Encoding($false)
$Ssh=Join-Path ([Environment]::SystemDirectory) 'OpenSSH\ssh.exe'
$Scp=Join-Path ([Environment]::SystemDirectory) 'OpenSSH\scp.exe'
$Options=@('-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes','-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no','-o','ForwardAgent=no','-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')
$PacketSha=(Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant()
if ((Get-FileHash -LiteralPath $Local -Algorithm SHA256).Hash.ToLowerInvariant() -cne $Sha) { throw 'STOP: local runner hash mismatch.' }
if (!(Test-Path -LiteralPath $Receipts)) { [void](New-Item -ItemType Directory -Path $Receipts) }
$State=@{}
if (Test-Path -LiteralPath $StateFile) {
    $Object=ConvertFrom-Json ([IO.File]::ReadAllText($StateFile))
    foreach ($Property in $Object.PSObject.Properties) { $State[$Property.Name]=$Property.Value }
    if ($State['runnerSha'] -cne $Sha) { throw 'STOP: state belongs to different executable bytes.' }
    if ($State['packetSha'] -cne $PacketSha) { throw 'STOP: packet changed since the previous owner step.' }
    if ($State.ContainsKey('php') -and ([string]$State['php']) -cnotmatch '^/[A-Za-z0-9_./-]+$') { throw 'STOP: unsafe saved PHP path.' }
    if ($State.ContainsKey('admission') -and ([string]$State['admission']) -cnotmatch '^[a-f0-9]{64}$') { throw 'STOP: unsafe saved admission.' }
}
function Save-State { $State['runnerSha']=$Sha; $State['packetSha']=$PacketSha; [IO.File]::WriteAllText($StateFile,($State | ConvertTo-Json -Depth 8),$Utf8) }
function Invoke-LfBody([string]$Body) {
    $Body=$Body.Replace("`r`n","`n")
    if ($Body.Contains("`r")) { throw 'STOP: carriage return in remote body.' }
    $Arguments=$Options+@('booster','bash','-s')
    foreach ($Argument in $Arguments) { if ($Argument -cnotmatch '^[A-Za-z0-9_=/.-]+$') { throw 'STOP: unsafe native argument.' } }
    $Info=New-Object Diagnostics.ProcessStartInfo
    $Info.FileName=$Ssh; $Info.Arguments=($Arguments -join ' ')
    $Info.UseShellExecute=$false; $Info.CreateNoWindow=$true
    $Info.RedirectStandardInput=$true; $Info.RedirectStandardOutput=$true; $Info.RedirectStandardError=$false
    $Info.StandardOutputEncoding=$Utf8
    $Process=New-Object Diagnostics.Process; $Process.StartInfo=$Info
    try {
        [void]$Process.Start(); $Read=$Process.StandardOutput.ReadToEndAsync(); $Bytes=$Utf8.GetBytes($Body)
        $SendFailed=$false
        try { $Process.StandardInput.BaseStream.Write($Bytes,0,$Bytes.Length); $Process.StandardInput.BaseStream.Flush() } catch { $SendFailed=$true } finally { $Process.StandardInput.Close() }
        $Process.WaitForExit(); $Code=$Process.ExitCode
        if ($SendFailed -and $Code -eq 0) { $Code=-1 }
        return [pscustomobject]@{Code=$Code;Stdout=$Read.GetAwaiter().GetResult()}
    } finally { $Process.Dispose() }
}
function Run-Body([string]$Body) {
    $Result=Invoke-LfBody $Body
    $Path=Join-Path $Receipts ($Step+'-'+[DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffffffZ')+'.txt')
    [IO.File]::WriteAllText($Path,$Result.Stdout,$Utf8)
    Write-Host $Result.Stdout
    return $Result
}
$Common=@'
set +x
set -euo pipefail
export LC_ALL=C
umask 077
H=/home2/boosters
W=$H/public_html
S=$H/private/booster-patches
F=$S/RD-17-WP1A_profile-receiver-r3_20261026.php
SHA=__RUNNER_SHA__
die() { printf 'packet_error=%s\n' "$1"; exit 1; }
[[ "$HOME" == "$H" ]] || die home_changed
for d in "$H" "$W" "$H/private"; do
  [[ -d "$d" && ! -L "$d" && $(readlink -f -- "$d") == "$d" ]] || die unsafe_directory
done
if [[ ! -e "$S" && ! -L "$S" ]]; then mkdir -m 700 -- "$S"; fi
[[ -d "$S" && ! -L "$S" && $(readlink -f -- "$S") == "$S" ]] || die unsafe_stage
case "$S/" in "$W/"*) die stage_inside_webroot ;; esac
cd "$W"
P=$(command -v php) || die php_missing
[[ "$P" == /* && -f "$P" && -x "$P" ]] || die php_path_invalid
V=$("$P" -r 'echo PHP_VERSION;')
[[ "$V" =~ ^8\.0\.[0-9]+$ ]] || die php_not_8_0
printf 'php_path=%s\nphp_version=%s\n' "$P" "$V"
hash_ok() {
  [[ -f "$F" && ! -L "$F" && $(readlink -f -- "$F") == "$F" ]] || die staged_file_invalid
  actual=$(sha256sum -- "$F")
  [[ ${actual:0:64} == "$SHA" ]] || die staged_hash_mismatch
}
'@
$Common=$Common.Replace('__RUNNER_SHA__',$Sha)+"`n"
$Cache=@'
"$P" -d display_errors=0 -d log_errors=0 <<'RD17_WP1A_CACHE_PHP'
<?php
set_error_handler(static function () { throw new RuntimeException(); });
try {
  ob_start(); require './config.php'; ob_end_clean();
  $expected='/home2/boosters/ocartdata/storage/cache/template/';
  if (!defined('DIR_CACHE') || DIR_CACHE . 'template/' !== $expected || !is_dir($expected) || is_link(rtrim($expected,'/')) || str_replace('\\','/',realpath($expected)?:'')!==rtrim($expected,'/')) throw new RuntimeException();
  $files=[];
  $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($expected,FilesystemIterator::SKIP_DOTS));
  foreach ($iterator as $file) {
    if ($file->isLink()) throw new RuntimeException();
    if ($file->isFile() && substr($file->getFilename(),-4)==='.php') {
      $path=str_replace('\\','/',$file->getRealPath()?:'');
      if (!is_string($path) || strpos($path,$expected)!==0) throw new RuntimeException();
      $files[]=$path;
    }
  }
  foreach ($files as $file) {
    if (is_link($file) || str_replace('\\','/',realpath($file)?:'')!==$file || !unlink($file)) throw new RuntimeException();
  }
  echo 'template_cache_removed=',count($files),"\ntemplate_cache_cleanup=ok\n";
} catch (Throwable $error) {
  while (ob_get_level()) ob_end_clean();
  echo "packet_error=template_cache_cleanup_failed\n"; exit(1);
}
RD17_WP1A_CACHE_PHP
[[ ! -e "$F" && ! -L "$F" ]] || die runner_did_not_self_delete
printf 'staged_runner_absent=yes\n'
'@

if ($Step -eq 'Upload' -or $Step -eq 'RollbackUpload') {
    if ($Step -eq 'Upload' -and $State.ContainsKey('applyAttempted')) { throw 'STOP: apply already attempted; do not replay.' }
    if ($Step -eq 'RollbackUpload' -and !$State.ContainsKey('backup')) { throw 'STOP: no recorded backup; send the apply receipt for targeted assessment.' }
    $Result=Run-Body ($Common+'[[ ! -e "$F" && ! -L "$F" ]] || die staged_file_exists' + "`nprintf 'stage_ready=yes\n'`n")
    if ($Result.Code -ne 0 -or $Result.Stdout -notmatch '(?m)^stage_ready=yes$') { throw 'STOP: private staging check failed. Preserve the receipt.' }
    & $Scp @Options $Local ('booster:private/booster-patches/'+$Name)
    if ($LASTEXITCODE -ne 0) { throw 'STOP: upload failed; do not retry automatically.' }
    $State['uploaded']=$true; Save-State
    Write-Host ('RD17_WP1A_'+$Step.ToUpperInvariant()+'_OK')
    Write-Host $(if ($Step -eq 'Upload') {'Next: run the same packet with -Step Verify.'} else {'Next: -Step RollbackVerify.'})
    exit
}

if ($Step -eq 'Verify') {
    if ($State.ContainsKey('applyAttempted')) { throw 'STOP: apply already attempted; use its saved receipt.' }
    $Body=$Common+'hash_ok'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 -l "$F"'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 "$F" --check'+"`n"
    $Result=Run-Body $Body
    $Admission=[regex]::Match($Result.Stdout,'(?m)^admission_sha256=([a-f0-9]{64})$')
    $Php=[regex]::Match($Result.Stdout,'(?m)^php_path=(/[A-Za-z0-9_./-]+)$')
    if ($Result.Code -ne 0 -or !$Admission.Success -or !$Php.Success -or $Result.Stdout -notmatch '(?m)^production_writes=0$' -or $Result.Stdout -notmatch '(?m)^done=ok$') { throw 'STOP: verification/admission not accepted. No apply; send the saved receipt.' }
    $State['admission']=$Admission.Groups[1].Value; $State['php']=$Php.Groups[1].Value; $State['verified']=$true; Save-State
    Write-Host 'RD17_WP1A_VERIFY_OK'
    Write-Host 'Next owner deployment action: -Step Apply. This writes the seven admitted targets.'
    exit
}

if ($Step -eq 'Apply') {
    if (!$State.ContainsKey('verified') -or !$State['verified'] -or $State.ContainsKey('applyAttempted')) { throw 'STOP: verified one-use admission required; never replay an apply attempt.' }
    $State['applyAttempted']=$true; Save-State
    $Body=$Common+'hash_ok'+"`n"+'[[ "$P" == ''__PHP__'' ]] || die php_path_changed'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 "$F" --apply --admission=__ADMISSION__ && '+$Cache+"`n"
    $Body=$Body.Replace('__PHP__',[string]$State['php']).Replace('__ADMISSION__',[string]$State['admission'])
    $Result=Run-Body $Body
    $Backup=[regex]::Match($Result.Stdout,'(?m)^backup=(/home2/boosters/public_html/_patch_backups/RD-17-WP1A_profile-receiver_20261026-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12})$')
    if ($Backup.Success) { $State['backup']=$Backup.Groups[1].Value; Save-State }
    if ($Result.Code -ne 0 -or !$Backup.Success -or $Result.Stdout -notmatch '(?m)^changed_files=7$' -or $Result.Stdout -notmatch '(?m)^self_deleted=yes$' -or $Result.Stdout -notmatch '(?m)^template_cache_cleanup=ok$' -or $Result.Stdout -notmatch '(?m)^staged_runner_absent=yes$') { throw 'STOP: apply/cache result unresolved. Preserve receipts; do not replay. Send the result.' }
    $State['applied']=$true; Save-State
    Write-Host 'RD17_WP1A_APPLY_OK_OWNER_QA_REQUIRED'
    Write-Host ('Backup: '+$State['backup'])
    exit
}

if (!$State.ContainsKey('backup')) { throw 'STOP: exact backup missing. Send the apply receipt; never guess a path.' }
$BackupPath=[string]$State['backup']
if ($BackupPath -cnotmatch '^/home2/boosters/public_html/_patch_backups/RD-17-WP1A_profile-receiver_20261026-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$') { throw 'STOP: unsafe saved backup.' }
if ($Step -eq 'RollbackVerify') {
    $Body=$Common+'hash_ok'+"`n"+'[[ "$P" == ''__PHP__'' ]] || die php_path_changed'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 -l "$F"'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 "$F" --rollback-check=__BACKUP__'+"`n"
    $Body=$Body.Replace('__PHP__',[string]$State['php'])
    $Result=Run-Body ($Body.Replace('__BACKUP__',$BackupPath))
    if ($Result.Code -ne 0 -or $Result.Stdout -notmatch '(?m)^rollback_preflight=ok$' -or $Result.Stdout -notmatch '(?m)^done=ok$') { throw 'STOP: rollback preflight failed; nothing restored. Send the receipt.' }
    $State['rollbackVerified']=$true; Save-State
    Write-Host 'RD17_WP1A_ROLLBACK_VERIFY_OK'
    exit
}
if (!$State.ContainsKey('rollbackVerified') -or !$State['rollbackVerified'] -or $State.ContainsKey('rollbackAttempted')) { throw 'STOP: one-use rollback verification required.' }
$State['rollbackAttempted']=$true; Save-State
$Body=$Common+'hash_ok'+"`n"+'[[ "$P" == ''__PHP__'' ]] || die php_path_changed'+"`n"+'"$P" -d display_errors=0 -d log_errors=0 "$F" --rollback=__BACKUP__ && '+$Cache+"`n"
$Body=$Body.Replace('__PHP__',[string]$State['php'])
$Result=Run-Body ($Body.Replace('__BACKUP__',$BackupPath))
if ($Result.Code -ne 0 -or $Result.Stdout -notmatch '(?m)^rollback=ok$' -or $Result.Stdout -notmatch '(?m)^template_cache_cleanup=ok$' -or $Result.Stdout -notmatch '(?m)^staged_runner_absent=yes$') { throw 'STOP: rollback/cache result unresolved; do not replay. Send the receipt.' }
Write-Host 'RD17_WP1A_ROLLBACK_OK_OWNER_QA_REQUIRED'
