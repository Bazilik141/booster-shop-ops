<?php
/**
 * PAY-002 — prove and, only if needed, restore Basic Authorization passthrough
 * for the PUMB test callback.
 *
 * Run only from ~/public_html:
 *   php PAY-002_pumb-callback-basic-auth-passthrough_20260825.php
 *
 * Phase 1 creates one random temporary PHP probe, requests it with a random
 * Basic header, removes the probe, and reports whether PHP received the header.
 * Phase 2 runs only after Phase 1 conclusively proves the header is absent.
 * It adds the marked mod_rewrite passthrough, requires two public 200 smokes,
 * repeats the probe, and restores .htaccess on any failed Phase 2 check.
 */
declare(strict_types=1);

const PAY002_AUTH_PATCH_ID = 'PAY-002_pumb-callback-basic-auth-passthrough_20260825';
const PAY002_AUTH_MARKER_BEGIN = '# BEGIN PAY-002 callback Authorization passthrough';
const PAY002_AUTH_MARKER_END = '# END PAY-002 callback Authorization passthrough';

function out(string $key, string|int|bool $value): void {
    if (is_bool($value)) $value = $value ? 'yes' : 'no';
    echo $key . '=' . $value . PHP_EOL;
}
function fail(string $message, int $code = 1): void {
    out('error', $message);
    exit($code);
}
/** @return array{http:int,body:string,error:string} */
function curlGet(string $url, array $headers = []): array {
    $curl = curl_init($url);
    if ($curl === false) return ['http' => 0, 'body' => '', 'error' => 'curl_init_failed'];
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return ['http' => $http, 'body' => is_string($body) ? $body : '', 'error' => $error];
}
/** @return array{conclusive:bool,arrived:bool,http:int,error:string,cleanup:bool} */
function authorizationProbe(string $root, string $baseUrl): array {
    try {
        $nonce = bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        return ['conclusive' => false, 'arrived' => false, 'http' => 0, 'error' => 'random_bytes_failed', 'cleanup' => true];
    }
    $probeName = 'pay002_auth_probe_' . $nonce . '.php';
    $probePath = $root . '/' . $probeName;
    $expectedHeader = 'Basic ' . base64_encode('pay002-probe-' . $nonce);
    $probeSource = "<?php\n"
        . "header('Content-Type: application/json');\n"
        . '$header = (string)($_SERVER[\'HTTP_AUTHORIZATION\'] ?? $_SERVER[\'REDIRECT_HTTP_AUTHORIZATION\'] ?? \'\');' . "\n"
        . '$expected = ' . var_export($expectedHeader, true) . ";\n"
        . 'echo json_encode([\'authorization_present\' => $header !== \'\', \'authorization_matches_probe\' => hash_equals($expected, $header)]);' . "\n";
    if (file_exists($probePath)) return ['conclusive' => false, 'arrived' => false, 'http' => 0, 'error' => 'random_probe_name_collision', 'cleanup' => true];
    if (file_put_contents($probePath, $probeSource, LOCK_EX) !== strlen($probeSource)) return ['conclusive' => false, 'arrived' => false, 'http' => 0, 'error' => 'probe_write_failed', 'cleanup' => true];
    $response = curlGet($baseUrl . '/' . rawurlencode($probeName), ['Authorization: ' . $expectedHeader, 'Accept: application/json']);
    $cleanup = @unlink($probePath);
    if (!$cleanup) return ['conclusive' => false, 'arrived' => false, 'http' => $response['http'], 'error' => 'probe_cleanup_failed', 'cleanup' => false];
    $decoded = json_decode($response['body'], true);
    if ($response['error'] !== '' || $response['http'] !== 200 || !is_array($decoded) || !is_bool($decoded['authorization_present'] ?? null) || !is_bool($decoded['authorization_matches_probe'] ?? null)) {
        return ['conclusive' => false, 'arrived' => false, 'http' => $response['http'], 'error' => $response['error'] !== '' ? 'probe_transport_failed' : 'probe_response_inconclusive', 'cleanup' => true];
    }
    if ($decoded['authorization_matches_probe'] === true) return ['conclusive' => true, 'arrived' => true, 'http' => $response['http'], 'error' => '', 'cleanup' => true];
    if ($decoded['authorization_present'] === false) return ['conclusive' => true, 'arrived' => false, 'http' => $response['http'], 'error' => '', 'cleanup' => true];
    return ['conclusive' => false, 'arrived' => false, 'http' => $response['http'], 'error' => 'probe_header_mismatch', 'cleanup' => true];
}
/** @param array{conclusive:bool,arrived:bool,http:int,error:string,cleanup:bool} $probe */
function reportProbe(string $phase, array $probe): void {
    out($phase . '_probe_http', $probe['http']);
    out($phase . '_probe_cleanup', $probe['cleanup']);
    out($phase . '_authorization_header_reached_php', $probe['arrived']);
    if ($probe['error'] !== '') out($phase . '_probe_error', $probe['error']);
}
function restoreHtaccess(string $backupFile, string $htaccessFile): bool {
    return is_file($backupFile) && @copy($backupFile, $htaccessFile);
}
function finishSuccess(): void {
    if (!@unlink(__FILE__)) fail('self_delete_failed');
    out('self_deleted', 'yes');
    out('done', 'ok');
}

if (PHP_SAPI !== 'cli') fail('cli_only_refused');
if (!function_exists('curl_init')) fail('curl_extension_required');
$root = getcwd() ?: '.';
$config = $root . '/config.php';
$htaccessFile = $root . '/.htaccess';
if (!is_file($config)) fail('run_from_public_html_config_php_missing');
if (!is_file($htaccessFile) || !is_readable($htaccessFile) || !is_writable($htaccessFile)) fail('htaccess_missing_or_not_readable_writable');
require_once $config;
if (!defined('HTTP_SERVER')) fail('missing_http_server_constant');
$baseUrl = rtrim((string)HTTP_SERVER, '/');
$baseParts = parse_url($baseUrl);
if (!is_array($baseParts) || strtolower((string)($baseParts['scheme'] ?? '')) !== 'https' || (string)($baseParts['host'] ?? '') === '') fail('https_server_must_be_absolute_https_url');
$htaccess = file_get_contents($htaccessFile);
if (!is_string($htaccess)) fail('htaccess_read_failed');
$markerBeginCount = substr_count($htaccess, PAY002_AUTH_MARKER_BEGIN);
$markerEndCount = substr_count($htaccess, PAY002_AUTH_MARKER_END);
if ($markerBeginCount !== $markerEndCount || $markerBeginCount > 1) fail('htaccess_marker_integrity_failed');
$markerPresent = $markerBeginCount === 1;

out('scope', PAY002_AUTH_PATCH_ID);
out('cwd', $root);
out('started_at_utc', gmdate('c'));
out('php_version', PHP_VERSION);
out('htaccess_marker_present', $markerPresent);

$phase1 = authorizationProbe($root, $baseUrl);
reportProbe('phase1', $phase1);
if (!$phase1['conclusive']) fail('phase1_authorization_probe_inconclusive');
if ($phase1['arrived']) {
    out('htaccess_change', 'not_needed');
    out('conclusion', 'authorization_reaches_php; callback_401_requires_bank_credential_comparison');
    if ($markerPresent) out('already_applied', 'yes');
    finishSuccess();
}

out('phase2_required', 'yes');
if ($markerPresent) {
    out('already_applied', 'yes');
    fail('authorization_missing_with_existing_passthrough_marker');
}
$eol = str_contains($htaccess, "\r\n") ? "\r\n" : "\n";
$anchorPattern = '/^Options -Indexes\RRewriteEngine On\RRewriteBase \/\R/m';
$anchorMatches = preg_match_all($anchorPattern, $htaccess);
if ($anchorMatches !== 1) fail('htaccess_anchor_count_must_be_1');
$insertion = PAY002_AUTH_MARKER_BEGIN . $eol
    . '# Apache 2.4/cPanel EA4-safe: mod_rewrite already drives this file; this forwards the incoming header to PHP/FastCGI.' . $eol
    . '<IfModule mod_rewrite.c>' . $eol
    . 'RewriteCond %{HTTP:Authorization} .' . $eol
    . 'RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]' . $eol
    . '</IfModule>' . $eol
    . PAY002_AUTH_MARKER_END . $eol;
$updatedHtaccess = preg_replace_callback($anchorPattern, static function (array $matches) use ($eol, $insertion): string { return $matches[0] . $eol . $insertion; }, $htaccess, 1, $anchorCount);
if (!is_string($updatedHtaccess) || $anchorCount !== 1 || $updatedHtaccess === $htaccess) fail('htaccess_anchor_replacement_failed');
try {
    $backupDir = $root . '/_patch_backups/' . PAY002_AUTH_PATCH_ID . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
} catch (Throwable $exception) {
    fail('backup_random_suffix_failed');
}
if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) fail('backup_directory_create_failed');
$backupFile = $backupDir . '/.htaccess.before';
if (!copy($htaccessFile, $backupFile)) fail('htaccess_backup_failed');
out('backup_dir', $backupDir);
out('htaccess_change', 'apply_rewrite_authorization_passthrough');
if (file_put_contents($htaccessFile, $updatedHtaccess, LOCK_EX) !== strlen($updatedHtaccess)) {
    $restored = restoreHtaccess($backupFile, $htaccessFile);
    out('htaccess_restored_after_write_failure', $restored);
    fail('htaccess_write_failed');
}

$homeSmoke = curlGet($baseUrl . '/', ['Accept: text/html']);
$categorySmoke = curlGet($baseUrl . '/Pokemon', ['Accept: text/html']);
out('smoke_home_http', $homeSmoke['http']);
out('smoke_category_http', $categorySmoke['http']);
if ($homeSmoke['http'] !== 200 || $categorySmoke['http'] !== 200) {
    $restored = restoreHtaccess($backupFile, $htaccessFile);
    out('htaccess_restored_after_smoke_failure', $restored);
    fail('htaccess_smoke_check_failed');
}

$phase2 = authorizationProbe($root, $baseUrl);
reportProbe('phase2', $phase2);
if (!$phase2['conclusive'] || !$phase2['arrived']) {
    $restored = restoreHtaccess($backupFile, $htaccessFile);
    out('htaccess_restored_after_phase2_failure', $restored);
    fail('authorization_still_missing_after_htaccess_change');
}
out('conclusion', 'authorization_passthrough_applied_and_verified');
finishSuccess();
