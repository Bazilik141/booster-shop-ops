$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Payload = Join-Path $Root 'payload'
$Project = Join-Path $Root 'src\Installer\TCGCardShopSimulatorUA.Installer.csproj'
$EmbeddedZip = Join-Path $Root 'src\Installer\payload.zip'
$Release = Join-Path $Root 'release'
$Dictionary = Join-Path $Payload 'BepInEx\plugins\Translator\localization_data.txt'
$AllowedEqual = Join-Path $Root 'localization\reviewed_unchanged.txt'

$required = @(
  '.doorstop_version',
  'doorstop_config.ini',
  'winhttp.dll',
  'version.dll',
  'BepInEx\core\BepInEx.dll',
  'BepInEx\core\BepInEx.Preloader.dll',
  'BepInEx\plugins\Translator\Translator.dll',
  'BepInEx\plugins\Translator\localization_data.txt',
  'BepInEx\config\shaklin.Translator.cfg'
)
$missing = @()
foreach ($rel in $required) { if (-not (Test-Path (Join-Path $Payload $rel))) { $missing += $rel } }
if ($missing.Count -gt 0) {
  throw "Payload incomplete. Missing:`n - $($missing -join "`n - ")"
}

if (-not (Get-Command python -ErrorAction SilentlyContinue)) { throw 'Python 3 is required for dictionary validation.' }
if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) { throw '.NET 8 SDK is required to build the installer.' }

python (Join-Path $Root 'tools\validate_dictionary.py') $Dictionary --expect-min-records 2190 --allowed-equal-file $AllowedEqual --forbid-cjk
if ($LASTEXITCODE -ne 0) { throw 'Dictionary validation failed.' }

if (Test-Path $EmbeddedZip) { Remove-Item $EmbeddedZip -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
  $Payload,
  $EmbeddedZip,
  [System.IO.Compression.CompressionLevel]::Optimal,
  $false
)

# Verify that the archive actually contains every required payload file, including .doorstop_version.
$archive = [System.IO.Compression.ZipFile]::OpenRead($EmbeddedZip)
try {
  $zipEntries = @{}
  foreach ($entry in $archive.Entries) {
    if (-not [string]::IsNullOrWhiteSpace($entry.Name)) {
      $zipEntries[$entry.FullName.Replace([char]47, [System.IO.Path]::DirectorySeparatorChar)] = $true
    }
  }
  $missingFromZip = @()
  foreach ($rel in $required) {
    if (-not $zipEntries.ContainsKey($rel)) { $missingFromZip += $rel }
  }
  if ($missingFromZip.Count -gt 0) {
    throw "Embedded payload.zip incomplete. Missing:`n - $($missingFromZip -join "`n - ")"
  }
}
finally {
  $archive.Dispose()
}

if (Test-Path $Release) { Remove-Item $Release -Recurse -Force }
New-Item $Release -ItemType Directory | Out-Null

dotnet publish $Project -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o $Release
$exe = Join-Path $Release 'TCGCardShopSimulatorUA-Installer.exe'
if (-not (Test-Path $exe)) { throw 'Expected installer exe was not produced.' }
$hash = (Get-FileHash $exe -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  TCGCardShopSimulatorUA-Installer.exe" | Set-Content (Join-Path $Release 'SHA256SUMS.txt') -Encoding ascii
Write-Host "Built: $exe"
Write-Host "SHA256: $hash"
