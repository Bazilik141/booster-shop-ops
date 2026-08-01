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

function Invoke-3dpPost {
    param([Parameter(Mandatory = $true)][hashtable]$Payload)

    $request = @{} + $Payload
    $request.token = $ownerToken
    $body = $request | ConvertTo-Json -Compress -Depth 8
    return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body $body
}

function Assert-3dpSuccess {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)]$Response
    )

    if ($Response.ok -ne $true) {
        $actual = $Response | ConvertTo-Json -Compress -Depth 8
        throw "$Name failed: $actual"
    }
}

$target = @{
    action = '3dp_write'
    sheet = 'Номенклатура'
    sku_or_row = 'FIG-CHARM-001'
    column = 'O'
}

# The expected blank value prevents any write if the owner has started using O3.
$writeResponse = Invoke-3dpPost -Payload ($target + @{
    value = 0
    expected_current = ''
})
Assert-3dpSuccess -Name 'guarded test write O3 blank -> 0' -Response $writeResponse

try {
    $restoreResponse = Invoke-3dpPost -Payload ($target + @{
        value = ''
        expected_current = 0
    })
    Assert-3dpSuccess -Name 'guarded restore O3 0 -> blank' -Response $restoreResponse
} catch {
    throw "The first write succeeded but automatic restore did not complete. O3 may be 0; do not continue with reconciliation. Details: $($_.Exception.Message)"
}

[pscustomobject]@{
    ok = $true
    target = 'Номенклатура!O3'
    sequence = 'blank -> 0 -> blank'
    audit_records_expected = 2
    live_cells_changed = 0
} | ConvertTo-Json -Compress
