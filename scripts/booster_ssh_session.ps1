#Requires -Version 5.1
<#
OWNER-ONLY Booster work-period ssh-agent helper. Agents must never execute this file in the owner environment.
Setup  : elevated owner shell, once. Sets the EXISTING Windows ssh-agent service to Automatic and starts it.
         Installs nothing, never stops or disables the service, and loads no key.
Unlock : normal owner shell. Loads the existing encrypted ~/.ssh/booster_deploy through the native ssh-add prompt.
Lock   : normal owner shell. Removes ONLY that key (ssh-add -d <companion .pub>) and verifies it is absent.
Status : normal owner shell. Reports whether that key is loaded.
The agent is account/service-wide, so other same-user processes can use a loaded key. Closing PowerShell,
stopping the service or rebooting does NOT unload it. No lifetime (-t) is used. Always run Lock after work.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Setup','Unlock','Lock','Status')]
    [string]$Action
)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$OpenSsh = Join-Path ([Environment]::SystemDirectory) 'OpenSSH'   # built-in WINDIR/System32/OpenSSH
$SshAdd  = Join-Path $OpenSsh 'ssh-add.exe'
$KeyGen  = Join-Path $OpenSsh 'ssh-keygen.exe'
$SshDir  = Join-Path $env:USERPROFILE '.ssh'
$Key     = Join-Path $SshDir 'booster_deploy'
$Pub     = $Key + '.pub'
$Cert    = $Key + '-cert.pub'
$Script:Svc = 'unknown'
$target = $null

function Out-Receipt([string]$Status, [string]$Reason, [string]$Loaded) {
    [ordered]@{ schema = 'booster_ssh_session.v1'; action = $Action; status = $Status
                service = $Script:Svc; target_loaded = $Loaded; reason = $Reason } | ConvertTo-Json -Compress
    $code = @{ ok = 0; warn = 1; stop = 2 }[$Status]
    exit $code
}

function Test-Plain([string]$Path, [switch]$Dir) {
    try { $i = Get-Item -LiteralPath $Path -Force -ErrorAction Stop } catch { return $false }
    if ($i.Attributes -band [IO.FileAttributes]::ReparsePoint) { return $false }
    return ([bool]$i.PSIsContainer -eq [bool]$Dir)
}

# Child-process runner: removes SSH_AUTH_SOCK (forces the native default agent pipe) and SSH_ASKPASS for the
# child only. Captured output stays in memory and is never printed. Interactive mode keeps the native console prompt.
function Invoke-Native([string]$Exe, [string[]]$ArgList, [switch]$Interactive) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Exe
    $psi.Arguments = (@($ArgList | ForEach-Object { '"' + $_ + '"' }) -join ' ')
    $psi.UseShellExecute = $false
    $psi.WorkingDirectory = $OpenSsh
    foreach ($n in @('SSH_AUTH_SOCK', 'SSH_ASKPASS')) {
        if ($psi.EnvironmentVariables.ContainsKey($n)) { $psi.EnvironmentVariables.Remove($n) }
    }
    $psi.EnvironmentVariables['SSH_ASKPASS_REQUIRE'] = 'never'
    $capture = -not $Interactive
    $psi.RedirectStandardInput = $capture
    $psi.RedirectStandardOutput = $capture
    $psi.RedirectStandardError = $capture
    $p = [System.Diagnostics.Process]::Start($psi)
    $out = ''; $err = ''
    if ($capture) {
        $p.StandardInput.Close()
        $task = $p.StandardError.ReadToEndAsync()
        $out = $p.StandardOutput.ReadToEnd()
        $err = $task.GetAwaiter().GetResult()
    }
    $p.WaitForExit()
    $code = $p.ExitCode
    $p.Dispose()
    return [pscustomobject]@{ Code = $code; Out = $out; Err = $err }
}

function Get-Fp([string]$Text) {
    @([regex]::Matches($Text, '(?m)^\d+ (SHA256:[A-Za-z0-9+/]{43})(?: |\r?$)') | ForEach-Object { $_.Groups[1].Value })
}

# Public fingerprint only (ssh-keygen -l never decrypts). Returns $null unless exactly one fingerprint is found.
function Get-PubFp([string]$Path) {
    $r = Invoke-Native $KeyGen @('-l', '-E', 'sha256', '-f', $Path)
    $f = @(Get-Fp $r.Out)
    if ($r.Code -eq 0 -and $f.Count -eq 1) { return $f[0] }
    return $null
}

# Agent fingerprints, compared in memory only. Returns an array (possibly empty), or $null if the state is unknown.
function Get-AgentFps {
    $r = Invoke-Native $SshAdd @('-l', '-E', 'sha256')
    $f = @(Get-Fp $r.Out)
    if ($r.Code -eq 0 -and $f.Count -gt 0) { return ,$f }
    if ($r.Code -eq 1 -and $f.Count -eq 0 -and $r.Err.Trim().Length -eq 0 -and $r.Out -match 'has no identities') { return ,@() }
    return $null
}

try {
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)
    $serviceInfo = Get-Service -Name 'ssh-agent' -ErrorAction SilentlyContinue
    if ($null -eq $serviceInfo) { $Script:Svc = 'absent' } elseif ($serviceInfo.Status -eq 'Running') { $Script:Svc = 'running' } else { $Script:Svc = 'not_running' }

    if ($Action -eq 'Setup') {
        if ($Script:Svc -eq 'absent') { Out-Receipt 'stop' 'service_absent_no_install' 'not_checked' }
        $auto = ("$($serviceInfo.StartType)" -eq 'Automatic')
        if ($auto -and $Script:Svc -eq 'running') { Out-Receipt 'ok' 'service_already_ready' 'not_checked' }
        if (-not $isAdmin) { Out-Receipt 'stop' 'setup_requires_admin' 'not_checked' }
        if (-not $auto) { Set-Service -Name 'ssh-agent' -StartupType Automatic }
        if ($Script:Svc -ne 'running') { Start-Service -Name 'ssh-agent' }
        if ((Get-Service -Name 'ssh-agent').Status -eq 'Running') { $Script:Svc = 'running'; Out-Receipt 'ok' 'service_ready' 'not_checked' }
        $Script:Svc = 'not_running'
        Out-Receipt 'stop' 'service_start_failed' 'not_checked'
    }

    # Key actions: normal owner shell only, so the key never lands in a different account's agent store.
    if ($isAdmin) { Out-Receipt 'stop' 'elevated_key_action_rejected' 'unknown' }
    if ($Script:Svc -eq 'absent') { Out-Receipt 'stop' 'service_absent_no_install' 'unknown' }
    if ($Script:Svc -ne 'running') {
        if ($Action -eq 'Lock') { Out-Receipt 'stop' 'service_not_running_removal_unverified' 'unknown' }
        Out-Receipt 'stop' 'service_not_running_run_setup' 'unknown'
    }
    if (-not ((Test-Plain $SshAdd) -and (Test-Plain $KeyGen))) { Out-Receipt 'stop' 'native_openssh_tools_missing' 'unknown' }
    if (-not ((Test-Plain $SshDir -Dir) -and (Test-Plain $Pub))) { Out-Receipt 'stop' 'public_companion_missing_or_not_plain' 'unknown' }
    $target = Get-PubFp $Pub
    if ($null -eq $target) { Out-Receipt 'stop' 'public_companion_unreadable' 'unknown' }
    $before = Get-AgentFps
    if ($null -eq $before) {
        if ($Action -eq 'Lock') { Out-Receipt 'stop' 'agent_unreachable_removal_unverified' 'unknown' }
        Out-Receipt 'stop' 'agent_unreachable' 'unknown'
    }
    $isLoaded = ($before -ccontains $target)

    if ($Action -eq 'Status') {
        if ($isLoaded) { Out-Receipt 'ok' 'target_loaded' 'yes' }
        Out-Receipt 'ok' 'target_absent' 'no'
    }

    if ($Action -eq 'Unlock') {
        if ($isLoaded) { Out-Receipt 'ok' 'target_already_loaded' 'yes' }
        if (-not (Test-Plain $Key)) { Out-Receipt 'stop' 'private_key_missing_or_not_plain' 'no' }
        if (Test-Path -LiteralPath $Cert) { Out-Receipt 'stop' 'unexpected_certificate_companion' 'no' }
        # Pre-load pair check. Older builds may fall back to the .pub here; the post-load check below is authoritative.
        if ((Get-PubFp $Key) -cne $target) { Out-Receipt 'stop' 'key_pair_mismatch_or_unreadable' 'no' }
        $null = Invoke-Native $SshAdd @('-q', $Key) -Interactive
        $after = Get-AgentFps
        if ($null -eq $after) { Out-Receipt 'stop' 'post_load_state_unknown_run_lock' 'unknown' }
        $new = @($after | Where-Object { $before -cnotcontains $_ })
        if ($after -ccontains $target) {
            if ($new.Count -gt 1) { Out-Receipt 'warn' 'target_loaded_with_extra_identity' 'yes' }
            Out-Receipt 'ok' 'target_loaded' 'yes'
        }
        if ($new.Count -eq 0) { Out-Receipt 'stop' 'load_failed_or_cancelled' 'no' }
        # Something other than the expected fingerprint was loaded: roll it back by its own file, then report loudly.
        $null = Invoke-Native $SshAdd @('-q', '-d', $Key)
        $final = Get-AgentFps
        if ($null -ne $final -and @($new | Where-Object { $final -ccontains $_ }).Count -eq 0) {
            Out-Receipt 'stop' 'unexpected_identity_loaded_then_removed' 'no'
        }
        Out-Receipt 'stop' 'unexpected_identity_may_remain_owner_must_remove' 'unknown'
    }

    # Lock: exact single-identity delete via the companion public key. Never -D.
    if (-not $isLoaded) { Out-Receipt 'ok' 'target_already_absent' 'no' }
    $null = Invoke-Native $SshAdd @('-q', '-d', $Pub)
    $after = Get-AgentFps
    if ($null -eq $after) { Out-Receipt 'stop' 'post_remove_state_unknown' 'unknown' }
    if ($after -ccontains $target) { Out-Receipt 'stop' 'target_still_loaded' 'yes' }
    $kept = (@($before | Where-Object { $_ -cne $target }) | Sort-Object -CaseSensitive) -join ','
    if ($kept -cne ((@($after) | Sort-Object -CaseSensitive) -join ',')) { Out-Receipt 'warn' 'target_removed_other_identities_changed' 'no' }
    Out-Receipt 'ok' 'target_removed' 'no'
} catch {
    $loaded = if ($Action -eq 'Setup') { 'not_checked' } else { 'unknown' }
    try {
        if ($Action -ne 'Setup' -and $null -ne $target) {
            $s = Get-AgentFps
            if ($null -ne $s) { $loaded = @('no', 'yes')[[int]($s -ccontains $target)] }
        }
    } catch { }
    Out-Receipt 'stop' 'unexpected_error_check_status' $loaded
}
