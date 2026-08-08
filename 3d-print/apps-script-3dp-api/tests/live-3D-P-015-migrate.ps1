[CmdletBinding()]
param(
    [switch]$ConfirmLiveWrite,
    [switch]$SaveEvidence,
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30
$migrationPostTimeoutSeconds = 300

function Read-3dpSecret {
    param([Parameter(Mandatory = $true)][string]$Prompt)

    $secureValue = Read-Host -Prompt $Prompt -AsSecureString
    if ($secureValue.Length -eq 0) { throw "$Prompt cannot be empty." }
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}

$apiUrl = [string]$env:BOOSTER_3DP_URL
$ownerToken = [string]$env:BOOSTER_3DP_TOKEN
if ([string]::IsNullOrWhiteSpace($apiUrl)) { $apiUrl = [string](Read-Host -Prompt 'Paste the deployed Apps Script /exec URL') }
if (-not $apiUrl.EndsWith('/exec')) { throw 'The Apps Script URL must end with /exec.' }
if ([string]::IsNullOrWhiteSpace($ownerToken)) { $ownerToken = Read-3dpSecret -Prompt 'Paste owner 3D-P token (input is hidden)' }
if (-not $ConfirmLiveWrite) { throw 'This runs the irreversible 3D-P-015 schema migration. Re-run with -ConfirmLiveWrite only after source deployment and a saved preflight evidence file.' }

function Invoke-3dpGet {
    param([Parameter(Mandatory = $true)][hashtable]$Query)

    $pairs = @($Query.GetEnumerator() | ForEach-Object {
        '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value)
    })
    $uri = $apiUrl + '?' + (($pairs + ('token=' + [uri]::EscapeDataString($ownerToken))) -join '&')
    return Invoke-RestMethod -Method Get -Uri $uri -TimeoutSec $requestTimeoutSeconds
}

function Invoke-3dpPost {
    param([Parameter(Mandatory = $true)][hashtable]$Payload)

    $request = @{} + $Payload
    $request.token = $ownerToken
    try {
        return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body ($request | ConvertTo-Json -Compress -Depth 8) -TimeoutSec $migrationPostTimeoutSeconds
    }
    catch {
        $message = [string]($_.Exception.Message)
        if ($message -match '(?i)(timed out|timeout)') {
            throw '3D-P-015 migration POST timed out after 300 seconds. A client timeout does not mean the migration failed. Do not rerun yet: run live-3D-P-015-preflight.ps1 -SaveEvidence, verify Налаштування!A1:C5 and the Номенклатура!K formula with B5, then inspect _Аудит_API for SETUP_3DP015.'
        }
        throw
    }
}

function Assert-3dpSuccess {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) { throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)" }
}

function Assert-3dpEndpoint {
    $overview = $null
    try {
        $overview = Invoke-3dpGet -Query @{ action = '3dp_overview' }
    }
    catch {
        throw '3D-P endpoint validation failed before any writes: the supplied /exec URL and token did not complete the 3dp_overview identity probe. Use the deployed 3D-P URL/token, not main CRM. No migration request was sent.'
    }

    if ($overview.ok -ne $true -or [string]$overview.action -ne '3dp_overview' -or $null -eq $overview.summary) {
        throw '3D-P endpoint validation failed before any writes: the supplied /exec URL/token is not the deployed 3D-P API response contract (expected successful 3dp_overview). Use the 3D-P URL/token, not main CRM. No migration request was sent.'
    }

    $expectedSummaryFields = @('sku_count', 'printed', 'defects', 'sold', 'given', 'available', 'accrued_serhiy_current_month')
    $missingSummaryFields = @($expectedSummaryFields | Where-Object { $null -eq $overview.summary.PSObject.Properties[$_] })
    if ($missingSummaryFields.Count -gt 0) {
        throw '3D-P endpoint validation failed before any writes: the supplied /exec URL/token is not the deployed 3D-P overview response. Use the 3D-P URL/token, not main CRM. No migration request was sent.'
    }

    return $overview
}

function Get-3dpRange {
    param([Parameter(Mandatory = $true)][string]$Sheet, [Parameter(Mandatory = $true)][string]$Range)
    $response = Invoke-3dpGet -Query @{ action = '3dp_get_range'; sheet = $Sheet; range = $Range }
    Assert-3dpSuccess -Name "$Sheet!$Range" -Response $response
    return $response
}

function Assert-HeaderRow {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Actual, [Parameter(Mandatory = $true)][AllowEmptyString()][string[]]$Expected)
    $actualText = @($Actual | ForEach-Object { [string]$_ })
    $separator = [char]31
    if (($actualText -join $separator) -ne ($Expected -join $separator)) {
        throw "$Name mismatch. Expected: $($Expected -join ' | '); actual: $($actualText -join ' | ')."
    }
}

$endpoint = Assert-3dpEndpoint
$migration = Invoke-3dpPost -Payload @{ action = '3dp_setup_3dp015' }
Assert-3dpSuccess -Name '3dp_setup_3dp015' -Response $migration

$settings = Get-3dpRange -Sheet 'Налаштування' -Range 'A1:C5'
$settingsRows = @($settings.values)
Assert-HeaderRow -Name 'Налаштування A1:C1' -Actual $settingsRows[0] -Expected @('Глобальні константи 3D-друку', '', '')
Assert-HeaderRow -Name 'Налаштування A2:A5' -Actual @($settingsRows[1..4] | ForEach-Object { [string]$_[0] }) -Expected @(
    'Потужність принтера, кВт', 'Ціна електроенергії, грн/кВт·год', 'Амортизація принтера, грн/год', 'Планований брак, частка'
)
Assert-HeaderRow -Name 'Налаштування C2:C5' -Actual @($settingsRows[1..4] | ForEach-Object { [string]$_[2] }) -Expected @(
    'кВт', 'грн/кВт·год', 'грн/год', 'частка (0.1 = 10%)'
)

$nomenclatureHeaders = Get-3dpRange -Sheet 'Номенклатура' -Range 'N1:S1'
Assert-HeaderRow -Name 'Номенклатура N1:S1' -Actual $nomenclatureHeaders.values[0] -Expected @(
    'Фурнітура (ціна-довідка), грн/шт', 'API_статус_запису', 'API_історія_змін',
    'РРЦ фактична, грн', 'Ціна під викуп, грн', 'Посилання на модель'
)

$salesHeaders = Get-3dpRange -Sheet 'Продажі' -Range 'T1:W1'
Assert-HeaderRow -Name 'Продажі T1:W1' -Actual $salesHeaders.values[0] -Expected @(
    'CRM row number', 'РРЦ на момент продажу, грн', 'Вартість фурнітури за од., грн (заморожена)', 'Платник фурнітури'
)

$skus = Invoke-3dpGet -Query @{ action = '3dp_skus'; include_archived = 'true' }
Assert-3dpSuccess -Name '3dp_skus' -Response $skus
$costChecks = @()
foreach ($sku in @($skus.rows)) {
    $rowNumber = [int]$sku.row_number
    if ($rowNumber -lt 2) { throw "3dp_skus returned invalid row_number '$($sku.row_number)' for SKU '$($sku.SKU)'." }
    $cost = Get-3dpRange -Sheet 'Номенклатура' -Range "K${rowNumber}:N${rowNumber}"
    $formula = [string]$cost.formulas[0][0]
    if ($formula -match ('\+N' + $rowNumber + '(?![0-9])')) { throw "Номенклатура!K$rowNumber still includes fixture N$rowNumber." }
    if ($formula -notmatch ('\)\*\(1\+''Налаштування''!\$B\$5\)')) { throw "Номенклатура!K$rowNumber does not apply the planned defect-rate multiplier from Налаштування!B5." }
    $costChecks += [pscustomobject]@{ sku = [string]$sku.SKU; row_number = $rowNumber; cost_formula = $formula; fixture_reference = $cost.values[0][3] }
}

$sales = Invoke-3dpGet -Query @{ action = '3dp_sales' }
Assert-3dpSuccess -Name '3dp_sales' -Response $sales
$lastSaleRow = 2
foreach ($sale in @($sales.rows)) { $lastSaleRow = [Math]::Max($lastSaleRow, [int]$sale.row_number) }
$financial = Get-3dpRange -Sheet 'Продажі' -Range "I2:L$lastSaleRow"
for ($row = 2; $row -le $lastSaleRow; $row += 1) {
    $marginFormula = [string]$financial.formulas[$row - 2][0]
    $serhiyAccrualFormula = [string]$financial.formulas[$row - 2][2]
    $boosterIncomeFormula = [string]$financial.formulas[$row - 2][3]
    if ($marginFormula -and $marginFormula -notmatch ('-IF\(W' + $row + '="власник";V' + $row + ';0\)')) {
        throw "Продажі!I$row does not subtract only owner-paid frozen fixture V$row."
    }
    if ($serhiyAccrualFormula -and $serhiyAccrualFormula -notmatch ('\+IF\(W' + $row + '="Сергій";V' + $row + ';0\)')) {
        throw "Продажі!K$row does not reimburse Serhiy-paid fixture V$row separately from F$row."
    }
    if ($boosterIncomeFormula -and $boosterIncomeFormula -notmatch ('-IF\(W' + $row + '="Сергій";V' + $row + ';0\)')) {
        throw "Продажі!L$row does not deduct the Serhiy fixture reimbursement from BoosterShop income."
    }
}

$analyticsHeaders = Get-3dpRange -Sheet 'Аналітика' -Range 'A3:N3'
Assert-HeaderRow -Name 'Аналітика A3:N3' -Actual $analyticsHeaders.values[0] -Expected @(
    'SKU', 'Назва', 'Собівартість Сергія, грн', 'Витрати BoosterShop (фурнітура), грн', 'Час друку, год', '% прибутку Сергію',
    'РРЦ фактична', 'РРЦ рекомендована', 'Маржа BoosterShop, грн', 'Маржа BoosterShop, %', 'Нараховано Сергію, грн', 'Прибуток Сергію/год друку, грн', '', ''
)
$pending = Get-3dpRange -Sheet 'Аналітика' -Range 'H4:H17'
foreach ($index in 0..($pending.formulas.Count - 1)) {
    $formula = [string]$pending.formulas[$index][0]
    if ($formula -and $formula -notmatch '"pending"') { throw "Аналітика!H$($index + 4) must retain the explicit pending recommended-RRP placeholder." }
}
$analyticsFormulas = Get-3dpRange -Sheet 'Аналітика' -Range 'A4:L17'
$analyticsFormulaChecks = @()
foreach ($index in 0..($analyticsFormulas.formulas.Count - 1)) {
    $row = $index + 4
    $skuFormula = [string]$analyticsFormulas.formulas[$index][0]
    if (-not $skuFormula) { continue }
    $marginFormula = [string]$analyticsFormulas.formulas[$index][8]
    $marginPctFormula = [string]$analyticsFormulas.formulas[$index][9]
    $serhiyFormula = [string]$analyticsFormulas.formulas[$index][10]
    $hourFormula = [string]$analyticsFormulas.formulas[$index][11]
    if ($marginFormula -notmatch ('\(G' + $row + '-C' + $row + '-N\(D' + $row + '\)\)\*\(1-F' + $row + '\)')) { throw "Аналітика!I$row is not the post-split BoosterShop margin." }
    if ($marginPctFormula -notmatch ('I' + $row + '/G' + $row)) { throw "Аналітика!J$row is not the post-split margin percentage." }
    if ($serhiyFormula -notmatch ('C' + $row + '\+F' + $row + '\*\(G' + $row + '-C' + $row + '-N\(D' + $row + '\)\)')) { throw "Аналітика!K$row is not derived from the pre-split base inputs." }
    if ($hourFormula -notmatch ('K' + $row + '/E' + $row)) { throw "Аналітика!L$row must remain the Serhiy accrual per print hour." }
    $analyticsFormulaChecks += [pscustomobject]@{ row = $row; margin_i = $marginFormula; margin_pct_j = $marginPctFormula; accrual_k = $serhiyFormula; hourly_l = $hourFormula }
}

$report = [ordered]@{
    task = '3D-P-015'
    captured_at_local = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss K')
    mode = 'live_write'
    api_identity_probe = [ordered]@{ action = [string]$endpoint.action; summary_contract = '3dp_overview' }
    settings_a1_to_c5 = [ordered]@{ values = $settings.values; formulas = $settings.formulas }
    action = [ordered]@{ already_applied = [bool]$migration.already_applied; changes = @($migration.changes) }
    nomenclature_n_to_s = @($nomenclatureHeaders.values[0])
    sales_t_to_w = @($salesHeaders.values[0])
    cost_checks = $costChecks
    analytics_a3_to_n3 = @($analyticsHeaders.values[0])
    analytics_formula_checks = $analyticsFormulaChecks
}

$json = $report | ConvertTo-Json -Depth 10
if ($SaveEvidence) {
    if ([string]::IsNullOrWhiteSpace($OutputPath)) {
        $OutputPath = Join-Path $PSScriptRoot ('..\..\..\diagnostics\3D-P-015_live-migration_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.json')
    }
    $resolved = [IO.Path]::GetFullPath($OutputPath)
    $diagnosticsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..\..\diagnostics'))
    if (-not $resolved.StartsWith($diagnosticsRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'OutputPath must be inside diagnostics/.' }
    [IO.File]::WriteAllText($resolved, $json + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
    Write-Host "Evidence saved: $resolved" -ForegroundColor Cyan
}

$json
