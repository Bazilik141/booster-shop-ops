[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)]
  [string]$NodePath,

  [Parameter(Mandatory = $true)]
  [string]$OutputDirectory
)

$ErrorActionPreference = "Stop"
$expectedNodeVersion = "v24.19.0"
$packageName = "Booster-3DP-Serhiy_Node-v24.19.0_20260824"
$repoRoot = Split-Path -Parent $PSScriptRoot
$serverSource = Join-Path $repoRoot "3d-print\serhiy-local-server"
$sharedSource = Join-Path $repoRoot "3d-print\shared\print-time.js"
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stagingRoot = Join-Path $tempRoot ("Booster3DP-WP3-staging-" + [Guid]::NewGuid().ToString("N"))
$packageRoot = Join-Path $stagingRoot $packageName
$expandedNodeRoot = Join-Path $stagingRoot "node-source"

function Assert-File {
  param([string]$Path, [string]$Label)
  if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "$Label not found: $Path" }
}

try {
  Assert-File $sharedSource "Shared print-time source"
  if (-not (Test-Path -LiteralPath $serverSource -PathType Container)) { throw "Server source not found: $serverSource" }
  $resolvedNodePath = (Resolve-Path -LiteralPath $NodePath).Path
  $nodeRoot = $null
  if (Test-Path -LiteralPath $resolvedNodePath -PathType Container) {
    $nodeRoot = $resolvedNodePath
  } elseif ([IO.Path]::GetExtension($resolvedNodePath) -ieq ".zip") {
    New-Item -ItemType Directory -Path $expandedNodeRoot -Force | Out-Null
    Expand-Archive -LiteralPath $resolvedNodePath -DestinationPath $expandedNodeRoot -Force
    $nodeExecutable = Get-ChildItem -LiteralPath $expandedNodeRoot -Filter "node.exe" -File -Recurse | Select-Object -First 1
    if (-not $nodeExecutable) { throw "Portable Node archive does not contain node.exe." }
    $nodeRoot = $nodeExecutable.Directory.FullName
  } else {
    throw "NodePath must be the official Windows x64 zip or its extracted directory."
  }

  $nodeExe = Join-Path $nodeRoot "node.exe"
  Assert-File $nodeExe "Portable node.exe"
  $actualNodeVersion = ([string](& $nodeExe --version)).Trim()
  if ($actualNodeVersion -ne $expectedNodeVersion) {
    throw "Expected Node $expectedNodeVersion, got $actualNodeVersion. Use node-v24.19.0-win-x64.zip."
  }

  $appRoot = Join-Path $packageRoot "app"
  $runtimeRoot = Join-Path $packageRoot "runtime"
  $sharedRoot = Join-Path $packageRoot "shared"
  New-Item -ItemType Directory -Path $appRoot, $runtimeRoot, $sharedRoot -Force | Out-Null

  foreach ($fileName in @("server.mjs", "package.json", ".env.example")) {
    Copy-Item -LiteralPath (Join-Path $serverSource $fileName) -Destination $appRoot
  }
  Copy-Item -LiteralPath (Join-Path $serverSource "lib") -Destination $appRoot -Recurse
  Copy-Item -LiteralPath (Join-Path $serverSource "public") -Destination $appRoot -Recurse
  Copy-Item -LiteralPath $sharedSource -Destination $sharedRoot
  Get-ChildItem -LiteralPath (Join-Path $serverSource "distribution") -File | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $packageRoot
  }
  Get-ChildItem -LiteralPath $nodeRoot | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $runtimeRoot -Recurse
  }

  $outputRoot = [IO.Path]::GetFullPath($OutputDirectory)
  New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
  $zipPath = Join-Path $outputRoot ($packageName + ".zip")
  if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
  Compress-Archive -LiteralPath $packageRoot -DestinationPath $zipPath -CompressionLevel Optimal

  Write-Host "Package created: $zipPath"
  Write-Host "Node runtime: $actualNodeVersion (portable Windows x64)"
  Write-Host "Repository runtime copies: 0"
} finally {
  $resolvedStaging = [IO.Path]::GetFullPath($stagingRoot)
  if ((Test-Path -LiteralPath $resolvedStaging) -and $resolvedStaging.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase)) {
    Remove-Item -LiteralPath $resolvedStaging -Recurse -Force
  }
}
