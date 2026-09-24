[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)]
  [string]$NodePath,

  [Parameter(Mandatory = $true)]
  [string]$OutputDirectory,

  [string]$ServerSourcePath = ""
)

$ErrorActionPreference = "Stop"
$expectedNodeVersion = "v24.19.0"
$packageName = "Booster-3DP-Serhiy_Node-v24.19.0_$(Get-Date -Format 'yyyyMMdd')"
$repoRoot = Split-Path -Parent $PSScriptRoot
$serverSource = if ($ServerSourcePath) { [IO.Path]::GetFullPath($ServerSourcePath) } else { Join-Path $repoRoot "3d-print\serhiy-local-server" }
$sharedSource = Join-Path $repoRoot "3d-print\shared\print-time.js"
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stagingRoot = Join-Path $tempRoot ("Booster3DP-WP3-staging-" + [Guid]::NewGuid().ToString("N"))
$packageRoot = Join-Path $stagingRoot $packageName
$expandedNodeRoot = Join-Path $stagingRoot "node-source"

function Assert-File {
  param([string]$Path, [string]$Label)
  if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "$Label not found: $Path" }
}

function Assert-WindowsScriptBytes {
  param([byte[]]$Bytes, [string]$Label)
  $extension = [IO.Path]::GetExtension($Label).ToLowerInvariant()
  if ($extension -in @(".bat", ".vbs")) {
    if ($Bytes | Where-Object { $_ -gt 127 } | Select-Object -First 1) {
      throw "$Label must be ASCII-only."
    }
    $text = [Text.Encoding]::ASCII.GetString($Bytes)
    if ($text -match "(?<!`r)`n" -or $text -match "`r(?!`n)") {
      throw "$Label must use CRLF line endings only."
    }
    return
  }
  if ($extension -in @(".ps1", ".txt")) {
    if ($Bytes.Length -lt 3 -or $Bytes[0] -ne 0xEF -or $Bytes[1] -ne 0xBB -or $Bytes[2] -ne 0xBF) {
      throw "$Label must be UTF-8 with BOM."
    }
    $strictUtf8 = New-Object Text.UTF8Encoding($true, $true)
    try {
      $text = $strictUtf8.GetString($Bytes, 3, $Bytes.Length - 3)
    } catch {
      throw "$Label is not valid UTF-8."
    }
    if ($text -match "(?<!`r)`n" -or $text -match "`r(?!`n)") {
      throw "$Label must use CRLF line endings only."
    }
  }
}

function Get-DistributionFileText {
  param([byte[]]$Bytes, [string]$Label)
  $extension = [IO.Path]::GetExtension($Label).ToLowerInvariant()
  if ($extension -in @(".bat", ".vbs")) {
    return [Text.Encoding]::ASCII.GetString($Bytes)
  }
  $utf8 = New-Object Text.UTF8Encoding($true, $true)
  return $utf8.GetString($Bytes, 3, $Bytes.Length - 3)
}

function Get-DistributionReferences {
  param([string]$Text, [string]$Label)
  $normalizedText = $Text -replace '(?i)%~dp0', ''
  $references = @([regex]::Matches($normalizedText, '(?i)(?<name>[A-Za-z0-9][A-Za-z0-9._ -]*\.(?:bat|cmd|ps1|vbs))') | ForEach-Object {
    $_.Groups['name'].Value
  } | Select-Object -Unique)
  if ($references.Count -eq 0) { throw "$Label does not reference a packaged launcher file." }
  return $references
}

function Assert-DistributionReferencesOnDisk {
  param([string]$PackageDirectory)
  $launchers = Get-ChildItem -LiteralPath $PackageDirectory -File | Where-Object { $_.Extension -in @(".bat", ".vbs") }
  foreach ($launcher in $launchers) {
    $bytes = [IO.File]::ReadAllBytes($launcher.FullName)
    $references = Get-DistributionReferences (Get-DistributionFileText $bytes $launcher.FullName) $launcher.FullName
    foreach ($reference in $references) {
      Assert-File (Join-Path $PackageDirectory $reference) ("Referenced by " + $launcher.Name)
    }
  }
}

function Assert-WindowsScriptFile {
  param([string]$Path)
  Assert-WindowsScriptBytes ([IO.File]::ReadAllBytes($Path)) $Path
}

function Assert-ZipDistributionFiles {
  param([string]$ZipPath)
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $archive = [IO.Compression.ZipFile]::OpenRead($ZipPath)
  try {
    $entryNames = @{}
    foreach ($entry in $archive.Entries) { $entryNames[$entry.FullName.ToLowerInvariant()] = $true }
    $checked = 0
    foreach ($entry in $archive.Entries) {
      $extension = [IO.Path]::GetExtension($entry.FullName).ToLowerInvariant()
      if ($extension -notin @(".bat", ".ps1", ".vbs", ".txt")) { continue }
      $stream = $entry.Open()
      $memory = New-Object IO.MemoryStream
      try {
        $stream.CopyTo($memory)
        $bytes = $memory.ToArray()
        Assert-WindowsScriptBytes $bytes ("zip:" + $entry.FullName)
        if ($extension -in @(".bat", ".vbs")) {
          $references = Get-DistributionReferences (Get-DistributionFileText $bytes $entry.FullName) ("zip:" + $entry.FullName)
          $entryDirectory = [IO.Path]::GetDirectoryName($entry.FullName).Replace('\', '/')
          foreach ($reference in $references) {
            $referencedEntry = if ($entryDirectory) { $entryDirectory + "/" + $reference } else { $reference }
            if (-not $entryNames.ContainsKey($referencedEntry.ToLowerInvariant())) {
              throw "zip:$($entry.FullName) references missing packaged file $reference."
            }
          }
        }
        $checked += 1
      } finally {
        $memory.Dispose()
        $stream.Dispose()
      }
    }
    if ($checked -eq 0) { throw "Produced zip contains no validated distribution files." }
  } finally {
    $archive.Dispose()
  }
}

try {
  Assert-File $sharedSource "Shared print-time source"
  if (-not (Test-Path -LiteralPath $serverSource -PathType Container)) { throw "Server source not found: $serverSource" }
  Assert-WindowsScriptFile $PSCommandPath
  $distributionSource = Join-Path $serverSource "distribution"
  $distributionFiles = Get-ChildItem -LiteralPath $distributionSource -File | Where-Object { $_.Extension -in @(".bat", ".ps1", ".vbs", ".txt") }
  if (-not $distributionFiles) { throw "Distribution contains no validated launcher or instruction files." }
  $distributionFiles | ForEach-Object { Assert-WindowsScriptFile $_.FullName }
  Assert-DistributionReferencesOnDisk $distributionSource

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

  foreach ($fileName in @("server.mjs", "package.json")) {
    Copy-Item -LiteralPath (Join-Path $serverSource $fileName) -Destination $appRoot
  }
  Copy-Item -LiteralPath (Join-Path $serverSource "lib") -Destination $appRoot -Recurse
  Copy-Item -LiteralPath (Join-Path $serverSource "public") -Destination $appRoot -Recurse
  Copy-Item -LiteralPath $sharedSource -Destination $sharedRoot
  Get-ChildItem -LiteralPath (Join-Path $serverSource "distribution") -File | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $packageRoot
  }
  Assert-DistributionReferencesOnDisk $packageRoot
  Copy-Item -LiteralPath $nodeExe -Destination (Join-Path $runtimeRoot "node.exe")
  foreach ($runtimeDocument in @("LICENSE", "README.md")) {
    $documentPath = Join-Path $nodeRoot $runtimeDocument
    if (Test-Path -LiteralPath $documentPath -PathType Leaf) {
      Copy-Item -LiteralPath $documentPath -Destination $runtimeRoot
    }
  }

  $outputRoot = [IO.Path]::GetFullPath($OutputDirectory)
  New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
  $zipPath = Join-Path $outputRoot ($packageName + ".zip")
  if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
  Compress-Archive -LiteralPath $packageRoot -DestinationPath $zipPath -CompressionLevel Optimal
  Assert-ZipDistributionFiles $zipPath

  Write-Host "Package created: $zipPath"
  Write-Host "Node runtime: $actualNodeVersion (portable Windows x64)"
  Write-Host "Distribution encoding: source and zip verified"
  Write-Host "Launcher references: source, staging and zip verified"
  Write-Host "Repository runtime copies: 0"
} finally {
  $resolvedStaging = [IO.Path]::GetFullPath($stagingRoot)
  if ((Test-Path -LiteralPath $resolvedStaging) -and $resolvedStaging.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase)) {
    Remove-Item -LiteralPath $resolvedStaging -Recurse -Force
  }
}
