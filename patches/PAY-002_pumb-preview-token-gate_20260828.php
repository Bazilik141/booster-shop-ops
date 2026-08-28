<?php
/**
 * PAY-002 WP1 — PUMB preview token/public visibility settings and server gate.
 * Run from ~/public_html: php PAY-002_pumb-preview-token-gate_20260828.php
 * DB change: adds two setting rows when absent. Rollback: delete the rows named
 * payment_pumb_credit_preview_token and payment_pumb_credit_public, then restore
 * the backed-up files.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_pumb-preview-token-gate_20260828';
function out(string $s): void { echo $s . PHP_EOL; }
function fail(string $s): void { fwrite(STDERR, 'ERROR: ' . $s . PHP_EOL); exit(1); }
function need(bool $ok, string $s): void { if (!$ok) fail($s); }
function replaceOnce(string $s, string $a, string $b, string $label): string { need(substr_count($s, $a) === 1, 'anchor count for ' . $label . ' is not 1'); return str_replace($a, $b, $s); }
function backupFile(string $root, string $backup, string $rel): void { $to = $backup . '/' . $rel; if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0755, true) && !is_dir(dirname($to))) fail('cannot create backup directory'); need(copy($root . '/' . $rel, $to), 'cannot back up ' . $rel); }
function lint(string $path): void { $lines=[]; exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lines, $status); need($status === 0, 'php -l failed for ' . $path . ': ' . implode(' ', $lines)); out('php_l=ok file=' . $path); }
function settingRows(mysqli $db, string $prefix, string $key): array { $q=$db->prepare('SELECT setting_id,value FROM `' . $prefix . 'setting` WHERE store_id=0 AND code=? AND `key`=? ORDER BY setting_id'); need($q !== false, 'settings preflight failed'); $code='payment_pumb_credit'; $q->bind_param('ss',$code,$key); need($q->execute(), 'settings query failed'); $q->bind_result($id,$value); $rows=[]; while($q->fetch()) $rows[]=['id'=>(int)$id,'value'=>(string)$value]; $q->close(); return $rows; }
function execSql(mysqli $db, string $sql): void { need($db->query($sql) !== false, 'SQL failed: ' . $db->error); }

$root = getcwd() ?: '.';
need(is_file($root . '/config.php'), 'Run from OpenCart public_html (config.php missing).');
require_once $root . '/config.php';
need(defined('DB_PREFIX') && defined('DB_HOSTNAME') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_DATABASE') && defined('DB_PORT'), 'DB constants unavailable.');
$rel = [
 'extension/pumb_credit/catalog/controller/payment/pumb_credit.php',
 'extension/pumb_credit/catalog/model/payment/pumb_credit.php',
 'extension/pumb_credit/admin/controller/payment/pumb_credit.php',
 'extension/pumb_credit/admin/view/template/payment/pumb_credit.twig'
];
foreach ($rel as $f) need(is_file($root . '/' . $f), 'missing live file: ' . $f);
$marker = $root . '/extension/pumb_credit/.pay002-preview-token-marker';
if (is_file($marker)) { out('already_applied=yes'); exit(0); }

$src=[]; foreach ($rel as $f) { $v=file_get_contents($root . '/' . $f); need(is_string($v), 'cannot read ' . $f); $src[$f]=$v; }
$catalog = replaceOnce($src[$rel[0]], '    public function index(): string { return \'\'; }', <<<'PHP'
    public function index(): string { return ''; }
    public function preview(): void {
        $configured = trim((string)$this->config->get('payment_pumb_credit_preview_token'));
        $token = (string)($this->request->get['token'] ?? '');
        if (isset($this->request->get['off']) || ($configured !== '' && $token !== '' && hash_equals($configured, $token))) {
            if (isset($this->request->get['off'])) unset($this->session->data['pay002_pumb_preview']);
            elseif ($configured !== '') $this->session->data['pay002_pumb_preview'] = true;
        }
        $language = rawurlencode((string)$this->config->get('config_language'));
        $this->response->redirect($this->url->link('checkout/checkout', 'language=' . $language, true));
    }
    private function pay002Available(): bool {
        if (!$this->config->get('payment_pumb_credit_status')) return false;
        foreach (['payment_pumb_credit_api_base','payment_pumb_credit_oauth_url','payment_pumb_credit_oauth_username','payment_pumb_credit_oauth_password','payment_pumb_credit_point_of_sale_code','payment_pumb_credit_partner_name'] as $key) {
            if (trim((string)$this->config->get($key)) === '') return false;
        }
        return (bool)$this->config->get('payment_pumb_credit_public') || !empty($this->session->data['pay002_pumb_preview']);
    }
PHP, 'catalog gate insertion');
$catalog = replaceOnce($catalog, "if (!$this->config->get('payment_pumb_credit_status') || !$orderId)", "if (!$this->pay002Available() || !$orderId)", 'confirm gate');
$catalog = replaceOnce($catalog, '    public function confirm(): void {', "    public function confirm(): void {\n        // PAY-002: server-side token/public gate is authoritative; UI is not trusted.", 'confirm gate comment');
$model = replaceOnce($src[$rel[1]], "        // PAY-003 owns the shared credit-provider UI and will call the dedicated flow.\n", "        // PAY-002: intentionally [] so legacy SimpleCheckout never gains PUMB.\n", 'legacy model intent');
$admin = replaceOnce($src[$rel[2]], "'max_total','terms','sort_order'", "'max_total','terms','preview_token','public','sort_order'", 'admin setting keys');
$twig = replaceOnce($src[$rel[3]], '<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="payment_pumb_credit_test_mode"', '<div class="mb-3"><label class="form-label">Preview URL token (empty = closed)</label><input type="text" class="form-control" name="payment_pumb_credit_preview_token" value="{{ payment_pumb_credit_preview_token }}" autocomplete="off"></div>\n<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="payment_pumb_credit_public" value="1"{% if payment_pumb_credit_public %} checked{% endif %}><label class="form-check-label">Показувати ПУМБ усім клієнтам</label></div>\n<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="payment_pumb_credit_test_mode"', 'admin fields');
$stamp=date('Ymd-His'); $backup=$root . '/_patch_backups/' . PATCH_ID . '-' . $stamp; foreach($rel as $f) backupFile($root,$backup,$f);
$db=@new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT); need(!$db->connect_errno,'database connection failed'); $db->set_charset('utf8mb4');
$rowsToken=settingRows($db,DB_PREFIX,'payment_pumb_credit_preview_token'); $rowsPublic=settingRows($db,DB_PREFIX,'payment_pumb_credit_public'); need(count($rowsToken)<=1 && count($rowsPublic)<=1,'duplicate PAY-002 setting rows');
try {
    file_put_contents($root.'/'.$rel[0],$catalog); file_put_contents($root.'/'.$rel[1],$model); file_put_contents($root.'/'.$rel[2],$admin); file_put_contents($root.'/'.$rel[3],$twig);
    lint($root.'/'.$rel[0]);
    $stmt=$db->prepare('INSERT INTO `' . DB_PREFIX . 'setting` (store_id,code,`key`,`value`,serialized) VALUES (0,?,?,?,0)'); need($stmt!==false,'cannot prepare setting insert'); $code='payment_pumb_credit';
    if (!$rowsToken) { $key='preview_token'; $value=''; $stmt->bind_param('sss',$code,$key,$value); need($stmt->execute(),'cannot insert preview token setting'); }
    if (!$rowsPublic) { $key='public'; $value='0'; $stmt->bind_param('sss',$code,$key,$value); need($stmt->execute(),'cannot insert public setting'); }
    $stmt->close(); file_put_contents($marker,'PAY-002 WP1 applied ' . date('c') . PHP_EOL); out('cwd='.$root); out('backup='.$backup); out('changed='.implode(',', $rel)); out('payment_pumb_credit_status_preserved=yes'); out('done=ok');
} catch (Throwable $e) {
    foreach($rel as $f) @copy($backup.'/'.$f,$root.'/'.$f);
    $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id=0 AND code='payment_pumb_credit' AND `key` IN ('preview_token','public')");
    $db->close(); fail('restored source files; additive settings rolled back: ' . $e->getMessage());
}
$db->close(); if(!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes'); else out('self_delete=ok');
