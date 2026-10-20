# Owner-run bounded patch deployment packet: MONO-CHECKOUT-PRECHECK-20261020
# Usage (normal 64-bit powershell.exe 5.1, cwd = repo root):
#   powershell.exe -NoProfile -File <this packet> -Action Upload
#   powershell.exe -NoProfile -File <this packet> -Action Verify
#   powershell.exe -NoProfile -File <this packet> -Action Apply
#   powershell.exe -NoProfile -File <this packet> -Action Rollback -BackupDir <exact backup_dir printed by Apply>
# Upload, Verify, Apply and Rollback are separate owner actions. Each action revalidates its own prerequisites.
# This packet holds no persistent local state. It never reads ssh config or keys and never executes the provenance file.
# Production source changes only through the reviewed runner, and only on an explicit owner Apply or Rollback.
# Output is bounded ASCII. Remote stderr and raw exception text are never printed.
# Success ends packet_action=<upload_ok|verify_ok|apply_ok|rollback_ok>.
# Failure ends packet_action=STOP reason=<fixed code> and exits with code 1.
# Host QA remains owner-pending: PHP 8.0 runtime, cache, theme and the customer incident are not proven by this packet.
param(
    [string]$Action = '',
    [string]$BackupDir = ''
)
$ExtraArgCount = $args.Count
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$RepoRoot   = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$PatchName  = 'MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020.php'
$PatchDir   = $RepoRoot + '\patches'
$PatchPath  = $PatchDir + '\' + $PatchName
$PatchSha   = '855eadfa570db3c7942e2bfd6c4cbdf7b87c601560d34344a73a6dac48ecfc32'
$PatchSize  = 16318
$ProvDir    = $RepoRoot + '\diagnostics'
$ProvPath   = $ProvDir + '\ACC-009_C1-H1_owner-agent-PS_20261020.ps1'
$ProvSha    = '2bfb2f1c31669578a1758141d8f68ad8cc3c618b00abe4866ce807e4a85837d9'
$TimeoutMs  = 900000
$BackupDirRe = '^/[A-Za-z0-9_./-]+/public_html/_patch_backups/MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020-[0-9]{8}-[0-9]{6}-[0-9a-f]{8}\z'
$ReceiptRe  = '^(patch|mode|admission|target|database_touched|cache_cleared_by_runner|done|already_applied|changed_files|backup_dir|backup_manifest_sha256|recovery_runner_sha256|self_delete|source_restored|restored_files|error)=[A-Za-z0-9_:/. =-]{0,700}\z'
$FixedRe    = '^[A-Za-z_]{1,64}=[A-Za-z0-9_./:-]{0,200}\z'
$SshOptions = @(
    'BatchMode=yes', 'StrictHostKeyChecking=yes', 'IdentitiesOnly=yes',
    'IdentityAgent=//./pipe/openssh-ssh-agent', 'AddKeysToAgent=no', 'ForwardAgent=no',
    'PreferredAuthentications=publickey', 'PasswordAuthentication=no',
    'KbdInteractiveAuthentication=no', 'ClearAllForwardings=yes', 'ForwardX11=no',
    'UpdateHostKeys=no', 'PermitLocalCommand=no', 'ControlPath=none',
    'ConnectTimeout=15', 'ServerAliveInterval=15', 'ServerAliveCountMax=2'
)

$RemoteCommon = @'
set -u
set -f
umask 077
export LC_ALL=C
B='MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020.php'
RBN='MONO-CHECKOUT-PRECHECK-20261020_private_rollback_runner.php'
PSHA='855eadfa570db3c7942e2bfd6c4cbdf7b87c601560d34344a73a6dac48ecfc32'
PSIZE='16318'
PHPBIN='/usr/local/bin/php'
TPL='/home2/boosters/ocartdata/storage/cache/template'
T1='catalog/controller/checkout/confirm.php'
T1B='df3d6b75e261cc986646170864b7327e4eb56e7745ec3bf879cf86fe8cab869d'
T1A='6365c2a36bb575848cf4f578252f3642454ab8e37626ce7c1c14eabf4f60ac17'
T2='catalog/view/template/checkout/credit_confirm.twig'
T2B='6c095eaee5bc8bd5b5d074d4e990c208e03b936be3d63e639b0b055e597624ad'
T2A='ae9062872ecc3acc51c0117eaf14c790e935a7b0c08130001dc5888071216b66'
D1='catalog/view/template/checkout/checkout.twig'
D1S='e334d78880c00022095a8767f5788edeb450044f3fcbad0e3f674faee2acd34a'
D2='catalog/model/checkout/order.php'
D2S='0324c221cb75cb1aaa04d8b28be5865b295aff3b727139791e03413342c3a800'
CFGPHP='error_reporting(0); ini_set("display_errors", "0"); ob_start(); try { require "./config.php"; } catch (Throwable $e) { while (ob_get_level() > 0) { ob_end_clean(); } echo "cfg=load_failed"; exit(4); } while (ob_get_level() > 0) { ob_end_clean(); } $s = defined("DIR_STORAGE") ? DIR_STORAGE : null; $c = defined("DIR_CACHE") ? DIR_CACHE : null; $ok = ($s === "/home2/boosters/ocartdata/storage/" && $c === "/home2/boosters/ocartdata/storage/cache/" && !defined("DIR_MODIFICATION")); echo $ok ? "cfg=ok" : "cfg=mismatch"; exit($ok ? 0 : 5);'
ROOT=''
PRIV=''
STG=''
STAGED=''
pkt_begin() { printf '%s\n' '@@PKT_BEGIN@@'; }
emit() { printf '%s=%s\n' "$1" "$2"; }
stop() { emit remote_result STOP; emit remote_reason "$1"; printf '%s\n' '@@PKT_END@@'; exit 3; }
finish_ok() { emit remote_result ok; printf '%s\n' '@@PKT_END@@'; exit 0; }
has_line() { printf '%s\n' "$1" | grep -qxF -e "$2"; }
emit_receipt() { [ -n "$1" ] || return 0; printf '%s\n' "$1" | sed -e 's/^/runner|/'; }
chain_dir() {
  local rest cur part
  case "$1" in /*) ;; *) return 1 ;; esac
  rest="${1#/}"
  cur=''
  [ -n "$rest" ] || return 1
  while [ -n "$rest" ]; do
    part="${rest%%/*}"
    if [ "$part" = "$rest" ]; then rest=''; else rest="${rest#*/}"; fi
    case "$part" in ''|.|..) return 1 ;; esac
    cur="$cur/$part"
    if [ -L "$cur" ]; then return 1; fi
    [ -d "$cur" ] || return 1
  done
  return 0
}
reg_exact() {
  local s h
  if [ -L "$1" ]; then return 1; fi
  [ -f "$1" ] || return 1
  if [ "$3" != '-' ]; then
    s=$(stat -c %s -- "$1" 2>/dev/null) || return 1
    [ "$s" = "$3" ] || return 1
  fi
  h=$(sha256sum < "$1" 2>/dev/null) || return 1
  h="${h%% *}"
  [ "$h" = "$2" ]
}
root_file_exact() { chain_dir "$ROOT/${1%/*}" || return 1; reg_exact "$ROOT/$1" "$2" -; }
mode_is() { local m; m=$(stat -c %a -- "$1" 2>/dev/null) || return 1; [ "$m" = "$2" ]; }
base_checks() {
  local u
  case "${HOME:-}" in /?*) ;; *) stop home_not_absolute ;; esac
  case "$HOME" in *[!A-Za-z0-9/_.-]*|*//*|*/|*/./*|*/../*|*/.|*/..) stop home_path_invalid ;; esac
  ROOT="$HOME/public_html"
  PRIV="$HOME/private"
  STG="$PRIV/booster-patches"
  STAGED="$STG/$B"
  chain_dir "$HOME" || stop home_chain_not_plain_directories
  chain_dir "$ROOT" || stop root_chain_not_plain_directories
  chain_dir "$PRIV" || stop private_chain_not_plain_directories
  if [ ! -e "$STG" ] && [ ! -L "$STG" ]; then stop staging_missing_owner_setup_required; fi
  chain_dir "$STG" || stop staging_chain_not_plain_directories
  mode_is "$STG" 700 || stop staging_mode_not_0700
  u=$(stat -c %u -- "$STG" 2>/dev/null) || stop staging_owner_unreadable
  [ "$u" = "$(id -u)" ] || stop staging_owner_mismatch
  emit staging private_0700_symlink_free
}
staged_admit() {
  reg_exact "$STAGED" "$PSHA" "$PSIZE" || stop staged_patch_not_exact
  mode_is "$STAGED" 600 || stop staged_patch_mode_not_0600
  emit staged_patch exact_sha256_size_mode_0600
}
tpl_check() {
  local l
  chain_dir "$TPL" || return 1
  l=$(find "$TPL" -mindepth 1 -type l -print -quit 2>/dev/null </dev/null) || return 1
  [ -z "$l" ]
}
cache_cleanup() {
  tpl_check || return 1
  find "$TPL" -mindepth 1 -type f -delete 2>/dev/null </dev/null || return 1
  return 0
}
runtime_checks() {
  local v c
  if [ ! -f "$PHPBIN" ] || [ ! -x "$PHPBIN" ]; then stop php_binary_missing; fi
  if [ -L "$ROOT/index.php" ] || [ ! -f "$ROOT/index.php" ]; then stop root_index_not_regular; fi
  if [ -L "$ROOT/config.php" ] || [ ! -f "$ROOT/config.php" ]; then stop root_config_not_regular; fi
  cd "$ROOT" || stop root_cd_failed
  v=$("$PHPBIN" -r 'echo PHP_SAPI, " ", PHP_VERSION;' 2>/dev/null </dev/null) || stop php_version_failed
  case "$v" in 'cli 8.0.'[0-9]|'cli 8.0.'[0-9][0-9]) ;; *) stop php_runtime_not_cli_8_0 ;; esac
  emit php_runtime usr_local_bin_php_cli_8.0_admitted
  c=$("$PHPBIN" -r "$CFGPHP" 2>/dev/null </dev/null) || stop config_read_or_values_failed
  [ "$c" = 'cfg=ok' ] || stop config_values_mismatch
  emit DIR_STORAGE /home2/boosters/ocartdata/storage/
  emit DIR_CACHE /home2/boosters/ocartdata/storage/cache/
  emit DIR_MODIFICATION UNDEFINED
  tpl_check || stop template_cache_dir_not_admitted
  emit template_cache_dir admitted_symlink_free
}
dependency_checks() {
  root_file_exact "$D1" "$D1S" || stop dependency_checkout_twig_not_exact
  emit dependency_checkout_twig exact_regular
  root_file_exact "$D2" "$D2S" || stop dependency_order_php_not_exact
  emit dependency_order_php exact_regular
}
'@

$RemoteUploadGuard = @'
main() {
  pkt_begin
  base_checks
  if [ -L "$STAGED" ]; then stop staged_destination_symlink; fi
  if [ -e "$STAGED" ]; then
    reg_exact "$STAGED" "$PSHA" "$PSIZE" || stop staged_destination_not_reviewed_hash
    emit staged_destination present_exact
  else
    emit staged_destination absent
  fi
  finish_ok
}
main </dev/null
stop main_returned_without_result
'@

$RemoteUploadPost = @'
main() {
  pkt_begin
  base_checks
  reg_exact "$STAGED" "$PSHA" "$PSIZE" || stop staged_patch_mismatch_after_upload
  chmod 0600 -- "$STAGED" 2>/dev/null || stop staged_chmod_failed
  staged_admit
  emit upload_source_cache_db_write none
  finish_ok
}
main </dev/null
stop main_returned_without_result
'@

$RemoteVerify = @'
main() {
  local PRE RC
  pkt_begin
  base_checks
  staged_admit
  runtime_checks
  dependency_checks
  "$PHPBIN" -l "$STAGED" >/dev/null 2>&1 </dev/null || stop runner_lint_failed
  emit runner_lint ok
  cd "$ROOT" || stop root_cd_failed
  PRE=$("$PHPBIN" "$STAGED" --preflight 2>/dev/null </dev/null); RC=$?
  emit_receipt "$PRE"
  [ "$RC" -eq 0 ] || stop runner_preflight_failed
  has_line "$PRE" 'done=preflight_ok' || stop runner_preflight_receipt_invalid
  if has_line "$PRE" 'admission=before'; then emit verify_admission before
  elif has_line "$PRE" 'admission=after'; then emit verify_admission after
  else stop runner_admission_unknown; fi
  emit verify_source_cache_write none
  finish_ok
}
main </dev/null
stop main_returned_without_result
'@

$RemoteApply = @'
main() {
  local PRE RC RUNOUT RUN_OK GONE CACHE_OK
  pkt_begin
  base_checks
  staged_admit
  runtime_checks
  dependency_checks
  "$PHPBIN" -l "$STAGED" >/dev/null 2>&1 </dev/null || stop runner_lint_failed
  emit runner_lint ok
  cd "$ROOT" || stop root_cd_failed
  PRE=$("$PHPBIN" "$STAGED" --preflight 2>/dev/null </dev/null); RC=$?
  emit_receipt "$PRE"
  [ "$RC" -eq 0 ] || stop runner_preflight_failed
  has_line "$PRE" 'done=preflight_ok' || stop runner_preflight_receipt_invalid
  if has_line "$PRE" 'admission=before'; then emit apply_admission before
  elif has_line "$PRE" 'admission=after'; then emit apply_admission after_idempotent_noop
  else stop runner_admission_unknown; fi
  RUNOUT=''
  RUN_OK=0
  GONE=0
  CACHE_OK=0
  cd "$ROOT" && RUNOUT=$("$PHPBIN" "$STAGED" --apply 2>/dev/null </dev/null) && RUN_OK=1 && has_line "$RUNOUT" 'self_delete=ok' && has_line "$RUNOUT" 'done=ok' && [ ! -e "$STAGED" ] && [ ! -L "$STAGED" ] && GONE=1 && cache_cleanup && CACHE_OK=1
  emit_receipt "$RUNOUT"
  [ "$RUN_OK" = 1 ] || stop apply_runner_failed_review_receipt
  [ "$GONE" = 1 ] || stop apply_receipt_or_self_delete_not_confirmed
  [ "$CACHE_OK" = 1 ] || stop template_cache_cleanup_failed_after_runner_success
  emit template_cache_cleared yes
  root_file_exact "$T1" "$T1A" || stop apply_posthash_confirm_php
  root_file_exact "$T2" "$T2A" || stop apply_posthash_credit_confirm_twig
  emit source_posthash after_both
  if [ -e "$STAGED" ] || [ -L "$STAGED" ]; then stop staged_patch_present_after_apply; fi
  emit staged_patch_after_apply absent
  emit database_operations none
  finish_ok
}
main </dev/null
stop main_returned_without_result
'@

$RemoteRollback = @'
BD_B64='__BD_B64__'
main() {
  local BD BDRE RSRC RB PRE RC RUNOUT RB_OK CACHE_OK
  pkt_begin
  base_checks
  BD=$(printf '%s' "$BD_B64" | base64 -d 2>/dev/null) || stop backup_dir_decode_failed
  BDRE='^/[A-Za-z0-9_./-]+/public_html/_patch_backups/MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020-[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$'
  [[ "$BD" =~ $BDRE ]] || stop backup_dir_grammar_invalid
  [ "$BD" = "$ROOT/_patch_backups/${BD##*/}" ] || stop backup_dir_not_exact_root_child
  chain_dir "$BD" || stop backup_dir_chain_not_plain_directories
  if [ -L "$BD/manifest.json" ] || [ ! -f "$BD/manifest.json" ]; then stop backup_manifest_not_regular; fi
  RSRC="$BD/recovery_runner.php"
  reg_exact "$RSRC" "$PSHA" "$PSIZE" || stop backup_recovery_runner_not_reviewed_hash
  runtime_checks
  RB="$STG/$RBN"
  if [ -L "$RB" ]; then
    stop rollback_runner_destination_symlink
  elif [ -e "$RB" ]; then
    reg_exact "$RB" "$PSHA" "$PSIZE" || stop rollback_runner_destination_unknown_file
  else
    ( set -C; cat -- "$RSRC" > "$RB" ) 2>/dev/null || stop rollback_runner_copy_failed
  fi
  chmod 0600 -- "$RB" 2>/dev/null || stop rollback_runner_chmod_failed
  reg_exact "$RB" "$PSHA" "$PSIZE" || stop rollback_runner_hash_mismatch
  mode_is "$RB" 600 || stop rollback_runner_mode_not_0600
  emit rollback_runner private_exact_sha256_mode_0600
  "$PHPBIN" -l "$RB" >/dev/null 2>&1 </dev/null || stop rollback_runner_lint_failed
  emit runner_lint ok
  cd "$ROOT" || stop root_cd_failed
  PRE=$("$PHPBIN" "$RB" "--rollback-preflight=$BD" 2>/dev/null </dev/null); RC=$?
  emit_receipt "$PRE"
  [ "$RC" -eq 0 ] || stop rollback_preflight_failed
  has_line "$PRE" 'done=rollback_preflight_ok' || stop rollback_preflight_receipt_invalid
  RUNOUT=''
  RB_OK=0
  CACHE_OK=0
  cd "$ROOT" && RUNOUT=$("$PHPBIN" "$RB" "--rollback=$BD" 2>/dev/null </dev/null) && has_line "$RUNOUT" 'done=rollback_ok' && RB_OK=1 && cache_cleanup && CACHE_OK=1
  emit_receipt "$RUNOUT"
  [ "$RB_OK" = 1 ] || stop rollback_runner_failed_review_receipt
  [ "$CACHE_OK" = 1 ] || stop template_cache_cleanup_failed_after_rollback
  emit template_cache_cleared yes
  root_file_exact "$T1" "$T1B" || stop rollback_posthash_confirm_php
  root_file_exact "$T2" "$T2B" || stop rollback_posthash_credit_confirm_twig
  emit source_posthash before_both
  reg_exact "$RB" "$PSHA" "$PSIZE" || stop rollback_runner_changed_before_cleanup
  rm -f -- "$RB" 2>/dev/null || stop rollback_runner_cleanup_failed
  if [ -e "$RB" ] || [ -L "$RB" ]; then stop rollback_runner_cleanup_failed; fi
  emit rollback_runner_cleanup removed
  emit backup_artifacts kept
  emit database_operations none
  finish_ok
}
main </dev/null
stop main_returned_without_result
'@

function Write-Line([string]$Text) {
    [Console]::Out.WriteLine($Text)
}

function Stop-Packet([string]$Code) {
    if ($Code -cnotmatch '^[a-z0-9_]{1,100}\z') { $Code = 'invalid_stop_code' }
    [Console]::Out.WriteLine('packet_action=STOP reason=' + $Code)
    [Console]::Out.Flush()
    exit 1
}

function ConvertTo-WinArg([string]$Value) {
    if ($Value.Length -gt 0 -and $Value.IndexOfAny([char[]]@([char]32, [char]9, [char]34)) -lt 0) { return $Value }
    $sb = New-Object System.Text.StringBuilder
    [void]$sb.Append([char]34)
    $bs = 0
    foreach ($ch in $Value.ToCharArray()) {
        if ($ch -eq [char]92) { $bs++; continue }
        if ($ch -eq [char]34) {
            [void]$sb.Append([char]92, (2 * $bs + 1))
            [void]$sb.Append([char]34)
            $bs = 0
            continue
        }
        if ($bs -gt 0) { [void]$sb.Append([char]92, $bs); $bs = 0 }
        [void]$sb.Append($ch)
    }
    if ($bs -gt 0) { [void]$sb.Append([char]92, (2 * $bs)) }
    [void]$sb.Append([char]34)
    return $sb.ToString()
}

function Assert-PlainChain([string]$Path, [bool]$LeafIsFile, [string]$Code) {
    $full = $null
    try { $full = [System.IO.Path]::GetFullPath($Path) } catch { Stop-Packet $Code }
    if (-not [string]::Equals($full, $Path, [System.StringComparison]::Ordinal)) { Stop-Packet $Code }
    $chain = New-Object 'System.Collections.Generic.List[string]'
    $cur = $Path
    while (-not [string]::IsNullOrEmpty($cur)) {
        $chain.Insert(0, $cur)
        $cur = [System.IO.Path]::GetDirectoryName($cur)
    }
    for ($i = 0; $i -lt $chain.Count; $i++) {
        $attr = 0
        try { $attr = [int][System.IO.File]::GetAttributes($chain[$i]) } catch { Stop-Packet $Code }
        if (($attr -band 0x400) -ne 0) { Stop-Packet $Code }
        $isDir = (($attr -band 0x10) -ne 0)
        if ($i -eq ($chain.Count - 1) -and $LeafIsFile) {
            if ($isDir) { Stop-Packet $Code }
        } elseif (-not $isDir) {
            Stop-Packet $Code
        }
    }
}

function Get-Sha256Hex([string]$Path, [string]$Code) {
    $hex = $null
    try {
        $fs = [System.IO.File]::Open($Path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::Read)
        try {
            $sha = [System.Security.Cryptography.SHA256]::Create()
            try { $bytes = $sha.ComputeHash($fs) } finally { $sha.Dispose() }
        } finally { $fs.Dispose() }
        $sb = New-Object System.Text.StringBuilder
        foreach ($b in $bytes) { [void]$sb.Append($b.ToString('x2')) }
        $hex = $sb.ToString()
    } catch { Stop-Packet $Code }
    return $hex
}

function Assert-LocalPatch {
    Assert-PlainChain $PatchPath $true 'local_patch_path_not_plain'
    $len = -1
    try { $len = (New-Object System.IO.FileInfo -ArgumentList $PatchPath).Length } catch { Stop-Packet 'local_patch_unreadable' }
    if ($len -ne $PatchSize) { Stop-Packet 'local_patch_size_mismatch' }
    $h = Get-Sha256Hex $PatchPath 'local_patch_unreadable'
    if ($h -cne $PatchSha) { Stop-Packet 'local_patch_sha256_mismatch' }
}

function Invoke-Native([string]$Exe, [string[]]$Argv, [string]$WorkDir) {
    foreach ($a in $Argv) { if ($a -match '[\x00-\x1F]') { Stop-Packet 'native_argument_invalid' } }
    $quoted = New-Object 'System.Collections.Generic.List[string]'
    foreach ($a in $Argv) { $quoted.Add((ConvertTo-WinArg $a)) }
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Exe
    $psi.Arguments = [string]::Join(' ', $quoted.ToArray())
    if ($psi.Arguments.Length -gt 31000) { Stop-Packet 'native_command_too_long' }
    $psi.WorkingDirectory = $WorkDir
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $psi.RedirectStandardInput = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.StandardOutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $psi.StandardErrorEncoding = [System.Text.UTF8Encoding]::new($false)
    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi
    $started = $false
    try { $started = $proc.Start() } catch { $started = $false }
    if (-not $started) { Stop-Packet 'native_start_failed' }
    try { $proc.StandardInput.Close() } catch { }
    $outTask = $proc.StandardOutput.ReadToEndAsync()
    $errTask = $proc.StandardError.ReadToEndAsync()
    $exited = $false
    try { $exited = $proc.WaitForExit($TimeoutMs) } catch { $exited = $false }
    if (-not $exited) {
        try { $proc.Kill() } catch { }
        Stop-Packet 'native_timeout_or_wait_failed'
    }
    $out = ''
    $code = -1
    try {
        $proc.WaitForExit()
        [void]$outTask.Wait(60000)
        [void]$errTask.Wait(60000)
        $out = [string]$outTask.Result
        $null = $errTask.Result
        $code = $proc.ExitCode
    } catch { Stop-Packet 'native_capture_failed' }
    try { $proc.Dispose() } catch { }
    return New-Object PSObject -Property @{ Code = $code; Out = $out }
}

function Read-RemoteOutput($Result) {
    $out = [string]$Result.Out
    if ($out.Length -gt 262144) { Stop-Packet 'remote_output_too_large' }
    $normalized = $out.Replace("`r`n", "`n")
    if ($normalized.Contains([string][char]13)) { Stop-Packet 'remote_lone_carriage_return' }
    $lines = $normalized.Split([char]10)
    if ($lines.Length -lt 3 -or $lines[0] -cne '@@PKT_BEGIN@@') {
        if ($Result.Code -eq 255) { Stop-Packet 'ssh_connection_or_auth_failed' }
        Stop-Packet 'remote_no_packet_output'
    }
    $begin = 0
    $fixedKeys = @('staging','staged_patch','staged_destination','upload_source_cache_db_write','php_runtime','DIR_STORAGE','DIR_CACHE','DIR_MODIFICATION','template_cache_dir','dependency_checkout_twig','dependency_order_php','runner_lint','verify_admission','verify_source_cache_write','apply_admission','template_cache_cleared','source_posthash','staged_patch_after_apply','database_operations','rollback_runner','rollback_runner_cleanup','backup_artifacts','remote_result','remote_reason')
    $kv = @{}
    $ended = $false
    $rejected = 0
    for ($i = $begin + 1; $i -lt $lines.Length; $i++) {
        $l = $lines[$i]
        if ($l -ceq '@@PKT_END@@') { $ended = $true; break }
        if ($l.StartsWith('runner|', [System.StringComparison]::Ordinal)) {
            $c = $l.Substring(7)
            if ($c -cmatch $ReceiptRe) { Write-Line $c } else { $rejected++ }
            continue
        }
        if ($l -cmatch $FixedRe) {
            $idx = $l.IndexOf('=')
            $k = $l.Substring(0, $idx)
            $v = $l.Substring($idx + 1)
            if ($fixedKeys -cnotcontains $k -or $kv.ContainsKey($k)) { Stop-Packet 'remote_unknown_or_duplicate_key' }
            $kv[$k] = $v
            if ($k -cne 'remote_result' -and $k -cne 'remote_reason') { Write-Line $l }
        } else {
            $rejected++
        }
    }
    if (-not $ended) { Stop-Packet 'remote_output_truncated' }
    for ($j = $i + 1; $j -lt $lines.Length; $j++) {
        if ($j -ne ($lines.Length - 1) -or $lines[$j] -cne '') { Stop-Packet 'remote_trailing_output' }
    }
    if ($rejected -gt 0) { Stop-Packet 'remote_or_runner_line_rejected' }
    if (-not $kv.ContainsKey('remote_result')) { Stop-Packet 'remote_result_missing' }
    if ($kv['remote_result'] -ceq 'STOP') {
        $reason = 'unknown'
        if ($kv.ContainsKey('remote_reason') -and $kv['remote_reason'] -cmatch '^[a-z0-9_]{1,90}\z') { $reason = $kv['remote_reason'] }
        Stop-Packet ('remote_' + $reason)
    }
    if ($kv['remote_result'] -cne 'ok') { Stop-Packet 'remote_result_invalid' }
    if ($Result.Code -ne 0) { Stop-Packet 'remote_exit_nonzero' }
    return $kv
}

function Invoke-RemoteScript([string]$Body, [string]$BdB64) {
    $text = $RemoteCommon + [string][char]10 + $Body
    $text = $text.Replace('__BD_B64__', $BdB64)
    $text = $text.Replace([string][char]13, '')
    foreach ($ch in $text.ToCharArray()) {
        $n = [int]$ch
        if (($n -lt 32 -and $n -ne 10 -and $n -ne 9) -or $n -gt 126) { Stop-Packet 'remote_script_not_ascii' }
    }
    $b64 = [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes($text))
    $remote = 'printf %s ' + $b64 + ' | base64 -d | env -u BASH_ENV LC_ALL=C bash --noprofile --norc -s'
    $argv = New-Object 'System.Collections.Generic.List[string]'
    $argv.Add('-T')
    $argv.Add('-n')
    foreach ($o in $SshOptions) { $argv.Add('-o'); $argv.Add($o) }
    $argv.Add('booster')
    $argv.Add($remote)
    $r = Invoke-Native $script:SshExe $argv.ToArray() $RepoRoot
    return (Read-RemoteOutput $r)
}

function Assert-Key($Map, [string]$Key, [string]$Value, [string]$Code) {
    if (-not $Map.ContainsKey($Key) -or $Map[$Key] -cne $Value) { Stop-Packet $Code }
}

function Invoke-Packet {
    if ($ExtraArgCount -ne 0) { Stop-Packet 'unexpected_arguments' }
    $act = ''
    switch ($Action.ToLowerInvariant()) {
        'upload'   { $act = 'upload' }
        'verify'   { $act = 'verify' }
        'apply'    { $act = 'apply' }
        'rollback' { $act = 'rollback' }
        default    { Stop-Packet 'action_invalid' }
    }
    $bdB64 = ''
    if ($act -ceq 'rollback') {
        if ($BackupDir.Length -lt 1 -or $BackupDir.Length -gt 300) { Stop-Packet 'backup_dir_length_invalid' }
        if ($BackupDir -cnotmatch $BackupDirRe) { Stop-Packet 'backup_dir_grammar_invalid' }
        if ($BackupDir.Contains('//') -or $BackupDir.Contains('/./') -or $BackupDir.Contains('..')) { Stop-Packet 'backup_dir_grammar_invalid' }
        $bdB64 = [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes($BackupDir))
    } elseif ($BackupDir.Length -ne 0) {
        Stop-Packet 'backup_dir_only_for_rollback'
    }

    if ($PSVersionTable.PSVersion.Major -ne 5 -or $PSVersionTable.PSVersion.Minor -ne 1) { Stop-Packet 'powershell_5_1_required' }
    if ($PSVersionTable.PSEdition -cne 'Desktop') { Stop-Packet 'powershell_desktop_required' }
    if ($Host.Name -cne 'ConsoleHost') { Stop-Packet 'console_host_required_not_ise' }
    if (-not [Environment]::Is64BitProcess) { Stop-Packet 'powershell_64bit_required' }
    $sys = [Environment]::GetFolderPath([Environment+SpecialFolder]::System)
    $expectedPs = $sys + '\WindowsPowerShell\v1.0\powershell.exe'
    $selfExe = ''
    try { $selfExe = [System.Diagnostics.Process]::GetCurrentProcess().MainModule.FileName } catch { Stop-Packet 'host_process_unverifiable' }
    if (-not [string]::Equals($selfExe, $expectedPs, [System.StringComparison]::OrdinalIgnoreCase)) { Stop-Packet 'system32_powershell_exe_required' }

    $loc = Get-Location
    if ($loc.Provider.Name -cne 'FileSystem') { Stop-Packet 'cwd_not_filesystem' }
    if (-not [string]::Equals($loc.ProviderPath, $RepoRoot, [System.StringComparison]::OrdinalIgnoreCase)) { Stop-Packet 'cwd_not_pinned_repo' }
    [Environment]::CurrentDirectory = $RepoRoot

    Assert-PlainChain $RepoRoot $false 'local_repo_not_plain_directory'
    Assert-PlainChain $PatchDir $false 'local_patch_dir_not_plain_directory'
    Assert-PlainChain $ProvDir $false 'local_provenance_dir_not_plain_directory'
    Assert-PlainChain $ProvPath $true 'local_provenance_path_not_plain'
    if ([string]::IsNullOrEmpty($PSCommandPath)) { Stop-Packet 'packet_path_unknown' }
    Assert-PlainChain $PSCommandPath $true 'local_packet_path_not_plain'

    $script:SshExe = $sys + '\OpenSSH\ssh.exe'
    $script:ScpExe = $sys + '\OpenSSH\scp.exe'
    Assert-PlainChain $script:SshExe $true 'system32_ssh_exe_missing_or_not_plain'
    Assert-PlainChain $script:ScpExe $true 'system32_scp_exe_missing_or_not_plain'

    Assert-LocalPatch
    $ph = Get-Sha256Hex $ProvPath 'local_provenance_unreadable'
    if ($ph -cne $ProvSha) { Stop-Packet 'local_provenance_sha256_mismatch' }

    Write-Line 'packet_id=MONO-CHECKOUT-PRECHECK-20261020_recover-missing-order_20261020'
    Write-Line ('packet_mode=' + $act)
    Write-Line 'local_patch=exact_path_size_sha256'
    Write-Line 'local_provenance=exact_sha256_not_executed'

    if ($act -ceq 'upload') {
        $pre = Invoke-RemoteScript $RemoteUploadGuard ''
        if ($pre.ContainsKey('staged_destination') -and $pre['staged_destination'] -ceq 'absent') {
            Assert-LocalPatch
            $scpArgs = New-Object 'System.Collections.Generic.List[string]'
            $scpArgs.Add('-q')
            foreach ($o in $SshOptions) { $scpArgs.Add('-o'); $scpArgs.Add($o) }
            $scpArgs.Add($PatchName)
            $scpArgs.Add('booster:private/booster-patches/' + $PatchName)
            $sr = Invoke-Native $script:ScpExe $scpArgs.ToArray() $PatchDir
            if ($sr.Code -ne 0) { Stop-Packet 'scp_upload_failed' }
            Write-Line 'upload_transfer=scp_exact_patch_only'
        } elseif ($pre.ContainsKey('staged_destination') -and $pre['staged_destination'] -ceq 'present_exact') {
            Write-Line 'upload_transfer=skipped_already_exact'
        } else {
            Stop-Packet 'upload_guard_result_invalid'
        }
        $post = Invoke-RemoteScript $RemoteUploadPost ''
        Assert-Key $post 'staged_patch' 'exact_sha256_size_mode_0600' 'upload_post_verify_invalid'
        Write-Line 'packet_action=upload_ok'
        exit 0
    }

    if ($act -ceq 'verify') {
        $v = Invoke-RemoteScript $RemoteVerify ''
        Assert-Key $v 'staged_patch' 'exact_sha256_size_mode_0600' 'verify_staged_not_confirmed'
        Assert-Key $v 'php_runtime' 'usr_local_bin_php_cli_8.0_admitted' 'verify_runtime_not_confirmed'
        Assert-Key $v 'DIR_STORAGE' '/home2/boosters/ocartdata/storage/' 'verify_config_not_confirmed'
        Assert-Key $v 'DIR_CACHE' '/home2/boosters/ocartdata/storage/cache/' 'verify_config_not_confirmed'
        Assert-Key $v 'DIR_MODIFICATION' 'UNDEFINED' 'verify_config_not_confirmed'
        Assert-Key $v 'template_cache_dir' 'admitted_symlink_free' 'verify_cache_dir_not_confirmed'
        Assert-Key $v 'dependency_checkout_twig' 'exact_regular' 'verify_dependency_not_confirmed'
        Assert-Key $v 'dependency_order_php' 'exact_regular' 'verify_dependency_not_confirmed'
        Assert-Key $v 'runner_lint' 'ok' 'verify_lint_not_confirmed'
        Assert-Key $v 'verify_source_cache_write' 'none' 'verify_result_invalid'
        Write-Line 'packet_action=verify_ok'
        exit 0
    }

    if ($act -ceq 'apply') {
        $a = Invoke-RemoteScript $RemoteApply ''
        Assert-Key $a 'runner_lint' 'ok' 'apply_lint_not_confirmed'
        Assert-Key $a 'template_cache_cleared' 'yes' 'apply_cache_not_confirmed'
        Assert-Key $a 'source_posthash' 'after_both' 'apply_posthash_not_confirmed'
        Assert-Key $a 'staged_patch_after_apply' 'absent' 'apply_staged_absence_not_confirmed'
        Assert-Key $a 'database_operations' 'none' 'apply_result_invalid'
        Write-Line 'host_qa=pending_owner_mobile_checkout_bank_handoff_and_payment_regression'
        Write-Line 'packet_action=apply_ok'
        exit 0
    }

    if ($act -ceq 'rollback') {
        $r = Invoke-RemoteScript $RemoteRollback $bdB64
        Assert-Key $r 'rollback_runner' 'private_exact_sha256_mode_0600' 'rollback_runner_not_confirmed'
        Assert-Key $r 'template_cache_cleared' 'yes' 'rollback_cache_not_confirmed'
        Assert-Key $r 'source_posthash' 'before_both' 'rollback_posthash_not_confirmed'
        Assert-Key $r 'rollback_runner_cleanup' 'removed' 'rollback_cleanup_not_confirmed'
        Assert-Key $r 'backup_artifacts' 'kept' 'rollback_result_invalid'
        Write-Line 'host_qa=pending_owner_checkout_regression'
        Write-Line 'packet_action=rollback_ok'
        exit 0
    }

    Stop-Packet 'action_dispatch_failed'
}

$script:SshExe = ''
$script:ScpExe = ''
try {
    Invoke-Packet
} catch {
    [Console]::Out.WriteLine('packet_action=STOP reason=local_unexpected_error')
    [Console]::Out.Flush()
    exit 1
}
Stop-Packet 'packet_fell_through'
