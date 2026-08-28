param(
  [Parameter(Mandatory=$true)][string]$BepInExCoreDir,
  [Parameter(Mandatory=$true)][string]$GameManagedDir
)

$ErrorActionPreference = 'Stop'
$BepInExCoreDir = (Resolve-Path -LiteralPath $BepInExCoreDir).Path
$GameManagedDir = (Resolve-Path -LiteralPath $GameManagedDir).Path
foreach ($reference in @(
  (Join-Path $BepInExCoreDir 'BepInEx.dll'),
  (Join-Path $BepInExCoreDir '0Harmony.dll'),
  (Join-Path $GameManagedDir 'Assembly-CSharp.dll'),
  (Join-Path $GameManagedDir 'UnityEngine.dll'),
  (Join-Path $GameManagedDir 'UnityEngine.CoreModule.dll')
)) {
  if (-not (Test-Path -LiteralPath $reference)) { throw "Missing required build reference: $reference" }
}
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$pluginProject = Join-Path $root 'src\Plugin\TCGCardShopSimulatorUA.Plugin.csproj'
$installerProject = Join-Path $root 'src\Installer\TCGCardShopSimulatorUA.Installer.csproj'
$dictionary = Join-Path $root 'localization\localization_data.txt'
$dynamicOverrides = Join-Path $root 'localization\dynamic_ui_overrides.txt'
$payload = Join-Path $root 'payload'
$pluginOut = Join-Path $root 'src\Plugin\bin\Release\net48\TCGCardShopSimulatorUA.dll'
$payloadPlugin = Join-Path $payload 'BepInEx\plugins\TCGCardShopSimulatorUA\TCGCardShopSimulatorUA.dll'
$payloadDictionary = Join-Path $payload 'BepInEx\plugins\TCGCardShopSimulatorUA\localization_data.txt'
$payloadDynamicOverrides = Join-Path $payload 'BepInEx\plugins\TCGCardShopSimulatorUA\dynamic_ui_overrides.txt'
$embeddedZip = Join-Path $root 'src\Installer\payload.zip'
$release = Join-Path $root 'release'

$lines = @(Get-Content -LiteralPath $dictionary -Encoding utf8 | Where-Object { $_.Trim() })
$seen = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
foreach ($line in $lines) {
  if (($line.ToCharArray() | Where-Object { $_ -eq '|' }).Count -ne 1) { throw "Malformed dictionary row: $line" }
  $source = $line.Split('|',2)[0]
  if (-not $seen.Add($source)) { throw "Exact duplicate dictionary key: $source" }
}
if ($lines.Count -ne 2190) { throw "Expected 2190 mappings, got $($lines.Count)" }
if (($lines -join "`n") -match '[\u3400-\u4DBF\u4E00-\u9FFF\u3040-\u30FF\uAC00-\uD7AF]') { throw 'Dictionary contains CJK text.' }
$dynamicLines = @(Get-Content -LiteralPath $dynamicOverrides -Encoding utf8 | Where-Object { $_.Trim() })
$dynamicSeen = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
foreach ($line in $dynamicLines) {
  if (($line.ToCharArray() | Where-Object { $_ -eq '|' }).Count -ne 1) { throw "Malformed dynamic UI row: $line" }
  $source = $line.Split('|',2)[0]
  if (-not $dynamicSeen.Add($source)) { throw "Exact duplicate dynamic UI key: $source" }
  if ($seen.Contains($source)) { throw "Dynamic UI key duplicates a master key: $source" }
}
if (($dynamicLines -join "`n") -match '[\u3400-\u4DBF\u4E00-\u9FFF\u3040-\u30FF\uAC00-\uD7AF]') { throw 'Dynamic UI overrides contain CJK text.' }

dotnet build $pluginProject -c Release -p:BepInExCoreDir=$BepInExCoreDir -p:GameManagedDir=$GameManagedDir
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $pluginOut)) { throw 'Plugin build failed.' }
Copy-Item -LiteralPath $pluginOut -Destination $payloadPlugin -Force
Copy-Item -LiteralPath $dictionary -Destination $payloadDictionary -Force
Copy-Item -LiteralPath $dynamicOverrides -Destination $payloadDynamicOverrides -Force

$banned = @('Translator.dll','shaklin.Translator.cfg','README.md','.pdb')
$payloadNames = Get-ChildItem -LiteralPath $payload -Recurse -File | ForEach-Object { $_.Name }
foreach ($name in $banned) { if ($payloadNames -contains $name) { throw "Forbidden public payload file: $name" } }

if (Test-Path -LiteralPath $embeddedZip) { Remove-Item -LiteralPath $embeddedZip -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($payload, $embeddedZip, [IO.Compression.CompressionLevel]::Optimal, $false)
if (Test-Path -LiteralPath $release) { Remove-Item -LiteralPath $release -Recurse -Force }
New-Item -ItemType Directory -Path $release | Out-Null
dotnet publish $installerProject -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o $release
$exe = Join-Path $release 'TCGCardShopSimulator-UA-Installer.exe'
if (-not (Test-Path -LiteralPath $exe)) { throw 'Expected installer EXE was not produced.' }
Get-ChildItem -LiteralPath $release -Filter '*.pdb' | Remove-Item -Force
$hash = (Get-FileHash -LiteralPath $exe -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  TCGCardShopSimulator-UA-Installer.exe" | Set-Content -LiteralPath (Join-Path $release 'SHA256SUMS.txt') -Encoding ascii
Write-Host "Built: $exe"
Write-Host "SHA256: $hash"
