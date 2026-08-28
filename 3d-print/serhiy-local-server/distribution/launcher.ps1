[CmdletBinding()]
param(
  [ValidateSet("Start", "ChangeToken")]
  [string]$Mode = "Start"
)

$ErrorActionPreference = "Stop"
[Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)
$Host.UI.RawUI.WindowTitle = "Booster Shop — 3D-друк"

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
  $setx = Join-Path $env:SystemRoot "System32\setx.exe"
  & $setx $Name $Value *> $null
  if ($LASTEXITCODE -ne 0) { throw "Не вдалося зберегти доступ у Windows." }
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
  $client = [Net.Sockets.TcpClient]::new()
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
    Write-Host "Не вдалося зв’язатися з 3D-таблицею. Перевір інтернет або звернися до власника." -ForegroundColor Red
    return $false
  }
  if (-not $payload.ok) {
    Write-Host ([string]$payload.error) -ForegroundColor Red
    if ([string]$payload.code -eq "UNAUTHORIZED") {
      Write-Host "Запусти «Змінити токен.bat» і введи новий токен." -ForegroundColor Yellow
    }
    return $false
  }
  if ([string]$payload.settings.range -ne "B2:B5") {
    Write-Host "Наданий доступ не є окремим доступом Сергія. Сторінку не запущено." -ForegroundColor Red
    Write-Host "Звернися до власника й не використовуй цей токен." -ForegroundColor Yellow
    return $false
  }
  return $true
}

try {
  if ($Mode -eq "ChangeToken") {
    $newToken = Read-MaskedValue "Встав новий токен (символи не показуються)"
    Save-UserVariable "BOOSTER_3DP_SERHIY_TOKEN" $newToken
    $newToken = $null
    Write-Host "Новий токен збережено. Тепер запускай «Запустити.bat»." -ForegroundColor Green
    exit 0
  }

  if (Test-PortInUse 3107) {
    Write-Host "Сторінка вже запущена або місце 3107 зайняте іншою програмою." -ForegroundColor Red
    Write-Host "Закрий попереднє чорне вікно й спробуй ще раз." -ForegroundColor Yellow
    exit 1
  }

  $apiUrl = Get-UserVariable "BOOSTER_3DP_URL"
  if (-not $apiUrl) {
    $apiUrl = ([string](Read-Host "Встав адресу, яку надав власник")).Trim()
    if (-not (Test-WebAppUrl $apiUrl)) { throw "Адреса має починатися з https://. Нічого не збережено." }
    Save-UserVariable "BOOSTER_3DP_URL" $apiUrl
  }

  $token = Get-UserVariable "BOOSTER_3DP_SERHIY_TOKEN"
  if (-not $token) {
    $token = Read-MaskedValue "Встав токен (символи не показуються)"
    Save-UserVariable "BOOSTER_3DP_SERHIY_TOKEN" $token
  }

  if (-not (Invoke-IdentityCheck $apiUrl $token)) { exit 1 }

  $nodePath = Join-Path $PSScriptRoot "runtime\node.exe"
  $serverPath = Join-Path $PSScriptRoot "app\server.mjs"
  if (-not (Test-Path -LiteralPath $nodePath -PathType Leaf) -or -not (Test-Path -LiteralPath $serverPath -PathType Leaf)) {
    throw "У папці бракує потрібних файлів. Завантаж її заново й не переміщуй файли всередині."
  }

  $env:BOOSTER_3DP_URL = $apiUrl
  $env:BOOSTER_3DP_SERHIY_TOKEN = $token
  $env:PORT = "3107"
  $token = $null
  $localUrl = "http://127.0.0.1:3107"
  $serverArgument = '"' + $serverPath + '"'
  $server = Start-Process -FilePath $nodePath -ArgumentList @($serverArgument) -WorkingDirectory (Join-Path $PSScriptRoot "app") -NoNewWindow -PassThru
  try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 40; $attempt += 1) {
      if ($server.HasExited) { break }
      if (Test-PortInUse 3107) { $ready = $true; break }
      Start-Sleep -Milliseconds 250
    }
    if (-not $ready) { throw "Сторінка не запустилася. Закрий вікно й спробуй ще раз." }
    Start-Process $localUrl
    Write-Host "Сторінку відкрито в браузері." -ForegroundColor Green
    Write-Host "Не закривай це вікно під час роботи. Закриття вікна зупинить сторінку."
    $server.WaitForExit()
    exit $server.ExitCode
  } finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue }
  }
} catch {
  Write-Host $_.Exception.Message -ForegroundColor Red
  exit 1
}
