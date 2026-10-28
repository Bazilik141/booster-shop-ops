#requires -Version 5.1
# Owner only. Collect fixed sources, download, verify, then remove only the two
# exact private export artifacts. Never invokes a production patch or clears cache.
[CmdletBinding()]
param([Parameter(Mandatory=$true)][ValidateRange(1,2147483647)][int]$OrderId)
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$Repo = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Collector = Join-Path $Repo 'diagnostics\RD-18-WP4_display-source_collect_20261028.sh'
$CollectorSha = 'b47cc0be5e9f9d8bfd71df8fc83012c373c3814552bb25308631e9d58a43b0f4'
$Drop = Join-Path $Repo 'work\rd18-wp4-20261028\owner-display-drop-r1'
$ArchiveName = 'RD-18-display-source-20261028-r1.tar.gz'
$ManifestName = 'RD-18-display-source-20261028-r1.manifest.txt'
$Ssh = Join-Path ([Environment]::SystemDirectory) 'OpenSSH\ssh.exe'
$Scp = Join-Path ([Environment]::SystemDirectory) 'OpenSSH\scp.exe'
$Options = @('-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',
    '-o','IdentitiesOnly=yes','-o','IdentityAgent=//./pipe/openssh-ssh-agent',
    '-o','AddKeysToAgent=no','-o','ForwardAgent=no',
    '-o','PreferredAuthentications=publickey','-o','PasswordAuthentication=no',
    '-o','KbdInteractiveAuthentication=no')
if (!(Test-Path -LiteralPath $Ssh -PathType Leaf) -or !(Test-Path -LiteralPath $Scp -PathType Leaf)) { throw 'STOP: existing OpenSSH client missing; preserve setup.' }
$CollectorBytes = [IO.File]::ReadAllBytes($Collector)
$Hasher = [Security.Cryptography.SHA256]::Create()
try { $ReadSha = ([BitConverter]::ToString($Hasher.ComputeHash($CollectorBytes))).Replace('-', '').ToLowerInvariant() } finally { $Hasher.Dispose() }
if ($ReadSha -cne $CollectorSha) { throw 'STOP: collector hash mismatch.' }
if (Test-Path -LiteralPath $Drop) { throw 'STOP: local collection directory exists; do not replay. Send the existing result.' }
[void](New-Item -ItemType Directory -Path $Drop)
# Byte-exact stdin; a PowerShell string pipeline appends a Windows CRLF.
function Invoke-Rd18LfProcess([string]$Program, [string[]]$NativeArgs, [string]$Body) {
    $Body = $Body.Replace("`r`n", "`n")
    if ($Body.Contains("`r")) { throw 'STOP: carriage return in remote body.' }
    foreach ($Arg in $NativeArgs) {
        if ($Arg -cnotmatch '^[A-Za-z0-9_=/.-]+$') { throw 'STOP: unsafe native argument.' }
    }
    $Info = New-Object Diagnostics.ProcessStartInfo
    $Info.FileName = $Program
    $Info.Arguments = ($NativeArgs -join ' ')
    $Info.UseShellExecute = $false
    $Info.CreateNoWindow = $true
    $Info.RedirectStandardInput = $true
    $Info.RedirectStandardOutput = $true
    $Info.RedirectStandardError = $false
    $Info.StandardOutputEncoding = New-Object Text.UTF8Encoding($false)
    $Process = New-Object Diagnostics.Process
    $Process.StartInfo = $Info
    try {
        [void]$Process.Start()
        $Read = $Process.StandardOutput.ReadToEndAsync()
        $Bytes = (New-Object Text.UTF8Encoding($false)).GetBytes($Body)
        $SendFailed = $false
        try {
            $Process.StandardInput.BaseStream.Write($Bytes, 0, $Bytes.Length)
            $Process.StandardInput.BaseStream.Flush()
        } catch {
            $SendFailed = $true
        } finally {
            $Process.StandardInput.Close()
        }
        $Process.WaitForExit()
        $Code = $Process.ExitCode
        if ($SendFailed -and $Code -eq 0) { $Code = -1 }
        return [pscustomobject]@{ Code = $Code; Stdout = $Read.GetAwaiter().GetResult() }
    } finally {
        $Process.Dispose()
    }
}
$Body = [Text.Encoding]::UTF8.GetString($CollectorBytes).Replace("`r`n", "`n")
$Body=$Body.Replace('__RD18_ORDER_ID__',[string]$OrderId)
if ($Body.Contains("`r")) { throw 'STOP: carriage return in collector body.' }
$Collected = Invoke-Rd18LfProcess $Ssh ($Options + @('booster','bash','-s')) $Body
[IO.File]::WriteAllText((Join-Path $Drop 'owner-receipt-raw.txt'), $Collected.Stdout, (New-Object System.Text.UTF8Encoding($false)))
if ($Collected.Code -ne 0) { throw 'STOP: collection failed. Raw stdout saved; preserve artifacts and send the error; do not retry.' }
$ReceiptText = $Collected.Stdout.Replace("`r`n", "`n").Trim()
    $Match = [regex]::Match($ReceiptText, '\Atask=RD-18-WP4-display-source\nsource_files=(?:[4-9]|1[0-3])\narchive_sha256=([a-f0-9]{64})\nmanifest_sha256=([a-f0-9]{64})\ndone=ok\z')
    if (!$Match.Success) { throw 'STOP: unexpected collection receipt. Preserve it; do not retry.' }
    $ArchiveSha = $Match.Groups[1].Value
    $ManifestSha = $Match.Groups[2].Value
    [IO.File]::WriteAllText((Join-Path $Drop 'owner-receipt.txt'), $ReceiptText + "`n", (New-Object System.Text.UTF8Encoding($false)))
    foreach ($Name in @($ArchiveName, $ManifestName)) {
        & $Scp @Options ('booster:private/booster-pulls/' + $Name) (Join-Path $Drop $Name)
        if ($LASTEXITCODE -ne 0) { throw 'STOP: download failed. Preserve remote files; do not recollect.' }
    }
    $Archive = Join-Path $Drop $ArchiveName
    $Manifest = Join-Path $Drop $ManifestName
    if ((Get-FileHash -LiteralPath $Archive -Algorithm SHA256).Hash.ToLowerInvariant() -cne $ArchiveSha) { throw 'STOP: downloaded archive hash mismatch. Remote files preserved.' }
    if ((Get-FileHash -LiteralPath $Manifest -Algorithm SHA256).Hash.ToLowerInvariant() -cne $ManifestSha) { throw 'STOP: downloaded manifest hash mismatch. Remote files preserved.' }
    $ManifestText = [IO.File]::ReadAllText($Manifest)
    if ($ManifestText -cnotmatch ('(?m)^archive_sha256=' + $ArchiveSha + '$') -or $ManifestText -cnotmatch '(?m)^done=ok$') { throw 'STOP: manifest receipt mismatch. Remote files preserved.' }
    # Only these two exact regular private files may be removed after both local
    # downloads passed their remote hashes. No recursive or wildcard removal.
    $Cleanup = @'
set +x
set -euo pipefail
cd "$HOME/private/booster-pulls"
[[ ! -L "$HOME/private" && ! -L "$HOME/private/booster-pulls" ]] || exit 1
[[ $(pwd -P) == "$HOME/private/booster-pulls" ]] || exit 1
for f in RD-18-display-source-20261028-r1.tar.gz RD-18-display-source-20261028-r1.manifest.txt; do
  [[ -f "$f" && ! -L "$f" ]] || exit 1
done
archive_hash=$(sha256sum -- RD-18-display-source-20261028-r1.tar.gz)
manifest_hash=$(sha256sum -- RD-18-display-source-20261028-r1.manifest.txt)
[[ ${archive_hash:0:64} == '__ARCHIVE__' ]] || exit 1
[[ ${manifest_hash:0:64} == '__MANIFEST__' ]] || exit 1
rm -- "$HOME/private/booster-pulls/RD-18-display-source-20261028-r1.tar.gz" "$HOME/private/booster-pulls/RD-18-display-source-20261028-r1.manifest.txt"
printf 'remote_export_cleanup=ok\n'
'@
    $Cleanup = $Cleanup.Replace('__ARCHIVE__', $ArchiveSha).Replace('__MANIFEST__', $ManifestSha).Replace("`r`n", "`n")
    if ($Cleanup.Contains("`r")) { throw 'STOP: carriage return in cleanup body.' }
    $Cleaned = Invoke-Rd18LfProcess $Ssh ($Options + @('booster','bash','-s')) $Cleanup
    [IO.File]::WriteAllText((Join-Path $Drop 'cleanup-receipt-raw.txt'), $Cleaned.Stdout, (New-Object System.Text.UTF8Encoding($false)))
    if ($Cleaned.Code -ne 0 -or $Cleaned.Stdout.Trim() -cne 'remote_export_cleanup=ok') { throw 'STOP: local download verified; remote cleanup unresolved. Raw stdout saved; send the error.' }
    Write-Host 'RD18_WP4_SOURCE_READY'
    Write-Host ('Files: ' + $Drop)
