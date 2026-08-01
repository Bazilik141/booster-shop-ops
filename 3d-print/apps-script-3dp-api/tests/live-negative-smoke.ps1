[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$apiUrl = [string]$env:BOOSTER_3DP_URL
$ownerToken = [string]$env:BOOSTER_3DP_TOKEN

if ([string]::IsNullOrWhiteSpace($apiUrl) -or -not $apiUrl.EndsWith('/exec')) {
    throw 'Set BOOSTER_3DP_URL to the deployed Apps Script /exec URL.'
}

if ([string]::IsNullOrWhiteSpace($ownerToken)) {
    throw 'Set BOOSTER_3DP_TOKEN locally. Never paste it into this file or chat.'
}

function Invoke-3dpGet {
    param([Parameter(Mandatory = $true)][string]$Action)

    $uri = '{0}?action={1}&token={2}' -f $apiUrl, [uri]::EscapeDataString($Action), [uri]::EscapeDataString($ownerToken)
    return Invoke-RestMethod -Method Get -Uri $uri
}

function Invoke-3dpPost {
    param([Parameter(Mandatory = $true)][hashtable]$Payload)

    $request = @{} + $Payload
    $request.token = $ownerToken
    $body = $request | ConvertTo-Json -Compress -Depth 8
    return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body $body
}

function Assert-3dpErrorCode {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)]$Response,
        [Parameter(Mandatory = $true)][string]$ExpectedCode
    )

    if ($Response.ok -ne $false -or [string]$Response.code -ne $ExpectedCode) {
        $actual = $Response | ConvertTo-Json -Compress -Depth 8
        throw "$Name expected $ExpectedCode, received: $actual"
    }
}

$overview = Invoke-3dpGet -Action '3dp_overview'
if ($overview.ok -ne $true) {
    throw "3dp_overview failed: $($overview | ConvertTo-Json -Compress -Depth 8)"
}

$skus = Invoke-3dpGet -Action '3dp_skus'
if ($skus.ok -ne $true) {
    throw "3dp_skus failed: $($skus | ConvertTo-Json -Compress -Depth 8)"
}

$firstSku = $skus.rows | Where-Object {
    -not [string]::IsNullOrWhiteSpace([string]$_.SKU)
} | Select-Object -First 1

if ($null -eq $firstSku) {
    throw 'No real SKU is available for bounded negative write tests.'
}

$sku = [string]$firstSku.SKU
$staleSentinel = '__3DP_EXPECTED_STALE_VALUE__'

$formulaResponse = Invoke-3dpPost -Payload @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = $sku
    column = 'K'
    value = 0
    expected_current = $staleSentinel
}
Assert-3dpErrorCode -Name 'formula cell guard' -Response $formulaResponse -ExpectedCode 'FORMULA_CELL'

$columnResponse = Invoke-3dpPost -Payload @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = $sku
    column = 'P'
    value = 'must-not-write'
    expected_current = $staleSentinel
}
Assert-3dpErrorCode -Name 'column whitelist guard' -Response $columnResponse -ExpectedCode 'COLUMN_NOT_ALLOWED'

$staleResponse = Invoke-3dpPost -Payload @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = $sku
    column = 'J'
    value = 0
    expected_current = $staleSentinel
}
Assert-3dpErrorCode -Name 'optimistic lock guard' -Response $staleResponse -ExpectedCode 'STALE_WRITE'

[pscustomobject]@{
    ok = $true
    sku = $sku
    read_actions = @('3dp_overview', '3dp_skus')
    negative_write_tests = @('FORMULA_CELL', 'COLUMN_NOT_ALLOWED', 'STALE_WRITE')
    live_cells_changed = 0
} | ConvertTo-Json -Compress
