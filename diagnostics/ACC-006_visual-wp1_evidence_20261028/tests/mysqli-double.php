<?php
// Fixture-only mysqli double (php -n has no mysqli). Behaviour is driven by A6_FIXTURE JSON.
const MYSQLI_REPORT_OFF = 0;
function mysqli_report($f) { return true; }
class A6Result { public $rows; public function __construct($rows) { $this->rows = $rows; } public function fetch_assoc() { return array_shift($this->rows); } public function free() {} }
class A6Stmt { private $fx; private $route; private $out = []; public $errno = 0;
  public function __construct($fx) { $this->fx = $fx; }
  public function bind_param($t, &$route) { $this->route = &$route; return true; }
  public function execute() { return true; }
  public function bind_result(&$count) { $this->out = [&$count]; return true; }
  public function fetch() { $this->out[0] = (int)($this->fx['theme'][$this->route] ?? 0); return true; }
  public function free_result() {} public function close() {} }
class mysqli { public $connect_errno = 0; private $fx;
  public function __construct(...$a) { $this->fx = json_decode(getenv('A6_FIXTURE') ?: '{}', true); $GLOBALS['A6_DB_LOG'][] = 'connect'; }
  public function set_charset($c) { return true; }
  public function query($sql) { $GLOBALS['A6_DB_LOG'][] = $sql;
    if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE)/i', ltrim($sql))) { fwrite(STDERR, "WRITE ATTEMPT\n"); exit(9); }
    if (strpos($sql, 'SHOW COLUMNS FROM `oc_theme`') === 0) return new A6Result([['Field' => 'route'], ['Field' => 'status']]);
    if (strpos($sql, 'SHOW COLUMNS FROM `oc_event`') === 0) return new A6Result([['Field' => 'trigger'], ['Field' => 'action'], ['Field' => 'status']]);
    if (strpos($sql, 'SHOW COLUMNS FROM `oc_setting`') === 0) return new A6Result([['Field' => 'store_id'], ['Field' => 'key'], ['Field' => 'value'], ['Field' => 'serialized']]);
    if (strpos($sql, 'SHOW COLUMNS FROM `oc_customer_wishlist`') === 0) return new A6Result([['Field' => 'customer_id'], ['Field' => 'product_id'], ['Field' => 'store_id'], ['Field' => 'date_added']]);
    if ($sql === 'START TRANSACTION READ ONLY') return true;
    if (strpos($sql, 'SELECT `store_id`,`key`') === 0) return new A6Result($this->fx['settings'] ?? [['store_id' => '0', 'key' => 'config_theme', 'value' => 'basic', 'serialized' => '0']]);
    if (strpos($sql, 'SELECT `trigger`,`action`') === 0) return new A6Result($this->fx['events'] ?? []);
    fwrite(STDERR, "UNEXPECTED SQL $sql\n"); exit(8); }
  public function prepare($sql) { $GLOBALS['A6_DB_LOG'][] = $sql; return new A6Stmt($this->fx); }
  public function rollback() { return true; } public function close() {} }
