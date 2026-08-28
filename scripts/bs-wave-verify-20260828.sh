cd ~/public_html && php <<'PHP'
<?php
require 'config.php';
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
$db->set_charset('utf8mb4'); $p = DB_PREFIX; $L = 4; $bad = 0;
function chk($ok, $label, $detail = '') { global $bad; if (!$ok) { $bad++; echo "FAIL  $label  $detail\n"; } else { echo "ok    $label\n"; } }

/* 1. WP1 — 28 descriptions */
$faq = [125=>1,126=>2,127=>1,128=>1,129=>1,130=>1,131=>1,132=>1,133=>1,134=>2,135=>1,136=>2,137=>3,138=>2,139=>2,140=>2,141=>1,142=>2,143=>1,144=>4,145=>3,146=>2,147=>2,148=>2,149=>3,150=>2,151=>3,152=>3];
$total = 0; $rawTags = 0; $mismatch = [];
$r = $db->query("SELECT product_id, description FROM {$p}product_description WHERE language_id = $L AND product_id BETWEEN 125 AND 152");
while ($x = $r->fetch_assoc()) {
    $id = (int) $x['product_id']; $d = $x['description'];
    $n = substr_count($d, 'class=&quot;bs-faq-item&quot;'); $total += $n;
    if (!isset($faq[$id]) || $n !== $faq[$id]) $mismatch[] = $id . ':' . $n . '/' . ($faq[$id] ?? '-');
    if (strpos($d, '<h2') !== false || strpos($d, '<p>') !== false || strpos($d, '<section') !== false) $rawTags++;
}
chk($total === 52, 'WP1 FAQ items = 52', "got $total");
chk($mismatch === [], 'WP1 per-card FAQ counts', implode(' ', $mismatch));
chk($rawTags === 0, 'WP1 no raw HTML stored', "$rawTags rows with raw tags");
$x = $db->query("SELECT text FROM {$p}product_attribute WHERE product_id=143 AND attribute_id=55 AND language_id=$L")->fetch_assoc();
chk($x && $x['text'] === 'PSA, BGS, SGC, слаби на магніті', 'WP1 attr 55 on 143', $x['text'] ?? 'missing');
$n = (int) $db->query("SELECT COUNT(*) c FROM {$p}product_attribute WHERE attribute_id=43 AND language_id=$L AND product_id BETWEEN 125 AND 143 AND text='1–2 робочих дні'")->fetch_assoc()['c'];
chk($n === 19, 'WP1 attr 43 on 19 products', "got $n");
$n = (int) $db->query("SELECT COUNT(*) c FROM {$p}product_attribute WHERE attribute_id=44 AND language_id=$L AND product_id BETWEEN 125 AND 129 AND text='Ні'")->fetch_assoc()['c'];
chk($n === 5, 'WP1 attr 44 = Ні on 125-129', "got $n");
chk($db->query("SELECT 1 FROM {$p}product_attribute WHERE product_id IN (142,143) AND attribute_id NOT IN (36,37,38,39,40,41,42,43,44,50,51,52,55)")->num_rows === 0, 'WP1 no capacity attributes created');

/* 2. WP2 — category 73 */
$x = $db->query("SELECT name, meta_keyword FROM {$p}category_description WHERE category_id=73 AND language_id=$L")->fetch_assoc();
chk($x['meta_keyword'] === 'брелоки Pokémon, фігурки Pokémon, 3D-друк Pokémon, декор Pokémon, Pokémon 3D-друк Україна', 'WP2 category 73 keywords');
chk($x['name'] === 'Фігурки та декор Pokémon', 'WP2 category 73 name untouched');
$n = (int) $db->query("SELECT COUNT(*) c FROM {$p}category WHERE category_id IN (73,74) AND status=0")->fetch_assoc()['c'];
chk($n === 2, 'WP2 categories 73/74 still disabled', "got $n");

/* 3. WP3/4/5 — nine new products */
$new = ['BR-CHARM-200','PKM-JP-SVEL-SET','FIG-JIGGL-300','FIG-MEW-300','FIG-UMBRE-300','FIG-GENG-300','FIG-MAGIK-300','FIG-PIKA-300','FIG-SQUIR-300'];
foreach ($new as $sku) {
    $x = $db->query("SELECT p.product_id, p.status, p.quantity, p.price, p.image, p.weight, p.length, p.width, p.height,
        (SELECT COUNT(*) FROM {$p}product_attribute a WHERE a.product_id=p.product_id AND a.language_id=$L) attrs,
        (SELECT GROUP_CONCAT(category_id ORDER BY category_id) FROM {$p}product_to_category c WHERE c.product_id=p.product_id) cats,
        (SELECT keyword FROM {$p}seo_url s WHERE s.`key`='product_id' AND s.value=p.product_id LIMIT 1) slug
        FROM {$p}product p WHERE p.model='$sku'")->fetch_assoc();
    if (!$x) { chk(false, "created $sku", 'NOT FOUND'); continue; }
    $want = $sku === 'PKM-JP-SVEL-SET' ? ['650.0000','59,64',8] : ($sku === 'BR-CHARM-200' ? ['1.0000','59,73',13] : ['1.0000','59,73',13]);
    chk((int)$x['status'] === 0 && (int)$x['quantity'] === 0 && $x['price'] === $want[0] && $x['cats'] === $want[1] && (int)$x['attrs'] === $want[2] && $x['slug'] !== null,
        "created $sku (id {$x['product_id']})", "status={$x['status']} qty={$x['quantity']} price={$x['price']} cats={$x['cats']} attrs={$x['attrs']} slug={$x['slug']}");
    if ($sku === 'PKM-JP-SVEL-SET')
        chk($x['weight'] === '400.00000000' && $x['length'] === '220.00000000' && $x['width'] === '160.00000000' && $x['height'] === '60.00000000',
            'WP4 SVEL weight/dimensions', "{$x['weight']} {$x['length']}x{$x['width']}x{$x['height']}");
}
chk((int) $db->query("SELECT COUNT(*) c FROM {$p}attribute")->fetch_assoc()['c'] === 40, 'no attribute definition created', 'count=' . $db->query("SELECT COUNT(*) c FROM {$p}attribute")->fetch_assoc()['c']);

/* 4. WP6 — prices and disabled promotions */
$price = ['OP-JP-OP15-BBX'=>'5400.0000','PKM-MEGA-BOX'=>'4700.0000','PKM-JP-ABYE-BBX'=>'4900.0000','OP-JP-OP16-BBX'=>'4700.0000','PKM-JP-SPIN-BBX'=>'4700.0000','YGO-JP-WPP5-BBX'=>'1100.0000','PKM-JP-MBRV-BBX'=>'4900.0000','PKM-JP-MDEX-BBX'=>'4900.0000','PKM-EN-PORD-BST'=>'300.0000','OP-JP-OP16-BST'=>'220.0000','PKM-JP-MSYM-BST'=>'170.0000','PKM-JP-MBRV-BST'=>'180.0000','PKM-JP-MZERO-BST'=>'160.0000','OP-JP-OP10-BST'=>'190.0000','OP-JP-OP14-BST'=>'180.0000','PKM-JP-SPIN-BST'=>'190.0000','PKM-JP-MDEX-BST'=>'460.0000','PKM-JP-WFLR-BST'=>'320.0000','PKM-JP-INFX-BST'=>'220.0000','PKM-JP-ABYE-BST'=>'210.0000','MTG-JP-AFRS-BST'=>'250.0000','PKM-EN-PORD-BBN'=>'1600.0000','PKM-EN-CHRS-BBN'=>'1600.0000','PKM-EN-CHRS-BST'=>'300.0000','PKM-JP-OUTL-BST'=>'90.0000'];
$wrong = [];
foreach ($price as $sku => $want) {
    $x = $db->query("SELECT price FROM {$p}product WHERE model='$sku'")->fetch_assoc();
    if (!$x || $x['price'] !== $want) $wrong[] = $sku . ':' . ($x['price'] ?? 'missing') . '/' . $want;
}
chk($wrong === [], 'WP6 25 prices match plan', implode(' ', $wrong));
$n = (int) $db->query("SELECT COUNT(*) c FROM {$p}product_discount WHERE product_discount_id IN (1116,1153,1152,1123) AND date_end < CURDATE() AND date_end <> '0000-00-00'")->fetch_assoc()['c'];
chk($n === 4, 'WP6 four promotions ended', "got $n");
$n = (int) $db->query("SELECT COUNT(*) c FROM {$p}product_discount WHERE product_discount_id=1140 AND price=80.0000 AND (date_end='0000-00-00' OR date_end>NOW())")->fetch_assoc()['c'];
chk($n === 1, 'WP6 Outlet promotion untouched', "got $n");

echo $bad === 0 ? "\nALL CHECKS PASSED\n" : "\n$bad CHECK(S) FAILED\n";
PHP
