[CmdletBinding()]
param(
    [switch]$SaveEvidence,
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30

$apiUrl = [string]$env:BOOSTER_3DP_URL
$ownerToken = [string]$env:BOOSTER_3DP_TOKEN

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

if ([string]::IsNullOrWhiteSpace($apiUrl)) {
    $apiUrl = [string](Read-Host -Prompt 'Paste the deployed Apps Script /exec URL')
}
if (-not $apiUrl.EndsWith('/exec')) {
    throw 'The Apps Script URL must end with /exec.'
}
if ([string]::IsNullOrWhiteSpace($ownerToken)) {
    $ownerToken = Read-3dpSecret -Prompt 'Paste owner 3D-P token (input is hidden)'
}

function Invoke-3dpGet {
    param([Parameter(Mandatory = $true)][hashtable]$Query)

    $pairs = @($Query.GetEnumerator() | ForEach-Object {
        '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value)
    })
    $uri = $apiUrl + '?' + (($pairs + ('token=' + [uri]::EscapeDataString($ownerToken))) -join '&')
    return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec $requestTimeoutSeconds
}

function Assert-3dpSuccess {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)]$Response
    )

    if ($Response.ok -ne $true) {
        throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)"
    }
}

function Assert-3dpEndpoint {
    $overview = $null
    try {
        $overview = Invoke-3dpGet -Query @{ action = '3dp_overview' }
    }
    catch {
        throw '3D-P endpoint validation failed before any reads: the supplied /exec URL and token did not complete the 3dp_overview identity probe. Use the deployed 3D-P URL/token, not main CRM. No workbook changes were attempted.'
    }

    if ($overview.ok -ne $true -or [string]$overview.action -ne '3dp_overview' -or $null -eq $overview.summary) {
        throw '3D-P endpoint validation failed before any reads: the supplied /exec URL/token is not the deployed 3D-P API response contract (expected successful 3dp_overview). Use the 3D-P URL/token, not main CRM. No workbook changes were attempted.'
    }

    $expectedSummaryFields = @('sku_count', 'printed', 'defects', 'sold', 'given', 'available', 'accrued_serhiy_current_month')
    $missingSummaryFields = @($expectedSummaryFields | Where-Object { $null -eq $overview.summary.PSObject.Properties[$_] })
    if ($missingSummaryFields.Count -gt 0) {
        throw '3D-P endpoint validation failed before any reads: the supplied /exec URL/token is not the deployed 3D-P overview response. Use the 3D-P URL/token, not main CRM. No workbook changes were attempted.'
    }

    return $overview
}

function Get-3dpRange {
    param(
        [Parameter(Mandatory = $true)][string]$Sheet,
        [Parameter(Mandatory = $true)][string]$Range
    )

    $response = Invoke-3dpGet -Query @{ action = '3dp_get_range'; sheet = $Sheet; range = $Range }
    Assert-3dpSuccess -Name "$Sheet!$Range" -Response $response
    return $response
}

function Get-RangeCell {
    param(
        [Parameter(Mandatory = $true)]$Range,
        [Parameter(Mandatory = $true)][int]$Row,
        [Parameter(Mandatory = $true)][int]$Column
    )

    $valueRows = @($Range.values)
    $formulaRows = @($Range.formulas)
    if ($Row -lt 0 -or $Row -ge $valueRows.Count -or $Row -ge $formulaRows.Count) {
        throw "Response range does not contain row index $Row."
    }
    $valueCells = @($valueRows[$Row])
    $formulaCells = @($formulaRows[$Row])
    if ($Column -lt 0 -or $Column -ge $valueCells.Count -or $Column -ge $formulaCells.Count) {
        throw "Response range row $Row does not contain column index $Column."
    }
    return [pscustomobject]@{
        value = $valueCells[$Column]
        formula = $formulaCells[$Column]
    }
}

$endpoint = Assert-3dpEndpoint
$nomenclatureHeader = Get-3dpRange -Sheet 'Номенклатура' -Range 'A1:S1'
$settings = Get-3dpRange -Sheet 'Налаштування' -Range 'A1:C5'
$skus = Invoke-3dpGet -Query @{ action = '3dp_skus'; include_archived = 'true' }
Assert-3dpSuccess -Name '3dp_skus' -Response $skus

$nomenclatureCostFormulas = @()
foreach ($sku in @($skus.rows)) {
    $rowNumber = [int]$sku.row_number
    if ($rowNumber -lt 2) { throw "3dp_skus returned invalid row_number '$($sku.row_number)' for SKU '$($sku.SKU)'." }
    $cost = Get-3dpRange -Sheet 'Номенклатура' -Range "K${rowNumber}:N${rowNumber}"
    $nomenclatureCostFormulas += [pscustomobject]@{
        sku = [string]$sku.SKU
        row_number = $rowNumber
        cost_k = Get-RangeCell -Range $cost -Row 0 -Column 0
        fixture_price_n = Get-RangeCell -Range $cost -Row 0 -Column 3
    }
}

$sales = Invoke-3dpGet -Query @{ action = '3dp_sales' }
Assert-3dpSuccess -Name '3dp_sales' -Response $sales
$lastSaleRow = [Math]::Max(2, (@($sales.rows | ForEach-Object { [int]$_.row_number } | Measure-Object -Maximum).Maximum))

$salesHeaders = Get-3dpRange -Sheet 'Продажі' -Range 'A1:W1'
$salesFrozen = Get-3dpRange -Sheet 'Продажі' -Range "F2:F$lastSaleRow"
$salesDerived = Get-3dpRange -Sheet 'Продажі' -Range "I2:L$lastSaleRow"
$salesPeriod = Get-3dpRange -Sheet 'Продажі' -Range "S2:S$lastSaleRow"
$salesTechnical = Get-3dpRange -Sheet 'Продажі' -Range "T1:W$lastSaleRow"
$analytics = Get-3dpRange -Sheet 'Аналітика' -Range 'A3:N17'

$report = [ordered]@{
    task = '3D-P-015'
    captured_at_local = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss K')
    mode = 'read_only'
    api_identity_probe = [ordered]@{ action = [string]$endpoint.action; summary_contract = '3dp_overview' }
    settings_a1_to_c5 = [ordered]@{ values = $settings.values; formulas = $settings.formulas }
    nomenclature = [ordered]@{
        headers_a_to_s = @($nomenclatureHeader.values[0])
        cost_k_and_fixture_n_by_sku = $nomenclatureCostFormulas
    }
    sales = [ordered]@{
        last_live_row = $lastSaleRow
        headers_a_to_w = @($salesHeaders.values[0])
        frozen_cost_f = [ordered]@{ values = $salesFrozen.values; formulas = $salesFrozen.formulas }
        derived_i_to_l = [ordered]@{ values = $salesDerived.values; formulas = $salesDerived.formulas }
        period_s = [ordered]@{ values = $salesPeriod.values; formulas = $salesPeriod.formulas }
        technical_t_to_w = [ordered]@{ values = $salesTechnical.values; formulas = $salesTechnical.formulas }
    }
    analytics_a3_to_n17 = [ordered]@{
        values = $analytics.values
        formulas = $analytics.formulas
    }
}

$json = $report | ConvertTo-Json -Depth 12
if ($SaveEvidence) {
    if ([string]::IsNullOrWhiteSpace($OutputPath)) {
        $OutputPath = Join-Path $PSScriptRoot ('..\\..\\..\\diagnostics\\3D-P-015_live-preflight_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.json')
    }
    $resolved = [IO.Path]::GetFullPath($OutputPath)
    $diagnosticsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\\..\\..\\diagnostics'))
    if (-not $resolved.StartsWith($diagnosticsRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'OutputPath must be inside diagnostics/.'
    }
    [IO.File]::WriteAllText($resolved, $json + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
    Write-Host "Evidence saved: $resolved" -ForegroundColor Cyan
}

$json
