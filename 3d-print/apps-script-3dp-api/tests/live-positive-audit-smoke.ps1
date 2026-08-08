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
    param([Parameter(Mandatory = $true)][hashtable]$Query)
    $pairs = @($Query.GetEnumerator() | ForEach-Object {
        '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value)
    })
    $uri = $apiUrl + '?' + (($pairs + ('token=' + [uri]::EscapeDataString($ownerToken))) -join '&')
    return Invoke-RestMethod -Method Get -Uri $uri
}

function Invoke-3dpPost {
    param([Parameter(Mandatory = $true)][hashtable]$Payload)
    $request = @{} + $Payload
    $request.token = $ownerToken
    return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body ($request | ConvertTo-Json -Compress -Depth 8)
}

function Assert-3dpSuccess {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) { throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)" }
}

$skus = Invoke-3dpGet -Query @{ action = '3dp_skus' }
Assert-3dpSuccess -Name 'read real SKU for no-net-change audit smoke' -Response $skus
if (-not $skus.rows -or $skus.rows.Count -lt 1) { throw 'No real SKU is available for the no-net-change audit smoke.' }

$row = $skus.rows[0]
$fixtureHeader = 'Фурнітура (ціна-довідка), грн/шт'
if ($null -eq $row.$fixtureHeader) { throw "Fixture header '$fixtureHeader' was not returned by 3dp_skus." }

$response = Invoke-3dpPost -Payload @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = $row.row_number
    column = 'N'
    value = $row.$fixtureHeader
    expected_current = $row.$fixtureHeader
}
Assert-3dpSuccess -Name 'guarded no-net-change fixture audit write' -Response $response

[pscustomobject]@{
    ok = $true
    target = 'Номенклатура!N' + $row.row_number
    sequence = 'current fixture value -> same fixture value'
    audit_records_expected = 1
    live_cells_changed = 0
} | ConvertTo-Json -Compress
