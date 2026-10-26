#requires -Version 5.1
# OWNER ONLY. One streamed read-only diagnosis; no staged runner/state/target/cache writes.
[CmdletBinding()] param()
Set-StrictMode -Version 2.0
$ErrorActionPreference='Stop'
$Root='C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Source=Join-Path $Root 'diagnostics\RD-16-WP2_intake_20261026.php'
$Sha='f3b5cdc13a78b96ffdb92ee8d7ce297b530b4a03b8f6aad7d92777a4eeccd6ba'
$SourceBytes=[IO.File]::ReadAllBytes($Source)
$Hasher=[Security.Cryptography.SHA256]::Create()
try { $ActualSha=([BitConverter]::ToString($Hasher.ComputeHash($SourceBytes))).Replace('-','').ToLowerInvariant() } finally { $Hasher.Dispose() }
if ($ActualSha -cne $Sha) { throw 'STOP: diagnostic hash mismatch.' }
$Utf8=New-Object Text.UTF8Encoding($false)
$Ssh=Join-Path ([Environment]::SystemDirectory) 'OpenSSH\ssh.exe'
if (!(Test-Path -LiteralPath $Ssh -PathType Leaf)) { throw 'STOP: existing ssh.exe missing; no attempt consumed.' }
$Options=@('-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes','-o','IdentityAgent=//./pipe/openssh-ssh-agent','-o','AddKeysToAgent=no','-o','ForwardAgent=no','-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no','-o','KbdInteractiveAuthentication=no')
$Receipts=Join-Path $Root 'work\rd16-wp2-20261026\owner-receipts-intake'
if (!(Test-Path -LiteralPath $Receipts)) { [void](New-Item -ItemType Directory -Path $Receipts) }
$Once=Join-Path $Receipts 'attempt.json'
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

$Body=@'
set +x
set -euo pipefail
export LC_ALL=C
[[ "$HOME" == /home2/boosters ]] || exit 1
[[ -d /home2/boosters/public_html && ! -L /home2/boosters/public_html && $(readlink -f -- /home2/boosters/public_html) == /home2/boosters/public_html ]] || exit 1
cd /home2/boosters/public_html
P=/usr/local/bin/php
[[ -x "$P" && $("$P" -r 'echo PHP_VERSION;') == 8.0.30 ]] || exit 1
exec "$P" -d display_errors=0 -d log_errors=0
'@
$Body+="`n"+$Utf8.GetString($SourceBytes).Replace("`r`n","`n")+"`n"
$Attempt=@{task='RD-16-WP2-intake-r1';phpSha=$Sha;packetSha=(Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash.ToLowerInvariant();startedUtc=[DateTime]::UtcNow.ToString('o');replayPermitted=$false} | ConvertTo-Json
$Handle=[IO.File]::Open($Once,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
try { $Bytes=$Utf8.GetBytes($Attempt); $Handle.Write($Bytes,0,$Bytes.Length); $Handle.Flush() } finally { $Handle.Dispose() }
$Result=Invoke-LfBody $Body
$Receipt=Join-Path $Receipts 'stdout.txt'
[IO.File]::WriteAllText($Receipt,$Result.Stdout,$Utf8)
if ($Result.Code -ne 0 -or $Result.Stdout -cnotmatch '\nRD16_WP2_INTAKE_OK\n\z') { throw 'STOP: inspect failed; keep attempt and receipt, do not retry.' }
$Lines=$Result.Stdout.TrimEnd("`n").Split("`n")
if ($Lines.Count -ne 2) { throw 'STOP: unexpected inspect receipt; do not retry.' }
$Data=ConvertFrom-Json $Lines[0]
if ($Data.task -cne 'RD-16-WP2-intake-r1' -or $Data.writes -cne 'none' -or $Data.customer_reads -cne 'none') { throw 'STOP: inspect contract mismatch.' }
Write-Host 'RD16_WP2_INTAKE_OK'
Write-Host ('Receipt: '+$Receipt)
Write-Host 'Send this terminal output; Codex will read the saved local receipt. No WP2 Apply exists yet; send this output.'
