[CmdletBinding()]
param(
  [string]$OutputDirectory = "",
  [string]$Version = "$(Get-Date -Format 'yyyy.MM.dd')"
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $PSScriptRoot
$serverRoot = Join-Path $repoRoot "3d-print\serhiy-local-server"
$sourceFile = Join-Path $PSScriptRoot "serhiy-updater\Program.cs"
$outputRoot = if ($OutputDirectory) { [IO.Path]::GetFullPath($OutputDirectory) } else { Join-Path $serverRoot "dist" }
$tempBase = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stagingRoot = Join-Path $tempBase ("Booster3DP-updater-build-" + [Guid]::NewGuid().ToString("N"))
$payloadRoot = Join-Path $stagingRoot "payload"
$payloadZip = Join-Path $stagingRoot "payload.zip"
$manifestPath = Join-Path $stagingRoot "manifest.sha256"
$compiler = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
$exeName = "Booster-3DP-Оновлення_$($Version.Replace('.', '')).exe"
$exePath = Join-Path $outputRoot $exeName

function Copy-PayloadFile {
  param([string]$Source, [string]$RelativePath)
  if (-not (Test-Path -LiteralPath $Source -PathType Leaf)) { throw "Payload source missing: $Source" }
  $destination = Join-Path $payloadRoot $RelativePath
  $folder = Split-Path -Parent $destination
  New-Item -ItemType Directory -Force -Path $folder | Out-Null
  Copy-Item -LiteralPath $Source -Destination $destination -Force
}

try {
  if (-not (Test-Path -LiteralPath $compiler -PathType Leaf)) { throw "C# compiler not found: $compiler" }
  if (-not (Test-Path -LiteralPath $sourceFile -PathType Leaf)) { throw "Updater source not found: $sourceFile" }
  New-Item -ItemType Directory -Force -Path $payloadRoot, $outputRoot | Out-Null

  Copy-PayloadFile (Join-Path $serverRoot "server.mjs") "app\server.mjs"
  Copy-PayloadFile (Join-Path $serverRoot "package.json") "app\package.json"
  Get-ChildItem -LiteralPath (Join-Path $serverRoot "lib") -File -Recurse | ForEach-Object {
    Copy-PayloadFile $_.FullName ("app\lib\" + $_.FullName.Substring((Join-Path $serverRoot "lib").Length).TrimStart('\'))
  }
  Get-ChildItem -LiteralPath (Join-Path $serverRoot "public") -File -Recurse | ForEach-Object {
    Copy-PayloadFile $_.FullName ("app\public\" + $_.FullName.Substring((Join-Path $serverRoot "public").Length).TrimStart('\'))
  }
  Copy-PayloadFile (Join-Path $repoRoot "3d-print\shared\print-time.js") "shared\print-time.js"
  Get-ChildItem -LiteralPath (Join-Path $serverRoot "distribution") -File | ForEach-Object {
    Copy-PayloadFile $_.FullName $_.Name
  }

  [IO.File]::WriteAllText((Join-Path $payloadRoot "VERSION.txt"), "Booster 3D — Сергій`r`nVersion: $Version`r`n", (New-Object Text.UTF8Encoding($true)))
  $manifestLines = Get-ChildItem -LiteralPath $payloadRoot -File -Recurse | Sort-Object FullName | ForEach-Object {
    $relative = $_.FullName.Substring($payloadRoot.Length + 1).Replace('\', '/')
    "{0}`t{1}" -f (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(), $relative
  }
  [IO.File]::WriteAllLines($manifestPath, $manifestLines, (New-Object Text.UTF8Encoding($false)))
  Compress-Archive -Path (Join-Path $payloadRoot '*') -DestinationPath $payloadZip -CompressionLevel Optimal -Force

  & $compiler /nologo /target:winexe /optimize+ /platform:anycpu /out:$exePath `
    /reference:System.dll /reference:System.Core.dll /reference:System.Windows.Forms.dll /reference:System.Drawing.dll `
    /reference:System.IO.Compression.dll /reference:System.IO.Compression.FileSystem.dll `
    /resource:"$payloadZip,Booster3DP.Payload.zip" /resource:"$manifestPath,Booster3DP.Manifest.sha256" $sourceFile
  if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $exePath -PathType Leaf)) { throw "Updater compilation failed." }

  $hash = (Get-FileHash -LiteralPath $exePath -Algorithm SHA256).Hash.ToLowerInvariant()
  $hashPath = $exePath + ".sha256.txt"
  [IO.File]::WriteAllText($hashPath, "$hash  $exeName`r`n", (New-Object Text.UTF8Encoding($false)))
  Write-Host "Updater created: $exePath"
  Write-Host "SHA-256: $hash"
  Write-Host "Payload files: $($manifestLines.Count)"
} finally {
  $resolvedStaging = [IO.Path]::GetFullPath($stagingRoot)
  if ((Test-Path -LiteralPath $resolvedStaging) -and $resolvedStaging.StartsWith($tempBase, [StringComparison]::OrdinalIgnoreCase)) {
    Remove-Item -LiteralPath $resolvedStaging -Recurse -Force
  }
}
