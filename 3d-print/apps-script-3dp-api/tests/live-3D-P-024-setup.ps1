[CmdletBinding()]
param(
    [switch]$ConfirmLiveWrite,
    [switch]$SaveEvidence,
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$requestTimeoutSeconds = 30
$setupPostTimeoutSeconds = 120

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
if ([string]::IsNullOrWhiteSpace($apiUrl)) { $apiUrl = [string](Read-Host -Prompt 'Paste the deployed 3D-P Apps Script /exec URL') }
if (-not $apiUrl.EndsWith('/exec')) { throw 'The Apps Script URL must end with /exec.' }
if ([string]::IsNullOrWhiteSpace($ownerToken)) { $ownerToken = Read-3dpSecret -Prompt 'Paste owner 3D-P token (input is hidden)' }
if (-not $ConfirmLiveWrite) {
    throw 'This updates 3D-P-024 header notes, the stale Аналітика!A1 title, and may fill blank approved formula cells. First create a fresh named Google Sheets version, then re-run with -ConfirmLiveWrite.'
}

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
        return Invoke-RestMethod -Method Post -Uri $apiUrl -ContentType 'text/plain;charset=utf-8' -Body ($request | ConvertTo-Json -Compress -Depth 8) -TimeoutSec $setupPostTimeoutSeconds
    }
    catch {
        $message = [string]($_.Exception.Message)
        if ($message -match '(?i)(timed out|timeout)') {
            throw '3D-P-024 setup POST timed out after 120 seconds. Do not rerun yet: first run the read-only 3D-P-015 preflight and inspect _Аудит_API for SETUP_3DP024.'
        }
        throw
    }
}

function Assert-3dpSuccess {
    param([Parameter(Mandatory = $true)][string]$Name, [Parameter(Mandatory = $true)]$Response)
    if ($Response.ok -ne $true) { throw "$Name failed: $($Response | ConvertTo-Json -Compress -Depth 8)" }
}

function Assert-3dpEndpoint {
    try { $overview = Invoke-3dpGet -Query @{ action = '3dp_overview' } }
    catch {
        throw '3D-P endpoint validation failed before any writes. Use the deployed 3D-P URL/token, not main CRM. No setup request was sent.'
    }
    if ($overview.ok -ne $true -or [string]$overview.action -ne '3dp_overview' -or $null -eq $overview.summary) {
        throw '3D-P endpoint validation failed before any writes. Use the deployed 3D-P URL/token, not main CRM. No setup request was sent.'
    }
    return $overview
}

function Get-3dpRange {
    param([Parameter(Mandatory = $true)][string]$Sheet, [Parameter(Mandatory = $true)][string]$Range)
    $response = Invoke-3dpGet -Query @{ action = '3dp_get_range'; sheet = $Sheet; range = $Range }
    Assert-3dpSuccess -Name "$Sheet!$Range" -Response $response
    return $response
}

$endpoint = Assert-3dpEndpoint
$setup = Invoke-3dpPost -Payload @{ action = '3dp_setup_3dp024' }
Assert-3dpSuccess -Name '3dp_setup_3dp024' -Response $setup

$nomenclatureHeader = Get-3dpRange -Sheet 'Номенклатура' -Range 'G1:G1'
if ([string]$nomenclatureHeader.values[0][0] -ne 'Час друку за од., год') { throw 'Номенклатура!G1 changed unexpectedly.' }
$printLogHeader = Get-3dpRange -Sheet 'Друк-лог' -Range 'D1:D1'
if ([string]$printLogHeader.values[0][0] -ne 'Час друку факт, год') { throw 'Друк-лог!D1 changed unexpectedly.' }
$analyticsTitle = Get-3dpRange -Sheet 'Аналітика' -Range 'A1:A18'
if ([string]$analyticsTitle.values[0][0] -ne 'Маржа-калькулятор по SKU (фактична РРЦ, формула 50/50 після повернення собівартості)') {
    throw 'Аналітика!A1 was not updated to the actual-RRP title.'
}

$report = [ordered]@{
    task = '3D-P-024'
    captured_at_local = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss K')
    mode = 'live_write'
    api_identity_probe = [ordered]@{ action = [string]$endpoint.action; summary_contract = '3dp_overview' }
    action = [ordered]@{ already_applied = [bool]$setup.already_applied; changes = @($setup.changes) }
    nomenclature_g1 = [string]$nomenclatureHeader.values[0][0]
    print_log_d1 = [string]$printLogHeader.values[0][0]
    analytics_a1 = [string]$analyticsTitle.values[0][0]
    analytics_a18 = [string]$analyticsTitle.values[17][0]
}

$json = $report | ConvertTo-Json -Depth 8
if ($SaveEvidence) {
    if ([string]::IsNullOrWhiteSpace($OutputPath)) {
        $OutputPath = Join-Path $PSScriptRoot ('..\..\..\diagnostics\3D-P-024_live-setup_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.json')
    }
    $resolved = [IO.Path]::GetFullPath($OutputPath)
    $diagnosticsRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..\..\diagnostics'))
    if (-not $resolved.StartsWith($diagnosticsRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'OutputPath must be inside diagnostics/.' }
    [IO.File]::WriteAllText($resolved, $json + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
    Write-Host "Evidence saved: $resolved" -ForegroundColor Cyan
}

$json
