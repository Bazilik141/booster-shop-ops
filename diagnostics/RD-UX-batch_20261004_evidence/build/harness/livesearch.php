<?php
// Fixture for extension/ps_live_search/module/ps_live_search.autocomplete — same JSON shape the module JS validates.
$q = (string)($_GET['search'] ?? '');
header('Content-Type: application/json; charset=utf-8');
if (stripos($q, 'err') === 0) { http_response_code(500); echo 'server error'; exit; }
if (stripos($q, 'hang') === 0) { sleep(12); echo '{}'; exit; }
if (stripos($q, 'slow') === 0 || stripos($q, 'pok') === 0 && strlen($q) === 3) usleep(1500000);
$none = stripos($q, 'zzz') === 0;
$p = function ($n, $price, $special, $i) { return ['href' => '#p' . $i, 'name' => $n, 'description' => 'Короткий опис товару з модуля, який у списку більше не показується. Ще трохи тексту для двох рядків.', 'price' => $price, 'special' => $special, 'tax' => '', 'thumb' => 'image/fixture/p' . $i . '.svg', 'thumb_width' => 56, 'thumb_height' => 56]; };
$products = $none ? [] : [
    $p('Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання)', '330.00₴', '', 1),
    $p('Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box (Англійське видання)', '3900.00₴', '3450.00₴', 2),
    $p('Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)', '750.00₴', '', 3),
    $p('Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)', '1100.00₴', '', 4),
    $p('Бустер бокс Pokémon TCG: Surging Sparks Booster Display (36 бустерів)', '6900.00₴', '', 5),
];
$cats = $none ? [] : [['href' => '#c1', 'name' => 'Pokémon', 'thumb' => '', 'thumb_width' => 0, 'thumb_height' => 0], ['href' => '#c2', 'name' => 'Бустери Pokémon', 'thumb' => '', 'thumb_width' => 0, 'thumb_height' => 0], ['href' => '#c3', 'name' => 'Бустер бокси Pokémon', 'thumb' => '', 'thumb_width' => 0, 'thumb_height' => 0]];
echo json_encode([
    'products' => ['status' => true, 'show_price' => true, 'data' => $products],
    'categories' => ['status' => true, 'data' => $cats],
    'manufacturers' => ['status' => true, 'data' => []],
    'informations' => ['status' => false, 'data' => []],
], JSON_UNESCAPED_UNICODE);
