[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$TestSku,

    [Parameter(Mandatory = $true)]
    [int]$StockDelta,

    [ValidateRange(1, 1000000)]
    [int]$BatchQuantity = 2,

    [ValidateRange(0.001, 1000000)]
    [double]$BatchTotalWeightG = 12.5,

    [ValidateRange(0.001, 1000000)]
    [double]$BatchTotalPrintTimeH = 0.5,

    [ValidateRange(0.001, 1000000)]
    [double]$BatchSpoolWeightG = 1000,

    [ValidateRange(0.001, 1000000)]
    [double]$BatchSpoolPriceUah = 750,

    [switch]$ConfirmLiveWrites
)

$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30
$availabilityHeader = 'Наявно зараз, шт'
$statusHeader = 'API_статус_запису'
$stockReason = '3D-P-008 Addendum #2 positive smoke ' + [guid]::NewGuid().ToString('N')

if (-not $ConfirmLiveWrites) {
    throw 'This script writes live test data. Re-run with -ConfirmLiveWrites only for an owner-selected test SKU.'
}
if ($StockDelta -eq 0) {
    throw 'StockDelta must be a non-zero whole number so the ledger test proves a real adjustment.'
}

function Write-3dpStep {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host "`n[3D-P Addendum #2 positive smoke] $Message" -ForegroundColor Cyan
}

function Write-3dpSnapshot {
    param(
        [Parameter(Mandatory = $true)][string]$Label,
        [AllowNull()]$Value
    )
    Write-Host "--- $Label ---" -ForegroundColor Yellow
    if ($null -eq $Value) {
        '[]'
        return
    }
    $Value | ConvertTo-Json -Depth 8
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
    Write-Host "GET $($Query.action)" -ForegroundColor DarkGray
    return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec $requestTimeoutSeconds
}

function Invoke-3dpPost {
    param([Parameter(Mandatory = $true)][hashtable]$Payload)

    $request = @{} + $Payload
    $request.token = $ownerToken
    Write-Host "POST $($Payload.action)" -ForegroundColor DarkGray
    return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body ($request | ConvertTo-Json -Compress -Depth 8) -TimeoutSec $requestTimeoutSeconds
}

function Assert-3dpSuccess {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) {
        throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)"
    }
}

function Assert-3dpEqual {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [AllowNull()]$Actual,
        [AllowNull()]$Expected
    )
    if ([string]$Actual -ne [string]$Expected) {
        throw "$Name expected '$Expected', received '$Actual'."
    }
}

function Assert-3dpNumberEqual {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)]$Expected
    )
    if ([Math]::Abs(([double]$Actual) - ([double]$Expected)) -gt 0.000001) {
        throw "$Name expected $Expected, received $Actual."
    }
}

function Get-3dpProperty {
    param(
        [Parameter(Mandatory = $true)]$Object,
        [Parameter(Mandatory = $true)][string]$Name
    )
    $property = $Object.PSObject.Properties[$Name]
    if ($null -eq $property) {
        throw "Response is missing required property '$Name'."
    }
    return $property.Value
}

function Find-3dpSku {
    param(
        [Parameter(Mandatory = $true)]$Response,
        [Parameter(Mandatory = $true)][string]$Sku,
        [Parameter(Mandatory = $true)][string]$Context
    )
    $matches = @($Response.rows | Where-Object { [string]$_.SKU -eq $Sku })
    if ($matches.Count -ne 1) {
        throw "$Context expected exactly one '$Sku' row, found $($matches.Count)."
    }
    return $matches[0]
}

function Get-3dpAvailability {
    param([Parameter(Mandatory = $true)][string]$Sku)

    $response = Invoke-3dpGet -Query @{ action = '3dp_get_row'; sheet = 'Наявність'; sku = $Sku }
    Assert-3dpSuccess -Name 'read Наявність row' -Response $response
    $value = Get-3dpProperty -Object $response.row -Name $availabilityHeader
    return [pscustomobject]@{
        sku = $Sku
        row_number = $response.row.row_number
        availability_g = $value
        row = $response.row
    }
}

function Wait-3dpAvailability {
    param(
        [Parameter(Mandatory = $true)][string]$Sku,
        [Parameter(Mandatory = $true)][int]$ExpectedValue
    )

    $last = $null
    for ($attempt = 1; $attempt -le 5; $attempt += 1) {
        $last = Get-3dpAvailability -Sku $Sku
        if ([int]$last.availability_g -eq $ExpectedValue) {
            return $last
        }
        Start-Sleep -Seconds 1
    }
    throw "Наявність!G did not recalculate to $ExpectedValue within 5 seconds; last value was $($last.availability_g)."
}

Write-3dpStep '1/4: owner-only idempotent setup preflight'
$setup = Invoke-3dpPost -Payload @{ action = '3dp_setup_addendum2' }
Assert-3dpSuccess -Name 'repeat Addendum #2 setup through API' -Response $setup
Write-3dpSnapshot -Label 'setup response' -Value $setup
if ($setup.already_applied -ne $true) {
    throw 'Safety stop: setup reported schema changes. Inspect them before any positive test write.'
}

Write-3dpStep 'Read selected active test SKU and Наявність!G before writes'
$activeSkusBefore = Invoke-3dpGet -Query @{ action = '3dp_skus' }
Assert-3dpSuccess -Name 'read active SKU list before test' -Response $activeSkusBefore
$targetBefore = Find-3dpSku -Response $activeSkusBefore -Sku $TestSku -Context 'active 3dp_skus before archive'
Assert-3dpEqual -Name 'test SKU technical status before archive' -Actual (Get-3dpProperty -Object $targetBefore -Name $statusHeader) -Expected 'Активний'
$availabilityBefore = Get-3dpAvailability -Sku $TestSku
$stockBefore = [int]$availabilityBefore.availability_g
if (($stockBefore + $StockDelta) -lt 0) {
    throw "Safety stop: Наявність!G=$stockBefore plus StockDelta=$StockDelta would be negative."
}
Write-3dpSnapshot -Label 'before values' -Value ([pscustomobject]@{
    sku = $TestSku
    nomenclature_row = $targetBefore.row_number
    technical_status = Get-3dpProperty -Object $targetBefore -Name $statusHeader
    'Наявність!G' = $stockBefore
    planned_stock_delta = $StockDelta
})

Write-3dpStep '2/4: save and fresh-read all five batch-draft values'
$draftBefore = Invoke-3dpGet -Query @{ action = '3dp_batch_draft'; sku = $TestSku }
Assert-3dpSuccess -Name 'read batch draft before save' -Response $draftBefore
$draftValues = @{
    quantity = $BatchQuantity
    total_weight_g = $BatchTotalWeightG
    total_print_time_h = $BatchTotalPrintTimeH
    spool_weight_g = $BatchSpoolWeightG
    spool_price_uah = $BatchSpoolPriceUah
}
$draftExpected = @{
    quantity = $draftBefore.values.quantity
    total_weight_g = $draftBefore.values.total_weight_g
    total_print_time_h = $draftBefore.values.total_print_time_h
    spool_weight_g = $draftBefore.values.spool_weight_g
    spool_price_uah = $draftBefore.values.spool_price_uah
}
Write-3dpSnapshot -Label 'batch draft before' -Value ([pscustomobject]@{ found = $draftBefore.found; values = $draftBefore.values })
$draftSave = Invoke-3dpPost -Payload @{
    action = '3dp_batch_draft_save'
    sku = $TestSku
    values = $draftValues
    expected_current = $draftExpected
}
Assert-3dpSuccess -Name 'save batch draft' -Response $draftSave
$draftAfter = Invoke-3dpGet -Query @{ action = '3dp_batch_draft'; sku = $TestSku }
Assert-3dpSuccess -Name 'fresh-read batch draft after save' -Response $draftAfter
if ($draftAfter.found -ne $true) {
    throw 'Batch draft was not found after a successful save.'
}
foreach ($field in $draftValues.Keys) {
    Assert-3dpNumberEqual -Name "fresh batch draft $field" -Actual $draftAfter.values.$field -Expected $draftValues[$field]
}
Write-3dpSnapshot -Label 'batch draft after' -Value ([pscustomobject]@{ save = $draftSave; fresh_values = $draftAfter.values })

Write-3dpStep '3/4: archive, verify include_archived=true, then restore the test SKU'
$archiveApplied = $false
$archiveReason = '3D-P-008 Addendum #2 positive smoke archive/restore'
try {
    $archive = Invoke-3dpPost -Payload @{
        action = '3dp_nomenclature_archive'
        row = $targetBefore.row_number
        expected_status = 'Активний'
        reason = $archiveReason
    }
    Assert-3dpSuccess -Name 'archive test SKU' -Response $archive
    $archiveApplied = $true

    $activeAfterArchive = Invoke-3dpGet -Query @{ action = '3dp_skus' }
    Assert-3dpSuccess -Name 'read active SKU list after archive' -Response $activeAfterArchive
    $activeMatchesAfterArchive = @($activeAfterArchive.rows | Where-Object { [string]$_.SKU -eq $TestSku })
    if ($activeMatchesAfterArchive.Count -ne 0) {
        throw "Archived test SKU '$TestSku' is still visible in active 3dp_skus."
    }

    $allAfterArchive = Invoke-3dpGet -Query @{ action = '3dp_skus'; include_archived = 'true' }
    Assert-3dpSuccess -Name 'read include_archived SKU list' -Response $allAfterArchive
    $archivedRow = Find-3dpSku -Response $allAfterArchive -Sku $TestSku -Context 'include_archived=true after archive'
    Assert-3dpEqual -Name 'archived SKU technical status' -Actual (Get-3dpProperty -Object $archivedRow -Name $statusHeader) -Expected 'Архів'
    Write-3dpSnapshot -Label 'archive result and visibility' -Value ([pscustomobject]@{
        archive = $archive
        active_3dp_skus_contains_test_sku = $false
        include_archived_row = $archivedRow
    })
}
finally {
    if ($archiveApplied) {
        Write-3dpStep 'Restoring test SKU after archive assertion'
        $restore = Invoke-3dpPost -Payload @{
            action = '3dp_nomenclature_restore'
            row = $targetBefore.row_number
            expected_status = 'Архів'
            reason = $archiveReason
        }
        Assert-3dpSuccess -Name 'restore test SKU' -Response $restore
        Write-3dpSnapshot -Label 'restore result' -Value $restore
    }
}
$activeAfterRestore = Invoke-3dpGet -Query @{ action = '3dp_skus' }
Assert-3dpSuccess -Name 'read active SKU list after restore' -Response $activeAfterRestore
$restoredRow = Find-3dpSku -Response $activeAfterRestore -Sku $TestSku -Context 'active 3dp_skus after restore'
Assert-3dpEqual -Name 'restored SKU technical status' -Actual (Get-3dpProperty -Object $restoredRow -Name $statusHeader) -Expected 'Активний'
Write-3dpSnapshot -Label 'restored active SKU' -Value $restoredRow

Write-3dpStep '4/4: adjust Наявність!G and verify the append-only ledger'
$ledgerBefore = Invoke-3dpGet -Query @{ action = '3dp_stock_adjustments'; sku = $TestSku; limit = '5' }
Assert-3dpSuccess -Name 'read stock ledger before adjustment' -Response $ledgerBefore
Write-3dpSnapshot -Label 'stock ledger before' -Value $ledgerBefore.rows
$stockAdjustment = Invoke-3dpPost -Payload @{
    action = '3dp_adjust_stock'
    sku = $TestSku
    expected_current = $stockBefore
    delta = $StockDelta
    reason = $stockReason
}
Assert-3dpSuccess -Name 'adjust stock' -Response $stockAdjustment
Assert-3dpNumberEqual -Name 'stock action old value' -Actual $stockAdjustment.old_value -Expected $stockBefore
Assert-3dpNumberEqual -Name 'stock action delta' -Actual $stockAdjustment.delta -Expected $StockDelta
$stockExpectedAfter = $stockBefore + $StockDelta
Assert-3dpNumberEqual -Name 'stock action new value' -Actual $stockAdjustment.new_value -Expected $stockExpectedAfter
$availabilityAfter = Wait-3dpAvailability -Sku $TestSku -ExpectedValue $stockExpectedAfter
$ledgerAfter = Invoke-3dpGet -Query @{ action = '3dp_stock_adjustments'; sku = $TestSku; limit = '20' }
Assert-3dpSuccess -Name 'read stock ledger after adjustment' -Response $ledgerAfter
$ledgerMatch = @($ledgerAfter.rows | Where-Object {
    ([string](Get-3dpProperty -Object $_ -Name 'SKU') -eq $TestSku) -and
    ([int](Get-3dpProperty -Object $_ -Name 'Зміна наявності, шт') -eq $StockDelta) -and
    ([string](Get-3dpProperty -Object $_ -Name 'Причина') -eq $stockReason)
})
if ($ledgerMatch.Count -lt 1) {
    throw 'The expected stock-adjustment ledger row was not returned by the bounded API read.'
}
Write-3dpSnapshot -Label 'Наявність!G before/after and stock action' -Value ([pscustomobject]@{
    sku = $TestSku
    before = $stockBefore
    delta = $StockDelta
    after = $availabilityAfter.availability_g
    action = $stockAdjustment
})
Write-3dpSnapshot -Label 'matching ledger row' -Value $ledgerMatch[0]

[pscustomobject]@{
    ok = $true
    test_sku = $TestSku
    addendum2_setup_already_applied = $setup.already_applied
    batch_draft_saved_and_fresh_read = $draftAfter.values
    sku_archive_restore = 'active -> archived (hidden from active list, visible with include_archived=true) -> active'
    stock_adjustment = [pscustomobject]@{
        before = $stockBefore
        delta = $StockDelta
        after = $availabilityAfter.availability_g
        reason = $stockReason
        ledger_row = $ledgerMatch[0]
    }
    live_business_data_writes = @('batch draft save', 'SKU archive', 'SKU restore', 'stock adjustment')
} | ConvertTo-Json -Depth 8