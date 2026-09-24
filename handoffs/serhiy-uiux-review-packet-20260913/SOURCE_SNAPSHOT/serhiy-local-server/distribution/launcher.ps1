[CmdletBinding()]
param(
  [ValidateSet("Start", "FirstRun", "ChangeToken")]
  [string]$Mode = "Start"
)

$ErrorActionPreference = "Stop"
[Console]::InputEncoding = New-Object System.Text.UTF8Encoding($false)
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$localUrl = "http://127.0.0.1:3107"

function Show-Message {
  param([string]$Message, [string]$Title = "Booster Shop — 3D-друк")
  $shell = New-Object -ComObject WScript.Shell
  [void]$shell.Popup($Message, 0, $Title, 0x40)
}

function Read-MaskedValue {
  param([string]$Prompt)
  $secure = Read-Host $Prompt -AsSecureString
  if ($secure.Length -eq 0) { throw "Порожнє значення не збережено." }
  $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
  try {
    return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
  } finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
  }
}

function Save-UserVariable {
  param([string]$Name, [string]$Value)
  [Environment]::SetEnvironmentVariable($Name, $Value, "User")
  if ([string][Environment]::GetEnvironmentVariable($Name, "User") -ne $Value) {
    throw "Windows зберегла значення не повністю. Звернися до власника."
  }
  [Environment]::SetEnvironmentVariable($Name, $Value, "Process")
}

function Get-UserVariable {
  param([string]$Name)
  return ([string][Environment]::GetEnvironmentVariable($Name, "User")).Trim()
}

function Test-WebAppUrl {
  param([string]$Value)
  $uri = $null
  return [Uri]::TryCreate($Value, [UriKind]::Absolute, [ref]$uri) -and $uri.Scheme -eq "https"
}

function Test-PortInUse {
  param([int]$Port)
  $client = New-Object Net.Sockets.TcpClient
  try {
    $attempt = $client.BeginConnect("127.0.0.1", $Port, $null, $null)
    if (-not $attempt.AsyncWaitHandle.WaitOne(300)) { return $false }
    $client.EndConnect($attempt)
    return $client.Connected
  } catch {
    return $false
  } finally {
    $client.Dispose()
  }
}

function Invoke-IdentityCheck {
  param([string]$ApiUrl, [string]$Token)
  $separator = "?"
  if ($ApiUrl.Contains("?")) { $separator = "&" }
  $probeUrl = $ApiUrl + $separator + "action=3dp_bootstrap&include_archived=true&token=" + [Uri]::EscapeDataString($Token)
  try {
    $payload = Invoke-RestMethod -Method Get -Uri $probeUrl -TimeoutSec 45
  } catch {
    throw "Не вдалося зв’язатися з 3D-таблицею. Перевір інтернет або звернися до власника."
  }
  if (-not $payload.ok) {
    if ([string]$payload.code -eq "UNAUTHORIZED") {
      throw "Токен не прийнято. Запусти «Змінити токен.bat» і введи новий токен."
    }
    throw ([string]$payload.error)
  }
  if ([string]$payload.settings.range -ne "B2:B5") {
    throw "Наданий доступ не є окремим доступом Сергія. Звернися до власника й не використовуй цей токен."
  }
}

function Start-HiddenLauncher {
  $vbsPath = Join-Path $PSScriptRoot "start-hidden.vbs"
  Start-Process -FilePath "wscript.exe" -ArgumentList @('//B', ('"' + $vbsPath + '"'))
}

try {
  if ($Mode -eq "ChangeToken") {
    $newToken = Read-MaskedValue "Встав новий токен (символи не показуються)"
    Save-UserVariable "BOOSTER_3DP_SERHIY_TOKEN" $newToken
    $newToken = $null
    Write-Host "Новий токен збережено. Закрий відкриту сторінку; після зупинки сервера запусти «Запустити.bat»." -ForegroundColor Green
    exit 0
  }

  if (Test-PortInUse 3107) {
    Start-Process $localUrl
    exit 0
  }

  $apiUrl = Get-UserVariable "BOOSTER_3DP_URL"
  $token = Get-UserVariable "BOOSTER_3DP_SERHIY_TOKEN"
  if ($Mode -eq "Start" -and (-not $apiUrl -or -not $token)) {
    $arguments = '-NoProfile -ExecutionPolicy Bypass -File "' + $PSCommandPath + '" -Mode FirstRun'
    Start-Process -FilePath "powershell.exe" -ArgumentList $arguments
    exit 0
  }

  if (-not $apiUrl) {
    $apiUrl = ([string](Read-Host "Встав адресу, яку надав власник")).Trim()
    if (-not (Test-WebAppUrl $apiUrl)) { throw "Адреса має починатися з https://. Нічого не збережено." }
    Save-UserVariable "BOOSTER_3DP_URL" $apiUrl
  }
  if (-not $token) {
    $token = Read-MaskedValue "Встав токен (символи не показуються)"
    Save-UserVariable "BOOSTER_3DP_SERHIY_TOKEN" $token
  }

  Invoke-IdentityCheck $apiUrl $token
  if ($Mode -eq "FirstRun") {
    Write-Host "Доступ перевірено й збережено. Відкриваю сторінку без чорного вікна." -ForegroundColor Green
    Start-HiddenLauncher
    exit 0
  }

  $nodePath = Join-Path $PSScriptRoot "runtime\node.exe"
  $serverPath = Join-Path $PSScriptRoot "app\server.mjs"
  if (-not (Test-Path -LiteralPath $nodePath -PathType Leaf) -or -not (Test-Path -LiteralPath $serverPath -PathType Leaf)) {
    throw "У папці бракує потрібних файлів. Завантаж її заново й не переміщуй файли всередині."
  }

  $env:BOOSTER_3DP_URL = $apiUrl
  $env:BOOSTER_3DP_SERHIY_TOKEN = $token
  $env:PORT = "3107"
  $token = $null
  $serverArgument = '"' + $serverPath + '"'
  $server = Start-Process -FilePath $nodePath -ArgumentList @($serverArgument) -WorkingDirectory (Join-Path $PSScriptRoot "app") -NoNewWindow -PassThru
  try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 40; $attempt += 1) {
      if ($server.HasExited) { break }
      if (Test-PortInUse 3107) { $ready = $true; break }
      Start-Sleep -Milliseconds 250
    }
    if (-not $ready) { throw "Сторінка не запустилася. Спробуй ще раз." }
    Start-Process $localUrl
    $server.WaitForExit()
    exit $server.ExitCode
  } finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue }
  }
} catch {
  if ($Mode -eq "Start") {
    Show-Message $_.Exception.Message
  } else {
    Write-Host $_.Exception.Message -ForegroundColor Red
  }
  exit 1
}
