$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)

function Get-UserVariable {
  param([string]$Name)
  return ([string][Environment]::GetEnvironmentVariable($Name, "User")).Trim()
}

function Invoke-Get {
  param([string]$Action, [hashtable]$Parameters)
  $pairs = @("action=" + [Uri]::EscapeDataString($Action), "token=" + [Uri]::EscapeDataString($script:token))
  foreach ($name in $Parameters.Keys) { $pairs += [Uri]::EscapeDataString($name) + "=" + [Uri]::EscapeDataString(([string]$Parameters[$name])) }
  $separator = "?"
  if ($script:apiUrl.Contains("?")) { $separator = "&" }
  try {
    return Invoke-RestMethod -Method Get -Uri ($script:apiUrl + $separator + ($pairs -join "&")) -TimeoutSec 45
  } catch {
    throw "Запит до 3D-таблиці не виконано. Перевір інтернет і повтори пізніше."
  }
}

function Invoke-Post {
  param([hashtable]$Body)
  $payload = @{} + $Body
  $payload.token = $script:token
  try {
    return Invoke-RestMethod -Method Post -Uri $script:apiUrl -ContentType "text/plain;charset=utf-8" -Body ($payload | ConvertTo-Json -Compress -Depth 5) -TimeoutSec 45
  } catch {
    throw "Запит до 3D-таблиці не виконано. Перевір інтернет і повтори пізніше."
  }
}

function Show-ExpectedRefusal {
  param([string]$Label, [object]$Payload)
  if ($Payload.ok) { throw "НЕБЕЗПЕКА: «$Label» не було відхилено. Припини перевірку й повідом власника." }
  Write-Host ("OK — {0}: {1}: {2}" -f $Label, $Payload.code, $Payload.error) -ForegroundColor Green
}

try {
  $script:apiUrl = Get-UserVariable "BOOSTER_3DP_URL"
  $script:token = Get-UserVariable "BOOSTER_3DP_SERHIY_TOKEN"
  if (-not $script:apiUrl -or -not $script:token) { throw "Спочатку один раз запусти «Запустити.bat»." }

  Write-Host "Перевіряю, що це окремий доступ Сергія…"
  $identity = Invoke-Get "3dp_bootstrap" @{ include_archived = "true" }
  if (-not $identity.ok -or [string]$identity.settings.range -ne "B2:B5") {
    throw "Доступ не підтверджено як доступ Сергія. Жодної перевірки запису не виконано."
  }

  Show-ExpectedRefusal "читання Налаштування поза B2:B5" (Invoke-Get "3dp_get_range" @{ sheet = "Налаштування"; range = "B1:B6" })
  Show-ExpectedRefusal "запис Налаштування поза B2:B5" (Invoke-Post @{ action = "3dp_write"; sheet = "Налаштування"; sku_or_row = 6; column = "B"; value = "WP3-QA-MUST-NOT-WRITE"; expected_current = "" })
  Show-ExpectedRefusal "створення періоду виплати" (Invoke-Post @{ action = "3dp_payout_create"; period = "2099-12"; note = "WP3 negative QA" })
  Show-ExpectedRefusal "закриття періоду виплати" (Invoke-Post @{ action = "3dp_payout_mark_paid"; row_number = 2; expected_period = "WP3-QA"; paid_date = "2099-12-31" })
  Show-ExpectedRefusal "присвоєння артикулу" (Invoke-Post @{ action = "3dp_nomenclature_assign_sku"; draft_sku = "DRAFT-WP3-QA"; expected_draft_sku = "DRAFT-WP3-QA"; sku = "FIG-WP3Q-999" })
  Write-Host "Усі п’ять заборон спрацювали. Зроби один знімок цього вікна." -ForegroundColor Green
  $script:token = $null
  exit 0
} catch {
  $script:token = $null
  Write-Host $_.Exception.Message -ForegroundColor Red
  exit 1
}
