[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$OrderId,

    [Parameter(Mandatory = $true)]
    [ValidateRange(0.01, 1000000)]
    [double]$ExpectedPackagingUah,

    [ValidateRange(1, 50)]
    [int]$RecentSalesLimit = 50,

    [ValidateRange(0, 45)]
    [int]$WaitSeconds = 20,

    [switch]$ExpectNo3dpRow
)

# Read-only verifier for a test order saved through the normal CRM UI after
# 3D-P-010 is deployed. It never calls add_sale, update_sale, or a 3D-P POST.
$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30
$orderHeader = '№ замовлення'
$expenseHeader = 'Витрати BoosterShop за од., грн'

function Write-3dp010Step {
    param([Parameter(Mandatory = $true)][string]$Message)
    Write-Host "`n[3D-P-010 packaging verifier] $Message" -ForegroundColor Cyan
}

function Write-3dp010Snapshot {
    param([Parameter(Mandatory = $true)][string]$Label, [AllowNull()]$Value)
    Write-Host "--- $Label ---" -ForegroundColor Yellow
    if ($null -eq $Value) { '[]'; return }
    $Value | ConvertTo-Json -Depth 8
}

function Read-3dp010Secret {
    param([Parameter(Mandatory = $true)][string]$Prompt)
    $secureValue = Read-Host -Prompt $Prompt -AsSecureString
    if ($secureValue.Length -eq 0) { throw "$Prompt cannot be empty." }
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

function Get-3dp010Property {
    param([Parameter(Mandatory = $true)]$Object, [Parameter(Mandatory = $true)][string]$Name)
    $property = $Object.PSObject.Properties[$Name]
    if ($null -eq $property) { throw "Response is missing required property '$Name'." }
    return $property.Value
}

function Assert-3dp010Success {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) { throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)" }
}

function Assert-3dp010Money {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Actual, [Parameter(Mandatory = $true)]$Expected)
    if ([Math]::Abs(([double]$Actual) - ([double]$Expected)) -gt 0.005) {
        throw "$Name expected $Expected грн, received $Actual грн."
    }
}

function Invoke-3dp010Get {
    param(
        [Parameter(Mandatory = $true)][string]$BaseUrl,
        [Parameter(Mandatory = $true)][string]$Token,
        [Parameter(Mandatory = $true)][hashtable]$Query,
        [Parameter(Mandatory = $true)][string]$Label
    )
    $pairs = @($Query.GetEnumerator() | ForEach-Object {
        '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value)
    })
    $joiner = if ($BaseUrl.Contains('?')) { '&' } else { '?' }
    $uri = $BaseUrl + $joiner + (($pairs + ('token=' + [uri]::EscapeDataString($Token))) -join '&')
    Write-Host "GET $Label" -ForegroundColor DarkGray
    return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec $requestTimeoutSeconds
}

$crmUrl = [string]$env:BOOSTER_CRM_URL
$crmToken = [string]$env:BOOSTER_CRM_TOKEN
$threeDpUrl = [string]$env:BOOSTER_3DP_URL
$threeDpToken = [string]$env:BOOSTER_3DP_TOKEN

if ([string]::IsNullOrWhiteSpace($crmUrl)) { $crmUrl = [string](Read-Host -Prompt 'Paste deployed main CRM /exec URL') }
if ([string]::IsNullOrWhiteSpace($threeDpUrl)) { $threeDpUrl = [string](Read-Host -Prompt 'Paste deployed 3D-P /exec URL') }
if (-not $crmUrl.EndsWith('/exec')) { throw 'BOOSTER_CRM_URL must be the deployed main CRM /exec URL.' }
if (-not $threeDpUrl.EndsWith('/exec')) { throw 'BOOSTER_3DP_URL must be the deployed 3D-P /exec URL.' }
if ([string]::IsNullOrWhiteSpace($crmToken)) { $crmToken = Read-3dp010Secret -Prompt 'Paste main CRM token (input is hidden)' }
if ([string]::IsNullOrWhiteSpace($threeDpToken)) { $threeDpToken = Read-3dp010Secret -Prompt 'Paste owner 3D-P token (input is hidden)' }

Write-3dp010Step 'Read-only mode: no CRM or 3D-P write request will be sent'
$deadline = (Get-Date).AddSeconds($WaitSeconds)
$attempt = 0
$lastSnapshot = $null

while ($true) {
    $attempt += 1
    $crm = Invoke-3dp010Get -BaseUrl $crmUrl -Token $crmToken -Query @{ action = 'recent_sales'; limit = $RecentSalesLimit } -Label 'main CRM recent_sales'
    Assert-3dp010Success -Name 'main CRM recent_sales' -Response $crm
    $crmMatches = @($crm.rows | Where-Object { [string]$_.order_id -eq $OrderId })
    if ($crmMatches.Count -gt 1) { throw "recent_sales returned $($crmMatches.Count) aggregated rows for '$OrderId'; stop before interpreting it." }

    $threeDp = Invoke-3dp010Get -BaseUrl $threeDpUrl -Token $threeDpToken -Query @{ action = '3dp_sales' } -Label '3D-P sales'
    Assert-3dp010Success -Name '3D-P sales' -Response $threeDp
    $matches = @($threeDp.rows | Where-Object { [string](Get-3dp010Property -Object $_ -Name $orderHeader) -eq $OrderId } | Sort-Object { [int]$_.row_number })
    $lastSnapshot = [pscustomobject]@{
        attempt = $attempt
        crm = if ($crmMatches.Count -eq 1) { [pscustomobject]@{ order_id = $crmMatches[0].order_id; row_index = $crmMatches[0].row_index; packaging_type = $crmMatches[0].packaging_type; packaging_cost = $crmMatches[0].packaging_cost } } else { $null }
        three_dp_rows = @($matches | ForEach-Object { [pscustomobject]@{ row_number = $_.row_number; order_id = Get-3dp010Property -Object $_ -Name $orderHeader; expense_g = Get-3dp010Property -Object $_ -Name $expenseHeader } })
    }

    if ($crmMatches.Count -eq 1) {
        Assert-3dp010Money -Name 'main CRM aggregated packaging_cost' -Actual $crmMatches[0].packaging_cost -Expected $ExpectedPackagingUah
    }

    if ($ExpectNo3dpRow) {
        if ($crmMatches.Count -eq 1 -and $matches.Count -eq 0) { break }
    }
    elseif ($crmMatches.Count -eq 1 -and $matches.Count -ge 1) {
        $first = $matches[0]
        $firstValue = Get-3dp010Property -Object $first -Name $expenseHeader
        $duplicateRows = @($matches | Select-Object -Skip 1 | Where-Object {
            [Math]::Abs(([double](Get-3dp010Property -Object $_ -Name $expenseHeader)) - $ExpectedPackagingUah) -lt 0.005
        })
        if ([Math]::Abs(([double]$firstValue) - $ExpectedPackagingUah) -lt 0.005 -and $duplicateRows.Count -eq 0) { break }
    }

    if ((Get-Date) -ge $deadline) {
        Write-3dp010Snapshot -Label 'last read-only snapshot' -Value $lastSnapshot
        if ($ExpectNo3dpRow) { throw "Expected no 3D-P row for '$OrderId' after a normal CRM save, but the expected read-only state was not observed." }
        throw "Packaging pull did not reach the expected first 3D-P row within $WaitSeconds seconds. No write was sent by this verifier."
    }
    Start-Sleep -Seconds 2
}

Write-3dp010Snapshot -Label 'verified main CRM and 3D-P values' -Value $lastSnapshot
if ($ExpectNo3dpRow) {
    [pscustomobject]@{ ok = $true; order_id = $OrderId; expected_packaging_uah = $ExpectedPackagingUah; result = 'CRM order is visible and no 3D-P row exists; this verifies the non-blocking missing-row scenario only when the owner has confirmed the CRM save succeeded.'; no_live_writes = $true } | ConvertTo-Json -Depth 6
}
else {
    [pscustomobject]@{ ok = $true; order_id = $OrderId; expected_packaging_uah = $ExpectedPackagingUah; result = 'Main CRM packaging_cost equals first matching 3D-P Продажі!G; later matching 3D-P rows do not duplicate the full order total.'; no_live_writes = $true } | ConvertTo-Json -Depth 6
}