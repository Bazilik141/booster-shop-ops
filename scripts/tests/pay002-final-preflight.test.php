<?php
// Run with php -n: the fake driver guarantees zero real database/network access.
declare(strict_types=1);
if (extension_loaded('mysqli')) { fwrite(STDERR, "Run with php -n\n"); exit(1); }
require dirname(__DIR__) . '/PAY-002_final-preflight_20260831.php';
define('MYSQLI_REPORT_ERROR', 1); define('MYSQLI_REPORT_STRICT', 2);
define('MYSQLI_OPT_CONNECT_TIMEOUT', 0); define('MYSQLI_ASSOC', 1);
function mysqli_report(int $mode): void {}
function mysqli_init(): FakePreflightDb { return new FakePreflightDb(); }
class mysqli_sql_exception extends RuntimeException {}
class FakePreflightResult {
    private array $rows;
    private int $offset = 0;
    public function __construct(array $rows) { $this->rows = $rows; }
    // Deliberately no fetch_all(): emulate PHP 8.0 linked without mysqlnd.
    public function fetch_assoc(): ?array {
        if (!empty($GLOBALS['simulate_fetch_error'])) throw new Error('SYNTHETIC_SECRET_MUST_NOT_PRINT');
        return $this->rows[$this->offset++] ?? null;
    }
    public function free(): void {}
}
class FakePreflightDb {
    public function options(int $option, int $value): void {}
    public function real_connect(...$args): void {
        if ($args[0] !== 'fixture.invalid') throw new RuntimeException('wrong_fixture');
        if (!empty($GLOBALS['simulate_connect_error'])) throw new RuntimeException('SYNTHETIC_SECRET_MUST_NOT_PRINT');
    }
    public function set_charset(string $value): void {}
    public function close(): void {}
    public function query(string $sql): FakePreflightResult {
        if (!preg_match('/^(SELECT|SHOW)\s/', $sql)) throw new RuntimeException('write_attempt');
        $GLOBALS['queries'][] = $sql;
        if (strpos($sql, 'FROM `fixture_setting`') !== false) {
            if (!empty($GLOBALS['simulate_sql_error'])) throw new mysqli_sql_exception('SYNTHETIC_SECRET_MUST_NOT_PRINT', 1054);
            if (strpos($sql, 'CASE WHEN') === false) throw new RuntimeException('secret_projection_missing');
            $values = ['status'=>'1','test_mode'=>'1','public'=>'0','oauth_password'=>'present','preview_token'=>'present','oauth_url'=>'https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token','api_base'=>'https://api.dts.fuib.com/ext-oic/galadriel/v1','terms'=>'[3,4,5]','status_funded'=>'20'];
            $out = [];
            foreach ($values as $k=>$v) $out[] = ['store_id'=>'0','key'=>'payment_pumb_credit_'.$k,'safe_value'=>$v];
            $out[] = ['store_id'=>'0','key'=>'payment_pumb_credit_public','safe_value'=>'0'];
            return new FakePreflightResult($out);
        }
        if (strpos($sql, 'SHOW COLUMNS') === 0) return new FakePreflightResult([['Field'=>'order_id'],['Field'=>'requested_term'],['Field'=>'is_test']]);
        if (strpos($sql, 'SHOW INDEX') === 0) return new FakePreflightResult([
            ['Non_unique'=>0,'Key_name'=>'by_order','Seq_in_index'=>2,'Column_name'=>'is_test'],
            ['Non_unique'=>0,'Key_name'=>'by_order','Seq_in_index'=>1,'Column_name'=>'order_id'],
        ]);
        if (strpos($sql, 'FROM `fixture_order_status`') !== false) return new FakePreflightResult([['order_status_id'=>'20','language_id'=>'1']]);
        throw new RuntimeException('unexpected_query');
    }
}
function check(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); }
$fixture = __DIR__ . '/fixtures/pay002-preflight';
$config = file_get_contents($fixture . '/config.php');
check(pay002Config($config)['DB_DATABASE'] === 'synthetic-db', 'literal_config');
foreach ([str_replace("'fixture_'", "getenv('PRIVATE_PREFIX')", $config), $config . "\ndefine('DB_PREFIX','other_');", str_replace("'fixture_'", "'x`; DROP TABLE t; --'", $config)] as $bad) {
    $rejected = false; try { pay002Config($bad); } catch (Throwable $e) { $rejected = true; }
    check($rejected, 'unsafe_config_rejected');
}
check(pay002Endpoint('api_base','https://apiext.pumb.ua/ext-oic/galadriel/v1') === 'production_exact', 'prod_endpoint');
check(pay002Endpoint('api_base','https://example.invalid/?token=SECRET') === 'unrecognized_redacted', 'unknown_url_redaction');
check(pay002SafeSetting('public','SECRET') === 'invalid_redacted', 'bad_boolean');
check(pay002SafeSetting('terms','["SECRET"]') === 'invalid_redacted', 'bad_terms');
check(pay002SafeSetting('oauth_password','SECRET') === 'redacted', 'secret_defense');
check(pay002SafeSetting('test_callback_ips','194.44.66.16/28')['cidr_unsupported_by_live_code'] === 1, 'cidr_trap');
chdir($fixture);
ob_start(); $code = pay002Main(['preflight']); $out = ob_get_clean();
$json = json_decode($out,true);
check($code === 0 && $json['done'] === 'ok', 'fixture_success');
check($json['diagnostic'] === 'PAY-002-final-preflight-v2', 'revision');
check($json['unique_indexes_columns'] === [['order_id','is_test']], 'index_order');
check(count($json['duplicate_setting_rows']) === 1, 'duplicate_settings');
check(strpos($out,'SYNTHETIC_SECRET') === false && strpos($out,'synthetic-user') === false, 'secret_not_printed');
check(count($GLOBALS['queries']) === 4, 'bounded_queries');
$GLOBALS['simulate_connect_error'] = true;
ob_start(); $code = pay002Main(['preflight']); $out = ob_get_clean();
check($code === 1 && strpos($out,'SYNTHETIC_SECRET') === false && strpos($out,'db_connect') !== false, 'redacted_exception');
$GLOBALS['simulate_connect_error'] = false;
$GLOBALS['simulate_sql_error'] = true;
ob_start(); $code = pay002Main(['preflight']); $out = ob_get_clean(); $json = json_decode($out, true);
check($code === 1 && $json['phase'] === 'settings' && $json['db_operation'] === 'query', 'sql_phase');
check($json['error_kind'] === 'unknown_column' && $json['error_code'] === 1054 && strpos($out,'SYNTHETIC_SECRET') === false, 'sql_classification_redacted');
$GLOBALS['simulate_sql_error'] = false;
$GLOBALS['simulate_fetch_error'] = true;
ob_start(); $code = pay002Main(['preflight']); $out = ob_get_clean(); $json = json_decode($out, true);
check($code === 1 && $json['phase'] === 'settings' && $json['db_operation'] === 'fetch', 'fetch_phase');
check($json['error_kind'] === 'php_error' && strpos($out,'SYNTHETIC_SECRET') === false, 'fetch_error_redacted');
echo "preflight_tests=ok; real_db_calls=0; bank_calls=0\n";
