param(
    [Parameter(Mandatory=$true)]
    [string]$GameRoot
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Payload = Join-Path $Root 'payload'

$requiredGameMarkers = @('Card Shop Simulator.exe', 'Card Shop Simulator_Data')
foreach ($marker in $requiredGameMarkers) {
    if (-not (Test-Path (Join-Path $GameRoot $marker))) {
        throw "Invalid game root: missing $marker"
    }
}

$copyFiles = @(
    '.doorstop_version',
    'doorstop_config.ini',
    'winhttp.dll',
    'version.dll',
    'BepInEx\plugins\Translator\Translator.dll',
    'BepInEx\config\shaklin.Translator.cfg'
)
foreach ($rel in $copyFiles) {
    $src = Join-Path $GameRoot $rel
    if (-not (Test-Path $src)) { throw "Working baseline is missing required file: $rel" }
    $dst = Join-Path $Payload $rel
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $dst) | Out-Null
    Copy-Item $src $dst -Force
}

$coreSrc = Join-Path $GameRoot 'BepInEx\core'
if (-not (Test-Path (Join-Path $coreSrc 'BepInEx.dll'))) {
    throw 'Working baseline is missing BepInEx\core\BepInEx.dll'
}
$coreDst = Join-Path $Payload 'BepInEx\core'
New-Item -ItemType Directory -Force -Path $coreDst | Out-Null
Copy-Item (Join-Path $coreSrc '*') $coreDst -Recurse -Force

Write-Host 'Payload runtime copied from the selected working game installation.'
Write-Host 'The project localization_data.txt was NOT replaced.'
Write-Warning 'This makes a private/local build workspace. Check third-party redistribution permissions before publishing the resulting installer.'
