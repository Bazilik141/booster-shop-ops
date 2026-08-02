[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30

function Write-3dpProgress {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host "[3D-P smoke] $Message" -ForegroundColor Cyan
}

function Read-3dpSecret {
    param([Parameter(Mandatory = $true)][string]$Prompt)

    $secureValue = Read-Host -Prompt $Prompt -AsSecureString
    if ($secureValue.Length -eq 0) {
        throw "$Prompt cannot be empty."
    }

    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
    }
}

$apiUrl = [string]$env:BOOSTER_3DP_URL
$ownerToken = [string]$env:BOOSTER_3DP_TOKEN
$serhiyToken = [string]$env:BOOSTER_3DP_SERHIY_TOKEN

if ([string]::IsNullOrWhiteSpace($apiUrl)) {
    $apiUrl = [string](Read-Host -Prompt 'Paste the deployed Apps Script /exec URL')
}
if (-not $apiUrl.EndsWith('/exec')) {
    throw 'Set BOOSTER_3DP_URL to the deployed Apps Script /exec URL.'
}
if ([string]::IsNullOrWhiteSpace($ownerToken)) {
    $ownerToken = Read-3dpSecret -Prompt 'Paste owner 3D-P token (input is hidden)'
}
if ([string]::IsNullOrWhiteSpace($serhiyToken)) {
    $serhiyToken = Read-3dpSecret -Prompt 'Paste Serhiy 3D-P token (input is hidden)'
}

Write-3dpProgress 'Credentials accepted. Starting live checks.'

function Invoke-3dpGet {
    param([Parameter(Mandatory = $true)][hashtable]$Query)
    $pairs = @($Query.GetEnumerator() | ForEach-Object {
        '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value)
    })
    $uri = $apiUrl + '?' + (($pairs + ('token=' + [uri]::EscapeDataString($ownerToken))) -join '&')
    Write-3dpProgress ("GET {0}" -f [string]$Query.action)
    return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec $requestTimeoutSeconds
}

function Invoke-3dpPost {
    param(
        [Parameter(Mandatory = $true)][hashtable]$Payload,
        [Parameter(Mandatory = $true)][string]$Token
    )
    $request = @{} + $Payload
    $request.token = $Token
    Write-3dpProgress ("POST {0}" -f [string]$Payload.action)
    return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body ($request | ConvertTo-Json -Compress -Depth 8) -TimeoutSec $requestTimeoutSeconds
}

function Assert-3dpSuccess {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) { throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)" }
}

function Assert-3dpErrorCode {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)]$Response,
        [Parameter(Mandatory = $true)][string]$ExpectedCode
    )
    if ($Response.ok -ne $false -or [string]$Response.code -ne $ExpectedCode) {
        throw "$Name expected $ExpectedCode, received: $($Response | ConvertTo-Json -Compress -Depth 8)"
    }
}

$settings = Invoke-3dpGet -Query @{ action = '3dp_get_range'; sheet = 'Налаштування'; range = 'A1:C4' }
Assert-3dpSuccess -Name 'read approved settings block' -Response $settings
$settingsValue = $settings.values[1][1]

$skus = Invoke-3dpGet -Query @{ action = '3dp_skus' }
Assert-3dpSuccess -Name 'read active SKU' -Response $skus
if (-not $skus.rows -or $skus.rows.Count -lt 1) { throw 'No active real SKU is available for the Addendum #2 smoke.' }
$sku = [string]$skus.rows[0].SKU

$draft = Invoke-3dpGet -Query @{ action = '3dp_batch_draft'; sku = $sku }
Assert-3dpSuccess -Name 'read batch draft' -Response $draft

$stockLog = Invoke-3dpGet -Query @{ action = '3dp_stock_adjustments'; sku = $sku; limit = '1' }
Assert-3dpSuccess -Name 'read bounded stock-adjustment history' -Response $stockLog

$ownerSettingsWrite = Invoke-3dpPost -Token $ownerToken -Payload @{
    action = '3dp_write'
    sheet = 'Налаштування'
    sku_or_row = 2
    column = 'B'
    value = $settingsValue
    expected_current = $settingsValue
}
Assert-3dpSuccess -Name 'owner same-value settings audit write' -Response $ownerSettingsWrite

$serhiySettingsWrite = Invoke-3dpPost -Token $serhiyToken -Payload @{
    action = '3dp_write'
    sheet = 'Налаштування'
    sku_or_row = 2
    column = 'B'
    value = $settingsValue
    expected_current = $settingsValue
}
Assert-3dpErrorCode -Name 'Serhiy settings whitelist guard' -Response $serhiySettingsWrite -ExpectedCode 'COLUMN_NOT_ALLOWED'

$statusOverwrite = Invoke-3dpPost -Token $ownerToken -Payload @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = $skus.rows[0].row_number
    column = 'O'
    value = 'Архів'
    expected_current = 'Активний'
}
Assert-3dpErrorCode -Name 'specialized SKU archive guard' -Response $statusOverwrite -ExpectedCode 'COLUMN_NOT_ALLOWED'

$staleStock = Invoke-3dpPost -Token $ownerToken -Payload @{
    action = '3dp_adjust_stock'
    sku = $sku
    expected_current = '__3DP_EXPECTED_STALE_STOCK__'
    delta = -1
    reason = 'smoke stale guard'
}
Assert-3dpErrorCode -Name 'stock optimistic-lock guard' -Response $staleStock -ExpectedCode 'STALE_WRITE'

Write-3dpProgress 'All smoke assertions passed.'

[pscustomobject]@{
    ok = $true
    sku = $sku
    no_net_business_data_change = $true
    audit_records_expected = 1
    read_actions = @('3dp_get_range settings', '3dp_skus', '3dp_batch_draft', '3dp_stock_adjustments')
    guards = @('Serhiy settings COLUMN_NOT_ALLOWED', 'generic SKU status COLUMN_NOT_ALLOWED', 'stock STALE_WRITE')
    manual_qa_remaining = @('batch draft round-trip with intended data', 'test SKU archive/restore', 'stock adjustment with reason and visible ledger')
} | ConvertTo-Json -Compress