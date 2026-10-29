#requires -Version 5.1
# Owner only. ACC-006 visual WP1 supplementary pull r2: collect fixed sources, download, verify, then
# remove only the three exact private export artifacts. No patch, no cache clear.
[CmdletBinding()]
param()
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
$Repo = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$Collector = Join-Path $Repo 'diagnostics\ACC-006_wp1_r2_collect_20261028.sh'
$CollectorSha = '847ee3d40fcea348201609a200818b23fd8f2dc0ef3ed94b04c39a90dfeab9bc'
$Drop = Join-Path $Repo 'work\acc006-wp0-20261028\owner-drop-r2'
$Base = 'ACC-006-visual-source-20261028-r2'
$Names = @(($Base + '.tar.gz'), ($Base + '.manifest.txt'), ($Base + '.inventory.txt'))
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
function Invoke-A6LfProcess([string]$Program, [string[]]$NativeArgs, [string]$Body) {
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
if ($Body.Contains("`r")) { throw 'STOP: carriage return in collector body.' }
$Collected = Invoke-A6LfProcess $Ssh ($Options + @('booster','bash','-s')) $Body
[IO.File]::WriteAllText((Join-Path $Drop 'owner-receipt-raw.txt'), $Collected.Stdout, (New-Object System.Text.UTF8Encoding($false)))
if ($Collected.Code -ne 0) { throw 'STOP: collection failed. Raw stdout saved; preserve artifacts and send the error; do not retry.' }
$ReceiptText = $Collected.Stdout.Replace("`r`n", "`n").Trim()
$Match = [regex]::Match($ReceiptText, '\Atask=ACC-006-VISUAL-WP1-R2\nsource_files=(5)\narchive_sha256=([a-f0-9]{64})\ninventory_sha256=([a-f0-9]{64})\nmanifest_sha256=([a-f0-9]{64})\ndone=ok\z')
if (!$Match.Success) { throw 'STOP: unexpected collection receipt. Preserve it; do not retry.' }
$Expected = @{}
$Expected[$Names[0]] = $Match.Groups[2].Value
$Expected[$Names[2]] = $Match.Groups[3].Value
$Expected[$Names[1]] = $Match.Groups[4].Value
[IO.File]::WriteAllText((Join-Path $Drop 'owner-receipt.txt'), $ReceiptText + "`n", (New-Object System.Text.UTF8Encoding($false)))
foreach ($Name in $Names) {
    & $Scp @Options ('booster:private/booster-pulls/' + $Name) (Join-Path $Drop $Name)
    if ($LASTEXITCODE -ne 0) { throw 'STOP: download failed. Preserve remote files; do not recollect.' }
    if ((Get-FileHash -LiteralPath (Join-Path $Drop $Name) -Algorithm SHA256).Hash.ToLowerInvariant() -cne $Expected[$Name]) { throw ('STOP: downloaded hash mismatch for ' + $Name + '. Remote files preserved.') }
}
$ManifestText = [IO.File]::ReadAllText((Join-Path $Drop $Names[1]))
if ($ManifestText -cnotmatch ('(?m)^archive_sha256=' + $Expected[$Names[0]] + '$') -or $ManifestText -cnotmatch ('(?m)^inventory_sha256=' + $Expected[$Names[2]] + '$') -or $ManifestText -cnotmatch '(?m)^done=ok$') { throw 'STOP: manifest receipt mismatch. Remote files preserved.' }
# Only these three exact regular private files may be removed after all local
# downloads passed their remote hashes. No recursive or wildcard removal.
$Cleanup = @'
set +x
set -euo pipefail
cd "$HOME/private/booster-pulls"
[[ ! -L "$HOME/private" && ! -L "$HOME/private/booster-pulls" ]]
[[ $(pwd -P) == "$HOME/private/booster-pulls" ]]
a=ACC-006-visual-source-20261028-r2.tar.gz
m=ACC-006-visual-source-20261028-r2.manifest.txt
i=ACC-006-visual-source-20261028-r2.inventory.txt
for f in "$a" "$m" "$i"; do [[ -f "$f" && ! -L "$f" ]]; done
ah=$(sha256sum -- "$a"); mh=$(sha256sum -- "$m"); ih=$(sha256sum -- "$i")
[[ ${ah:0:64} == '__ARCHIVE__' ]]
[[ ${mh:0:64} == '__MANIFEST__' ]]
[[ ${ih:0:64} == '__INVENTORY__' ]]
rm -- "$HOME/private/booster-pulls/$a" "$HOME/private/booster-pulls/$m" "$HOME/private/booster-pulls/$i"
printf 'remote_export_cleanup=ok\n'
'@
$Cleanup = $Cleanup.Replace('__ARCHIVE__', $Expected[$Names[0]]).Replace('__MANIFEST__', $Expected[$Names[1]]).Replace('__INVENTORY__', $Expected[$Names[2]]).Replace("`r`n", "`n")
if ($Cleanup.Contains("`r")) { throw 'STOP: carriage return in cleanup body.' }
$Cleaned = Invoke-A6LfProcess $Ssh ($Options + @('booster','bash','-s')) $Cleanup
[IO.File]::WriteAllText((Join-Path $Drop 'cleanup-receipt-raw.txt'), $Cleaned.Stdout, (New-Object System.Text.UTF8Encoding($false)))
if ($Cleaned.Code -ne 0 -or $Cleaned.Stdout.Trim() -cne 'remote_export_cleanup=ok') { throw 'STOP: local download verified; remote cleanup unresolved. Raw stdout saved; send the error.' }
Write-Host 'ACC-006_WP1_R2_SOURCE_READY'
Write-Host ('Attach these 3 files to the cloud session: ' + $Drop)
