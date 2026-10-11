<?php
// Owner-run bounded public-primary lookup; no DB/file writes.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    require 'config.php';
    if (!preg_match('/\A[A-Za-z0-9_]+\z/', DB_PREFIX)) { throw new RuntimeException('Invalid prefix'); }
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) DB_PORT);
    $db->set_charset('utf8mb4'); $prefix = DB_PREFIX; $keyword = 'acsesuary';
    $stmt = $db->prepare("SELECT `key`, `value` FROM `{$prefix}seo_url` WHERE `keyword` = ? AND `key` IN ('category_id','path')");
    $stmt->bind_param('s', $keyword); $stmt->execute(); $stmt->bind_result($key, $value);
    $roots = [];
    while ($stmt->fetch()) {
        if (preg_match('/\A[0-9]+(?:_[0-9]+)*\z/', (string) $value)) {
            $parts = explode('_', (string) $value); $roots[] = (int) end($parts);
        }
    }
    $stmt->close(); $roots = array_values(array_unique($roots));
    if (count($roots) !== 1) {
        echo json_encode(['task'=>'RD-25-WP1-FIX-G1','done'=>'category_root_unresolved','candidate_category_ids'=>$roots,'db_writes'=>0,'file_writes'=>0]) . "\n"; exit;
    }
    $root = $roots[0];
    $stmt = $db->prepare(
        "SELECT p.product_id, p.image, GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id)
         FROM `{$prefix}product` p
         JOIN `{$prefix}product_to_category` pc ON pc.product_id = p.product_id
         JOIN `{$prefix}category_path` cp ON cp.category_id = pc.category_id
         JOIN `{$prefix}product_to_store` ps ON ps.product_id = p.product_id AND ps.store_id = 0
         WHERE cp.path_id = ? AND p.status = 1 AND p.date_available <= NOW()
         GROUP BY p.product_id, p.image ORDER BY p.product_id LIMIT 501"
    );
    $stmt->bind_param('i', $root); $stmt->execute(); $stmt->bind_result($id, $image, $categories);
    $products = [];
    while ($stmt->fetch()) {
        $products[] = ['product_id'=>(int) $id,'image'=>(string) $image,'category_ids'=>array_map('intval', explode(',', (string) $categories))];
    }
    $stmt->close(); $db->close();
    if (count($products) > 500) {
        echo json_encode(['task'=>'RD-25-WP1-FIX-G1','done'=>'limit_exceeded','db_writes'=>0,'file_writes'=>0]) . "\n"; exit;
    }
    echo json_encode(['task'=>'RD-25-WP1-FIX-G1','utc'=>gmdate('c'),'category_keyword'=>$keyword,'root_category_id'=>$root,'scope'=>'enabled store-0 products in acsesuary and descendants; primary images only','product_count'=>count($products),'products'=>$products,'db_writes'=>0,'file_writes'=>0,'done'=>'ok'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $error) {
    echo json_encode(['task'=>'RD-25-WP1-FIX-G1','done'=>'error','error_code'=>(int) $error->getCode(),'db_writes'=>0,'file_writes'=>0]) . "\n";
}
