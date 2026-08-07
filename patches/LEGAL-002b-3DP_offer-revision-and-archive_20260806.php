<?php
declare(strict_types=1);

/*
 * LEGAL-002b-3DP — work package 1 of 4.
 *
 * WHAT THIS DOES
 *   1. Replaces the live Публічна оферта body (ocp5_information_description
 *      id=3, language_id=4) with the owner-approved 07.08.2026 edition, and
 *      replaces that row's meta_description. title / meta_title / meta_keyword
 *      are NOT touched.
 *   2. Creates a new information page holding the previous 24.07.2026 edition
 *      as an archive, mirroring the existing 26.05.2026 archive (id=6):
 *        ocp5_information            (sort_order=0, status=1)
 *        ocp5_information_description(title, description, meta_title=title,
 *                                     meta_description='', meta_keyword='')
 *        ocp5_information_to_store   (new_id, 0)
 *        ocp5_seo_url                keyword=publichna-oferta-arhiv-2026-07-24
 *      No ocp5_information_to_layout row is created — id=6 has none (verified
 *      against backup-8.5.2026_10-49-27_boosters), so the default layout is used.
 *
 * SAFETY PRECONDITION
 *   The archive body embedded here is "banner + the 24.07.2026 offer body".
 *   Before archiving, the patch verifies that the LIVE id=3 description still
 *   hashes to OFFER_PREV_SHA256. If it does not, the patch refuses to run
 *   rather than archiving text that is not what is actually live.
 *
 * NOT TOUCHED
 *   sitemap.xml, robots.txt, .htaccess, canonical/redirects, checkout, payment,
 *   fiscalization, Merchant feed, ocp5_information ids 1/4/5/6, nav/footer menus,
 *   ocp5_information_to_layout.
 *
 * OPEN ITEM — archive noindex (reported, NOT implemented)
 *   ocp5_information_description has no meta_robots column, and the information
 *   controller only sends X-Robots-Tag: noindex from its info() popup route, not
 *   from the SEO-slug route that serves this page. There is therefore no
 *   per-page noindex mechanism to use without a controller/template change,
 *   which is a High-risk SEO change and out of this patch's scope. The
 *   26.05.2026 archive (id=6) is likewise indexable today; this patch keeps the
 *   new archive consistent with it.
 *
 * ROLLBACK
 *   - id=3: restore description + meta_description from
 *     _patch_backups/<patch>-<ts>/db/live_offer_before.json
 *   - archive: the IDs actually assigned at insert time are written to
 *     _patch_backups/<patch>-<ts>/db/created_ids.json. Delete exactly those:
 *       DELETE FROM ocp5_seo_url                 WHERE seo_url_id = <created_seo_url_id>;
 *       DELETE FROM ocp5_information_to_store    WHERE information_id = <created_information_id>;
 *       DELETE FROM ocp5_information_description WHERE information_id = <created_information_id>;
 *       DELETE FROM ocp5_information             WHERE information_id = <created_information_id>;
 *     No ID is hardcoded here on purpose.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME        = 'LEGAL-002b-3DP_offer-revision-and-archive_20260806';
const LANGUAGE_ID       = 4;
const OFFER_ID          = 3;
const OFFER_TITLE       = 'Публічна оферта';
const OFFER_PREV_SHA256 = '4324d3f4854da660ba2ec31f4ba447944fc202d3b88f6b31d4b4a4f2e97e044e';
const OFFER_NEW_SHA256  = '08695acfe3c7e1a1b6f5d09360879d2af3e004384494c41742e1197e420e2cae';
const OFFER_META_DESC   = 'Публічна оферта Booster Shop: умови оформлення замовлення, оплати, доставки й повернення TCG-продукції, Mystery Box, аксесуарів і товарів 3D-друку.';
const ARCHIVE_TITLE     = 'Публічна оферта — архів 24.07.2026';
const ARCHIVE_SLUG      = 'publichna-oferta-arhiv-2026-07-24';
const ARCHIVE_SHA256    = 'e31791015cf5c8fe7ed79817bd729f516b414092622436f460036ed69c1bebb0';
const MIRROR_ID         = 6;

function bs_log(string $key, string $value = ''): void {
    echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL;
}
function bs_fail(string $message): void { throw new RuntimeException($message); }
function bs_path(string $base, string $part): string {
    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
}
function bs_table(string $prefix, string $suffix): string {
    $table = $prefix . $suffix;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) bs_fail('Unsafe DB table name from DB_PREFIX');
    return $table;
}
function bs_quote(mysqli $db, string $value): string { return "'" . $db->real_escape_string($value) . "'"; }
function bs_table_exists(mysqli $db, string $table): bool {
    $r = $db->query('SHOW TABLES LIKE ' . bs_quote($db, $table));
    $ok = $r->num_rows === 1; $r->free(); return $ok;
}
function bs_columns(mysqli $db, string $table): array {
    $r = $db->query('SHOW COLUMNS FROM `' . $table . '`'); $columns = [];
    while ($row = $r->fetch_assoc()) $columns[(string) $row['Field']] = true;
    $r->free(); return $columns;
}
function bs_require_columns(array $columns, array $needed, string $table): void {
    foreach ($needed as $column) if (!isset($columns[$column])) bs_fail('Unexpected schema: ' . $table . '.' . $column . ' is missing');
}
function bs_lint_self(): void {
    if (!function_exists('exec')) bs_fail('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php'; $output = []; $code = 1;
    @exec(escapeshellarg($php) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) bs_fail('php -l gate failed: ' . implode(' ', $output));
    bs_log('php_l', 'ok');
}
function bs_blob(string $b64, string $sha, string $label): string {
    if (!function_exists('gzdecode')) bs_fail('zlib/gzdecode is unavailable; cannot decode embedded ' . $label);
    $compressed = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
    $html = is_string($compressed) ? @gzdecode($compressed) : false;
    if (!is_string($html) || $html === '') bs_fail('Cannot decode embedded ' . $label);
    if (hash('sha256', $html) !== $sha) bs_fail($label . ' SHA-256 mismatch inside this patch file');
    return $html;
}
function bs_expect(string $html, string $needle, int $count, string $label): void {
    $found = substr_count($html, $needle);
    if ($found !== $count) bs_fail($label . ': expected ' . $count . ' x "' . $needle . '", found ' . $found);
}
function bs_connect(): mysqli {
    if (!extension_loaded('mysqli')) bs_fail('mysqli extension is not loaded');
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $constant) {
        if (!defined($constant)) bs_fail('Missing config constant: ' . $constant);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
    $db->set_charset('utf8mb4'); bs_log('db_connect', 'ok'); return $db;
}
function bs_stmt_rows(mysqli_stmt $stmt): array {
    $metadata = $stmt->result_metadata();
    if ($metadata === false) bs_fail('Cannot read SQL result metadata');
    $row = []; $refs = [];
    foreach ($metadata->fetch_fields() as $field) { $row[$field->name] = null; $refs[] = &$row[$field->name]; }
    if (!call_user_func_array([$stmt, 'bind_result'], $refs)) bs_fail('Cannot bind SQL result columns');
    $rows = [];
    while ($stmt->fetch()) { $copy = []; foreach ($row as $k => $v) $copy[$k] = $v; $rows[] = $copy; }
    $metadata->free();
    return $rows;
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_json_backup(string $dir, string $name, array $payload): void {
    $path = bs_path($dir, 'db/' . $name . '.json'); $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0755, true)) bs_fail('Cannot create DB backup directory');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_self_delete(): void {
    @unlink(__FILE__);
    bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}

function bs_offer_html(): string { return bs_blob(<<<'B64'
H4sIAAAAAAACCu19625c2ZXe/3mKkwYCz8AlUhIvulgWfBs4g8yMDbQNTH4ZVJtuCS01NRJ7bP8j
Wa1LAwKl7uGQSdt9UztOgg7gwyJLVWSxioDyAsU8wjxJ9l63vda+nKqS5AkCZABPi3U5dc7ea6/r
t7517e71a6t3ro8/P/vwbPfs4dnu+Gg8PNutztrjk/Fo3Bn3q/Gx++PUvdMZD/w7W/B6fbbhX3If
rH6wtnZ/ffVe9fbNtbutanw6Hrn3no4P3eeOK/fHh/4Fd7nBuDseuqs/rca9cU3XlxfdN0fuuwN3
5a1x3/3lfnt84L66lXzOXfls071eu9eP4bOn8Jmu+5mh/Ij/gLuKu8tDfB/+VbuHeOI+AVfwj+Mv
NIKHGc5dm3drcW3+7vW/uHbz4vULc9V4z33hgL/kPg5XGrj/veDfuTbvPvoX1+66j/sv/NG9flTh
w8Miuo+dbVVnO+6r7u99v4Znj+AGtnFx3F3DLWxX/h7cP+kxzrbOua/jz/fcWgyjpeYHdG/13Odr
d9Xa/YzeuVN3pb67l313CVkUvXv+e9X4K/f2kV+NOXx2/ywX3bN84b4+gjXza/vw7MnVavyH8efj
L6rx79wL7bNNWITj8agaf+g+cwAPu+uf/5l7/RB+5Ahv5uyR26Yvx5+5r386/sJdYmHx8tKV8wuL
C0vqVxfcr37qvjCE3T32kmBuw93E06v48Q9uu/93+9b18XP3q046vJiNh1erby9crs4vL1TLlxar
hYvV0sVr8+5T8NGfrd5efffeyp2r1bWV6ua91V99962b6+t371+dn1+fu7M6fwNX975b3F+sv/Pu
W9e/F71ybX7lerjcX99ZuXVbXcv/ub529ebq7bs37uuvzv169cb9W+urb10vv6cuPQ+P5pdj0S3H
5yIideUWojs+cDs8ONv2EoUiDcLsBcQv1ol76UW0Zu5LJyQtX5BcnuKrrersI797Hfy628raXXnr
7Ik/106sOt9yB7nnv+H+yp5lfw11cJ0YJwcUXxs5KfQ35+7W3Y17a9//cObg+nefi5S2lXQsgUzu
uocdeCXiBcQ/xam74XblldfZztmeXrGZ18OfnmP/KP6j7mpuJdyDuN8CEfMCCdcZwmLRMT9A3XK2
EWnKc3D88Edf+AX0Z01OZ332oHDLuNNeFfBi1/DBvRbqTT7sXdj4PbquUmP+06niG+KtltZ2eQ4X
w+i20qK6vargOdxzobRsuSduw60+cmfdfQiWzW3mMe28u2S4LKxM7fWPWn+v2v0XYGW9ItuEu3/h
/u7QA5/gJ0BmSUPsysX8dp6C5TlByerCcQHth8vl1f2uexffAjvjvua38gSfipYb//RrfQybzYLj
1xh+5mv3w/4Ce/7Lc2IwvM58DtdGQe+LbbjobcO1++v31t5/9zrr22vz9EL1rxs7k5S/Vf3+5vwB
OPSP6BbKW4+MUpH9vej1ufx+otftnbyGjlc/uKB/kHc593Pe8/DPCFaxrkCZeZGpSRxqo3dQ8e2l
/kPRlvkPo6IJIuGf4ARfPICleKG1T7I+JGxeGknu/QFA63oEUr/FEpPcFZw++5B9d/ZBQ+6O93HF
T/Gw+gue7TjpeYjWGZWke2Q04+m3ncB9HW7uxKtBfxZ6WU8lf5rpSIHGQJ09xEdwP0umYJM2btc4
QnLq4oMK957srL5t1GSkszZBRbXxx1DbDtynH/sl7oD28C/6Z2pNOuC0BsWjC5Ynd3i9wC4qgRUd
GZ0M0ujeSsBiPsVb8nchBg/3vo7lEZ55BL/d9SJTuJj/YTS6NV7Amd1j2Mkufsw76KyxjkGNb8EH
OvSi+8QBbI3/4y/X76388tb771bvrNz7ZfXuyp3V+63qZz/88V+5n9r3h5s0jpMCUh/uv79pVb+8
df/u7ZXf4h90o27RnUmDlXUf/7vf+k//1qml31Tz8tff3foNmHp6Si8n4R7d7dx393J7Fe7mvr+H
IYq4v4PwSff1Flr3Ta/c3PrU+IFDtPt2Obyz2PabJeHBAT6QW8J9EPJd0KI6KPDC9wBslPcc9A9/
R3nI7icXfnQOVJzfojY4Nl1waJT1gJPjrrUBSssfp01wB/zbGGr04dmDWX+MYYc3es56ollSPxoU
1zPQ7HBKJZAI6se7Vv6QPmL1E8vTdvXTtff+1/+4s/a+3/JW9ZP3V6uf3lp9Z7X6oZeGHztpaFX/
6YNzP7517ic3/53b0pV3b71ztfrZzVX33vrN1XtecNRtwxF2V1JnZkmdmYxOjk5P7D6CCxV7YNGx
wcN86p0KeisNauCUi39DnqVTabD+frseaK/HaSf1BMvhCd5eXbm9+svonuV77CqLU6fcQ9Y5HVj/
TfxVVEWnpIpYQI20kMutvRpc6a7RdOBuJa4facdTkONd9UiXwiP9/P1fr9569+akp/J+LBpbWsPU
AspNWK0Nz9WBUAHd3HASxXyxzHsZBkE/BGmNDPIT/m10V0ErUNhB3/MBM67+Aex2z/4aHowBnEq+
lFdBeMrV+lwO6/OTD9Zvr647BUb/cPorllnR2OCWBpd8yx+Fc8mh28Pwx6ym9pRB5Q3AG6ZjG+lh
K99+2dLYRd3VCLUoBICnYOD+8rbT5KvV3ZV33vM61h/geT4UIwhg+uBawrV7WtVX3nlnEfShBbhs
LL/H7CuIbTsBq0AJjj3vV5jr47Zk7p7CB78Ode7g+EW7f9Opn/fOJecnEite4mNMGsBvUYIH3+jB
d92zus8qIUsuG7Tu5+R/kiRxnoidLP91LQZeRg9JI7AlHsVpmeDbwB323MPugnN2Ui3n3TIlr1eM
J80npjdR3Wad5JBRS8NBFX+lG5xP5MFWUrgP0iKB8YDeBuN7xLFqRxxdSt6RRT4l0enCcdhB8TiC
P7b0vg0h6HlgfXYKjE30TQlEfK1tTgH8TA8WsmNUCOprSl34fVMGvHau9if5jGQlfvMhORKRLYJg
8NCLk396788YGXVOhkrLtaMsT/CKtSo6YRVqPHx0ABuyCpsgEl34aoVy7rWA30W/6I3SNT5RMnnh
vBLKz0PeFn/rEZmJPuzXiQ+ETcTXhutLXlWcAb8BJ6Qbi+mRTdojHzzlnIHneil3vf0AQUAvU7aK
Qj38nvcVOkb9gOu5AY7cpr+OCnHc07HSocXqg+xrF9R6+i+/URGwV5/pAr0cVM5PW7ux8v57LDXu
W1/Rwj5y3zzyJiL/TXf1r8e/H3+M54APcXi6ihTOkXuMIQbQkrHzS95h/5d0XV5z2DSsT+np/Bm7
CbTW3inHHE4HD753ykegrzvo1HkVa2IdTAyxBMrNY9CWyWaRnQ0Ju67S+tHX1U6FD0nyjlwNLhiY
KFhf1VwSgiyIcI9RgeEJcMIbeZgXVOqnGDWV/DOSYHT+Dsl1OAA18sQaGTxDJ8Y2wZ365T6A0KKG
DBMbvT46EhhdH9A6gcI/5WSnyjzCL+MvnsJqS8xJxhfXGNa3T248ZXd1ZteecDEELlTKZDchZfAG
sptuD3T66+PgcLlNQFfA/YMDYO84RduBIUiXQi0KzGM3foTpY+0w1Sa29A6j0kg2j7udv2AIY8iR
3baXDDWgZFfI9rDCMJ5J8mHxlCccN71j6cYr3Qkp+UdxWGod8Qs6R/hVYkO9RTC2sRzEgLbyGmDg
81W7GYvsX3xAp+CYvW3SpOCGKH99utOWd83JncnlhNDSRjkhPFcmU9Yt1j7TqMJ/Fz1KbxQfoVmQ
/Lt3h7haknXfMope749OiT0s+DBgt6fYouBaRg939lQ7ORSo9DCFbh2gHqUiCvdxUuXdlqTKEJln
PJJDyDWlMgguFS6xPyKUWWuxguuDF3XSItMFf0GIBxtakw+z3ZJHwrOOJtIf6I0QOkahjfLydJJa
Xq7pZrrgOfNtdSgcHMDBBR0hDstRcKO7Nl7WeY2tEASijeCEvKrbNX455G97FWsVvZofwjp4a7Sp
EpduR7cosUYVGAoiovqvFtClXM42zttFzmdONUxw1P0n1OUFcDAUpYyRc1bXRufYJkJGVIZwy1XD
Y3ZCUBCeQD+ySlh9/9a9n2aNFSY3UZWQEgdJe8ovRcFvy9tl0i0naKK5xqHDIdEr2o2JZfZILdVc
RbcYQgx8dNSsHUQ9oJUA/4n18Yje87bOCZdPlg7whX0MHNAfAD090vmerKP/qvm1vOuRN3rG8Uge
+2wHxGrIxhbzkOyPnVS5NAdJvzWfALnxa+SDWrUNoQi5QMAN7+7CYieOrlQlFwCxksn1We/ce/JB
CYecHR12m8DtmLOSDxIosRYFjVshuesxSE/x6kplTVM9l5OyAAiWzyCLgCkME6FB6WAIqVflL7dE
O7QavQXKuT1VKuAJOSEdf82CH/00LgIo3TjJP5lC7KwM66QTuh1H4p9ZkVZr5oXnYYI8ePrqRwHO
/BDlYnz4LV/cJBNoIll4gTZX7bkBUAQRZ1jMNLAykiuFFdGoq1ChX8SzEIezU1WeKa1C4TMmu4rl
6EUQzGfhfjBwyOc6dqb325LCCR8ob8m2CGRzqEEYI5QVWG67jJjNDacNzfZj8NUecISQhsmh3m1D
B/9Yp/xzbt8lk6yAHGg5EGYjKceGm89Vf8EVFRiFX0dIecD98rq01UagqoTyPAjjLIud0XpiNDFr
SYAAlOFTqkajChqljwZKkKBtN26vvfPeP36wtr563d3ny2/GfzJeINTKN2mj7B65x7EYwz0j7S1d
NRxSoiSfRcT7xgQBBqXHmB61Tu4TDtMPMadw9uDlAFb42rx6ClzuRSv3EaZopFGMXGxLz6OR2T6I
YXYhjOpo1Ob0BXzQuqDFOEmt9kAHJhU7t0q6PFrtS/dbO+jmB106wh1iNyfEnFjKawdnyeTIglck
zwoe0eFs8V3WGSK3MAOo8N56G5Q8FdvBd7NHKtxDKEuB5AnSCsIbtTbLb+LksUzIuTsUae1Q1mQY
RLhmMTul4rg7V3uIEIAgfwsjDqqLSf2iVpUNMp+IsGn7xCtFQ7UsywGePKVmygCdYdDPlCegV8Nh
ypTHw3sManESGtUJtHyBXhXpymUgKs5nHYP7UAPSOEU8+hcFcWaCBNF3WJ2BlAPcZVdK88pX56jC
p5AKRT2+Z87y024Ubc4pBU8DjfBA1erlm830RnIag0xCcWcfYJzBFrOw7MFpzIlXX1I+7Ombs9lc
nlAH4pI7EF8F9wqrBB58d4qVS1YTUx8TU/b1ogV+Bvnbj0xoiGGoWc/Y7jI8K1vhzf58gl+lGuC2
95ZQ+aKe2C4H+Iu+VJ+LT04A6S65yJGCKqvkVy97ekBG2rDUj3S3wCbXEbxo5/XmBqB/OpSqg2Cs
JfUoEI523MQQtSrEco4J7i589wG5S2ROQ5h8pBbkSmlBGBQEF6EGgLAUCEIMxZnmzeOb3IRU8yZG
TlE4dhigGrwN6gJJURnNOa3+Q6nngq4YKuka7/sY4ViBq8J5PiY9tBtpTQJ7qZRe2iIg5ZQPua+A
PaAOXYqgWF7z0KlpzJapfYV1/k7oC6A3MJ8BlvkRJDv6sL11BR7IA4gcCfJ6St7MAGsGHQpPCTRY
Uz7tGGsmDOVg5I/65dQ2sjXp4FPwVlGOq0WvcjIrXNzcMi0BZlYwoCUjCdZS34A1+U/lqLLfdoyB
hHsQuJOjgKmmX9Z3OaKM63BCfkeqbx7rzycIzAcWuMcdfYtTHgJE6KF7gKB2hMLW4gwdTQSc+rcM
3LSVN2JcirMweW9aGJwFCmHDl838/Z/zSTCBfm9AGgNAguhNcfaRZUEqft5pYPeXbc1RmnXOOp6U
uerDQh1gMrgIkdfrrTIlcOH//TGkNVUpMnOi/U8Boow1KSqQBm3lXvNbvoOlIBS+XhZ+FEtRAj/W
mTdKtqFAUkQFV7d9ZGgH4JJPQwJPtsx3g5gtk+qYSq63zJU5r0SFExJpQsuOyHLVSh1o6alnwUA3
KpBT46XHa7xXXuOogeaQu30OsJjSkniDaxVRgq5BSSiEWnmTqLMIgA/en+o36wBR2hl9j6UlY7Rx
q6HeNhmVLptX2I+5qPHLhwwcJVgjLEkWtPbsazzSKIVJ4A3ZWW69gvzkTgYcOJTy/tljzEe1S/iI
ibD7fDcmOdDKhUL5VE6Ubig91lnApVKuEjMXuiZJOb4lyPF9DpURBCZkG63kEspviFKrOza1+pr4
hCVICe6A1smDSiMMqb0Z8vImOEF4RjKVhtRzGZqXoIYwj94vVDX1QaKci34pRiw8iRALbQN5te5D
5rvms9hwfCOUnQi9qm9YQ3QR0t2qBAfdYqRvEnBG6kEjMfTVNwgR4KS8Igcps44a5u8eY8upzM6E
DH+sBJYgPbkTWh1VMwRFHraXd4peBfYAahveqwYdeXJsQDJSl0G5ZgWxojizS0WuARVEfFZyo2WK
6ZCQhIJeCx2uE4oIVFGFqiTH6rVUTLCNwxRd2CgHsGCxo5xyAtCvXm4770hOpcv57H6hBQXjfl1O
DK8TesZvwj4AURFZNJxYxEz3bCKoJek5UcIIT8clGAE6K6Xk87V/oJUQtJvXRabmdBJahvMxP2Tl
o7uE0lxXFmgYVCZiJFR/fptDtC005CDbaRpkN1vWxuQ2t0Th/bckCHTLsQFSAhIJZvCx2shdidQo
Q07JPTwpA4zQEKMUDl4hwtdrH0X4vqYSnTTdjk2AWvQqepTr8ouRQMbcim9R/TE9gCNpS9IiPaQ4
9bFFsFYgZhuMdLOYHEGZdLE3NNfSMlJg4fGfOPzvSZJd9STuMBhGWrkVR8O2LOcD9/oh+GSABoeH
98aIPm7ANqTiBCAleVIqNbtv2ptgl0FlH/MqLIbsbLeqhg4FSSmluqnd4qxwHUl+sHzGqxw5MdmM
AVGv4XtU4/+qVnQnvzmMjBAsVpQ/yWhd2oXtFM4JJ1nlLUq+0NJcuf9I4VixTSApw2eK0l5lsQfS
b+U/AOsHiEiO51JUtFwj9Jjl8Y9wUgG//wQWZEuUGHy2D4rgaXUfnJNsC1FcHK3to7OPagAsucij
AFONckgB+zsB1yK+9zL50WmfTN5/FRd8GVzwT9iq0xoUvd6I3CRIfhZ8m63Bvi42ViRzOc+JUjFi
85RWoia5tEJ3StmsjxpUJmb8eReR8SVtj+JqjtY6DcHFMviRn9gsp/KajFxyJGecbgRdvcAf8ruS
ZNXtg3YlCE9PFbC0eO1+EO49AB/h1YFxNx5lTnA4HXuEEI91wJBEAN0sdVJyQPgJhyTJbrQY8FFn
+2g2yV/hnCEHk0PYVXRHjkt7tThnkIZSoA/QCW/6Sh2rCoe5/UrNqepOlma6k0yjKRsPagzVCbYS
bHoYShj7sCMRiIkxvhw1aAfBYGZDupnJPKbtIj3CkDbOO6d9pHAKwmLUIcYM9Xurs3eQ8Smpn5Fk
Tzw6DPsLCU9joNTeEZkLunXsX0d9pm8SEmnKPeUN7MWtggr7QCWOoJFS/+Xt//hz5VphiwGGUFgv
GYBHKg9hVeNp1FUcHESV4zNdEBkHVhEQTILiNZlk9btBZflbkNs7BLsHfZM+U5y04a7cW//12r33
8r2whZZXizqtrUDUrVwbz1H0rWaDbCznSInjpZlUCSZolB7BZ4xatk3PVF7vEfnQjP3bYdt3gv+V
b/tt7usmqoy4Fjlje7cqjKkmb1Ny+/893eWebptX/28SRtO+EhYl6icue3mK+ydBSHCRnGpVkM7C
UopyuAjrCCUE5K9C9gTFVOKjrC3a6I7ppo6bDjJ9abgUissF998IhPQLYRe6MkDcH61NjwEgjP9g
yudwpEqXy4EN+dKY5UNrnXUKqVPkkWlF4e5fw0UUc+FQH0zUSkzHGAtDnEDZi4ETcHs9Bp9r6ET8
lKCVIxV69qBVgALbI4S4rtQFkk7P/Jb+RmnUy5SaY1WW4bFoKFkg76N3zQhfMZ+ACiJrdjgL9ihI
y1dYJk6c5Lx8UyyYldFUJiuCZQZvVlG8BBesCCEmpRVccgtVUaWfhEL0bKfYI9ijWETXxSKzgcaX
V1o7/x7cI75GNiRpdkAaZEconTTpEhehrdqN+jUnVzf9DuYbjI+Q9HTQMrkwYWGYtMUqR5Yv0+UR
19plradwWYO8/rPx1/eCJkcn28Qw0yhjqI/HpjBrg62/iOnvp7orMgXwcYuBZtsC6hfF0hXkOStO
06SQQoCgJNXTP4w/L9F8EdeE7JeCeZQIv/IuXEMf8iigpbP5vECoZRoQ+pGTtwzt+cg5qy7Rzfav
l0nP5IxbXHjiFE/ZWT1bL3w+k+Bb3rVA63RHqT2JyyIG2m/Ul3dXiqsQubqSpVhfef+XK/ciDqol
yX9GFV7l2/IV/uFv7Xcvlb6Lonehsv29qXvhw518rsm9c2/1n1bvuWN0c+32WuSZQs1uADnTY4is
VZavLvW+uDgj80x/s756xz7VQu6p+DhdyEpTaFqgZnk851DuLDANMHwzQmLals6UeA5KBl8IjJR0
CXY+52go2FRlL2V+zS8EGIgBX10Bv0x3kDwbw8FSkiJLuQscyfl72NX9C7R7jV0ilkSuHQrTlOXL
QVuOkjZt2OiX34x/z5arh1vtn8vW0DvJImH7MqwHNNnY2OZTEcPY5dit/uFv8REjAUk+B78SJa5i
l1wMuW7dzpS3Qj7PF9ThjD3G7KlUx20pIrSObFfQ7btF+LCds22t1xaA9FR5MbuyF9pkRmfoI0J7
cTfoY2wY6QA7IPv91FYBW4UQi3/jvmFjU/FGgMRaJxpQxPDi2xx0EgAIuy08nwmuD9JSgy+cNVCp
E6OuQSDyBwkGx+ZAI7xxF7GImPzbSR7bP5cgiYgusytgecl6Jb+Z5qcUc6dKiuaSWEbss8W1bEZu
ygLbTEbZ5/c/jhKx6J9hEgIlGD2CBopVt7AULanEeS7VzqFQOSmbyeVMkxxt8IU49soEpW+C6bLg
u4eaIhI1ywnLGVXkeTKltt2mJwoZ6sakZ+gGmqlwWfYbmvKTy0B/kbiutUmyEGBKnaxWjk+4VlFZ
nziuOlRnoWTJJndomBa/UBPPWp1c1V5kD4BcG8TEHWQgCqGxD0t1DxQqiHInQSTSpdE8QRqVVqFa
anx00SLU6VMwnxMe+c9UdgGo4KjkbkUZCLxPzR+c494OS7WLjUzAV/xRmfoohDHKA8vVc7Dr4Jj4
0g7woaxkLzcqmAn0JJhSDSwoUu20TChFhl5i79DZGILUxxCZ3TxcD3p8OONUWxaTOg0pSvinOg86
IsCi6RVMMXk7MYBIt/phgpPzG5ILLwC0ZgJfgecXtVnlOGeEkbKMkSqzqFrkShaiEhedpqPyLoBj
I5JvgbNcAjhL8WcysD5LfPU6RF2tNAOq7BGQVk5EtVaWeHRTDz/B87xUcagHgAYALg7BCVSnw2jV
2bnXaJJKKAzjcutyZX8WVrYpitQ6rFTdgTl6KK9JQzjTxD8mCuySAvRblHZhrlUfIaCzsUJxJEUK
VyK0AmiVkBEZ1BYxPhhZNfipQ+gt263ofvoCy36oqDjIgQ0I2McERh1GSF8hJ+sHBH3afEwFExwG
AAwycCz7PKsiCxZWwF6hFIrQwzISKNSB5PkN5FM7l8951swoC3rjQJcTFwa0okoMmC/RSdkRTm3i
QNkGAeSHA6VoUHm1Jlo0rcdl6Lq0e3tp2PAoZ+64Nd4WuAqB7gGUud/ohwaRH2cPFe6KgjCDaw/v
powaQwPPlYkjQVzskzKM15DN9OJ85VbaYwuvF1XQtPxIlwBKZ0qg6DcGckBUZifgh9WJy2iPmMyN
SQ5I9gSL2Hu/RlXbPHGAwfVTbk+1UwRBZTduExVvI2HUdEHtJcCsfZJDM0aaT+bMJGZQxasUPXXC
ORCXDrtSDrgn0aRzFIaLYjCpae6TwBxGH3r5TRyMQYJNz1l6OYBQ+7WG3sXd71OhhyaHBLZml4yU
UNuzRPDP12ldqTCVqSc2KA9RmpRn6Wd5HeFrZedqCCxOEULDxsA1Iq5NipxNGEyBTtEvzFHhMD2k
8g4L+KYLi99mRRwvsWKcKa5Whwgaw0nuQ8ITvTJpcfEicRwM12cJNtYvApJ48QYZ0i3oxEIzP0S6
UwzTiYGgrxhAvY6Csh5ov3YjKjYZU5mEf9txPJvB4IyIFwGY2I4CJ0po6OwWe68Sw2ZRRLuBmXbg
e02F8snk/OSsdIs7pU7ecmjgm67dLkmnZmjSee4ge0gBeIQKtMHK4YQuE0Aw5V5J7ALxVXTywRN4
tSKlHcbQnvrEilB/DGYx90CwS1uwAl0nIMfU71GDZhpYolecSZMN9djDoTZ7bhpKMrS5kJXzJ+jK
g0rBfJufzBCle6ad6XMJIJQ7Wc3087d/cK40cspXABsiL6+VfvTDZn0ULbHgoYx3AefQ3whKByb4
eayHQkKJewX7etIE9gUPgBpokMJNE1QZUtBY1nRM65bt93RDL4jR5AhruDkBKu8q5dARKNhHR/hN
pLKnbrrxQnBZxdw0UJRAXBn8FnV3hmXfskyhB8h1pOwuTwsrTArLjQdT88M0lIg2wT3VAbEwiJ08
AWlEcIqUX+hudC2mW+DpMMou8M4xGCTn9cIvddndNkas4OwUCgy0oNG8tnj+dZ7z2bikPV+Iovzw
boN7OTd+NtdSdIB6SLfuZAjlgR3OzUWlaD1sYaRko48Q1PAjkhg50UjWB2D2FIs9v6+iOGl7qU3b
y6nkUYA5EmM4Ij/B+RkDcZhrHWdOcbzy4L6kmuxWB2kbR6qSPP4azskUcsrnTA0r0iSYLxjXJOhv
72XtYfSZr+70qeSR2w7vQ6kzfwVHfAppYAaNneXUCZjAZC66uOs8hZc5U4PCUy24e0aVsPfZwEGW
YyLCPNUDOjRkTrN4CYW7HMqknOCHYWPOMXI1GlNjyxUDyFr4R+4Rki+TKyk7PL3Q8JNZwpBk9hr5
j9gUrXCdyFxSS5b4Mk6Gp89lBxxmR4W1aZYlWPqnZw9ELC6XWhFzxHjUvCsrT9TXfCMqeQ3z7jGn
VpuPdTkb2Y+5Jql7DtloosR1N1qSR/RqPpkdaoE6iw1uccRGhvA+nd4RgB+TqPEcPZzD5xfSjIDg
3sBRngNxpBZ6ITl/M99LRdPJhdDNi/oLdIu5nQmt1ozkPaaesyNPDF6mfd4sfy2+cIhnnebtptxo
OS7AacDfl3F6fTgOyrKbcVQ2fFHmx/ICAa4/bqbxdzpgxIiahktQlg4yQGnIt6JWVZBVhc/78dqa
H1H705XfVvPV9+/exX/rhhup1ehnO8XkX6yebeSjrwLDTCJGSepW/JsffP/vLQWPpUd6+c0UI9de
DjRthJ5n9m82j0zmUage3WBCCkThgXO1EEnO0IZgEHKXIROmz3LRBlhbMe1ymyljUW/HTtHg0VCx
cms5JEvKV94yQDudYmmYZiIstf3ZRttFesVCLdBk7qJKVLwzdjaanjdegkLHk9m4ZJNrmonGsvnL
hSJmPAIhYppthQqEn4E2qUk5YpSNGK/1VrC7Ur6T0LcWKjeQeOIsvWFaQoCLXmJug2bS4mIg3cBP
q3B8wKL3kJIdcu6QtbZuZeYkSpqE76hlRuIlA/Fa8RA+EH7v+vsrbAWqXKxY61z1AUFHNWxAwswg
yFZmG7ADWl4m+V+HtN2SHYUmLxLgYbq3mbEAmSmF2PY9xUxCZU2XkU7emIu+iYCFMM+Lhrch2XEC
r1HMyPD6R9f+cvyZu/qnbhm+qBYWLy9dOb+wuLA08Xs///755QX/+Qvn6f8uLp8/v3Dx8tL585cn
/+qz8XO0aWB1Xg6y8wcuQw4tj6P4iDl2zAjakPHUq6tOuTiRiYdLrJQyWPHiYkWeV997Kgrt0TDi
IkM8kXY8sWMmcV9Xl5zihHIhdAvlYbrHge67PDJIEk7bhnOX0yiXIVn11Ss7Hfo62cEsgdzJmvDg
SSqPh72mGW7AlOmzlttyy5VnIDcOluCMUGMb5aeNfgVJ/9fu6D0cfzL+F3cIv3Iv/q4af+z+89n4
U5rIwcvpQ8dPU57FVF/bR6onFTDzUV2xCdUSyx41ENxPpDw3hKQq0k1ARcq+8EE+IRq3E/GOJInY
LoRjEyp1mt7c2F6zCwuojNAa9VQvRTx1xw4iJs1SstSVWQvsTDnfAo164d9X1GQC4c8cTXtmvjPF
oJ2MCy5tWtYTP1VnrD/1JFH2L9qRT5Jxh4OzttPokQk5GXko24nPZHdKLbPZKR/GfglVwoguTo3q
iA06IQwTldDkAnG2RPTYiOEgStfyfLQT63ZMowNmYFWLR04OY7cp9rU4na4IbAJJZwj5SkOS/TIv
FYZ6TRxjFd2Kld2iazZxyC0eMl8h2VDiWRge2jBhvEdGBhqICYLGRkYtwJXUXM4YbetrvTmTOfMI
8v/HzOYXcFj++/g/j/+Lp0X/1P3xbPzIHZmvxv/T/QUv/94dql330jP35ufjHfcveDlnXq9MaV6l
RzrYo4Zx8gk49vUt8fYrWWJZ2z1meTmIeDgmD9qK+QXQ2gLY3Z5kw9+pbJqYlcqFB4RSdv847//w
IejQEvqWUhmQnMLrXMSreC8drcVTyNd2kOWwQR1PGWMWIMMjIzgLOK9S3XurPEEOTkq2BWNQ6Juf
6RC1bMIwOFsyT0RXYfeZmRbjJRWJ8gRfa6KLijfgUTL+QJy5E7IsMT46mS3lr308izqDIAsMLkJB
PAz3DPL1FJj9jWGkektK2M5GIFfGtk5d3xaLo7xP2WpNk1BI9qInqtsI4yK5pxk4PCTXNfrrmMc6
WUe1wbusTa6IFy7vc2ru43A4n0xumTCOaLZRjnJstShj0kPFUx27byfR+kpKKXm63BqXPJ5mj2Zn
GnaMxIHh3y+5suonE3sTAiKjWONZttMkm43/uhOS33pZlrmXbXqfL+Pq1DhsOtPWG42Q1PPCcsPK
J9kzWYZSV0ga05UtvTSbxXlFpWNC9fkKB3Fwsdzw2wKvLvrUAaTSx7lSyYwBqWCzR/ln/K3YF+yS
orDYQ+k/C0knA+r4QgZ7JF80WdLpqpFp2lumkodKY0urqHz1UFGoNxb99HM0LbUX1Yi7GMBAmYTl
SOjQ7Wsx6eZU9ctMEKXbxLuqe7Rp7w4JfSzoKMU+kPg0mo9RWPDi4LR8/jw2WryK8uhQwXhgWpV7
Dn62env13Xsrd65W11aqm/dWf/Xdt26ur9+9f3V+fp3emruzOk8IMA8A+8XbH9y9u3Zv/Rc31tbf
uv69wjvX5leuy4P5wxW1EbbU1KeU5CRr7iZIzJAQcDtM/xaXY4emGzPp8JXKC9U+bLZ8WzpanFrd
J5bpJ4EAC0VmF/Jzu4SRw9eM/6ZHJbY05SL7USMVjXZs5PraJXKbH0ZutD9HVVztO6J4lBuTMm02
72omo8OTMJMJxcnRTYoUpVbPisFh0Hs/JNafZEAsKyGcFsWKZz4zXk7NuCX6cD1XWGZ5Hsd0y1Fl
LQXBiMVMgCKQ+9wRhNwwqWtMNxbQ4kA2IAwZkLsIV6Qomi0QDBQ5rgRt4B+gCAmZfJPJONGOmeVm
Lhfq79iWSG6QnjyyG2MkrvBM9AaRTIthWY0cgixsbchhmAY4bBiwComfm2qO7YY2AjjDFIG0TXsI
WKJI+NUZXGR3U6lYpEwwg0JVLxPrrSkauYh2IHAUKHx+Pas9EuBmHozVrCrCTsV4UJ7vZjKyV2gM
BwvCFMsx/XO/yv2XoyBK6vNgwmGzj6HxRKFlzAT6mtUF6v2hgSOwBgeEZRRQT2DL7M4ymVFtx3LE
DRrrqgm73zzdsDU9xnBqXGGTmOXGfXNkiOS7OfZxtRqXKL/d/CM9JWuhPv7qkkZ5riEBQMMwXU32
KQpTCDjt2K9RS4HXe9R/1q0uLKIdxPs4JOZKY4UNbCBXocksewqjp3wMj1TUNX1laARQZqaa9NQE
2Ibpr4TuNwW+YkbSju5tqMa5UBRoRz8pUVlkmBukHbwpPlj40Tnbqixhr/u9CzSYPaaEOLJfy3U1
xzpKEOpgyphzkLgbeIQmVakzD2IPA9XoC6QYpyjf4isMbHteQJ/LmXKPehEfdSJlSCYlyOQUusmI
YhjVqWWjmFcJU1q5AnczTwlV6A4Lk+0bIaRSkm1ygvJHTB+mN0HwpAdkp8d/lPPwdSldFEePNPyG
TgKWtjpvFVh51qTqYSzBgNIIoU+DfOLjgFoIoQZOMwh1P7dfxehD0YmrJeXfQbdPcYcEDSn3qfQj
32tavDGsiMlDSGY3bQSk0oUpKhQWVJ+2hWaDXjxleUyKMjw68HtNwz4MXECjaGD5UTUxXJnLNjTb
SQdiJOpW1l9ifGZxrPBuFfrefDK9SyNxtvVaL+Ko4KwF6DaIfxMOZoIlNqyvaL5T+pOMYklONraT
ZJCIdlCJdcLUwPf8vTSPLrF8TobfpFXkJCPGbBertOIWOU0R9tROgy1xUdtSwy63I9V6W5cAEos6
I8hrYzI+e3yYbekR5mt08akdK3KjxmAJnhCFbN762DLVbHakW9T2bCpofU9y0kCUeE366M1GoOU6
d1lxlcNSSOkNbKU1F6k6OVi2OYtpn70cwxG7VAAcmFEgEx4zGgPFR4uaxQyHR9EC9SqgsKqBeU+T
1oS0RvmE7Zn5pxFTVbZKbjgbM3Ovnk/p5gYYGCRF8EhkXOHmE9rLnlAxvunQh3D7ZIJUqlG1jynq
kklXUHWMbFHwQ1RGXg1qGuwOHPBaajqCf0jaTpTs0RCHQ/Zy5Vz7saw0mtirXpUqOQwws9AOnq/3
h27fGeRSwT26k8ALEe8gZUyKYIkGY66P9CXoHdynET5dM885kKHgtKtmaSocf0h6JJ2t20lnK0W7
fjMjD6mt8CjJIBlMEmQ63NQx1CodO8Ktpfb/asW9pAV7uZcfoYVxflv7+r0KSiTYFGvlUgXaF5jt
Px8rFTOMJK+suohOl3uadCdNING8cEGibE1pCt0pp0jP04BJa7IrubEpXTNLJp56PbmkYUdSNRYv
Jg8gyngEkzqApRUEDjCRLpFIxaNId0BTobFWZwLOox3S7fbgIvLql7OfUwE6TXVgpyAPoYSPg7Ps
3O6iiuDzWHPwYLG1aiO/9EjNeXdi4R/ZKEgT4ueW6YiWyQCQwgVnQwFH4Kgf3lx95z2YyhRBETjg
AqMkpj5T+upkhBWZk/ixdvXeLhD6WxA4NH8wrfD/XyiICsxCew41skVgPjlhIY+aDKTaRihN01mr
wBs0r45lEXz6vA5U3k+gqTHwkSJeDTK++d9WfaE1DHvTd3NMdjm5wwbQiHlomT5urphU8VPa8h98
/++r5obccqpXFM0sB7019dFl4r2Mtk7OLD0/KjdpV9Kj/+yN7ClmHc2ylo7+LozPjr6mVjb4SI2q
hZlzpEKB4p5c1rprwdEznak2CmlIdEYM7aVMhC6l/BmcvDx2QY1ZkPoNxtXbxmlOOqTVvDG9+BpI
a5RrqlMbNkqH5g/DNA07EtDygKAyJuDVvtD/dvB1z6hEg9PKNAhaVJVrhtTSGgcQmGbcmxcQtC6M
iH00PxYekwAJ/HJ/xpMPaHT0uL6q+PsPccKy9sTRKaIRlAJX9pHrt9wp32CHCByxQ9Drmxat94mZ
z5upvcY3Wudn/okm5pksWEXVfhsKk0aiptg08ZlTNEOppy9QE0ToGtz6MprN7ZTfyGcR31Jm7gTN
dAp3fpgApZI5qxnk6zbTsx7LxkmrVu6K/TI36xRotKbGHPfoC3PZE7jBfikT9urEwYgYEoa87yor
UUIvJo5aRCTVD4xIAySNU8tOPXGbFEVt6ZpldpiBJQdilNxj+KiSJr0QiykliRp+IlWL5Bpx104C
DJp1dXuMV2d7bH1QgYiyEVShbFRrVCiPgbvUC6TETW6Q1EXcAHZCBAaa9UdnCaVsQY1AJffNre1S
Q8vjEJRRTpML8dmmsHMldf2I6zANs2WqEzi8fUBV9jlAsCeVPpKvOtURDe0+7c12cIN7YUwIjcpG
dF+acwrDwexwZJsoFCUHux2XhliWwCxsQMm5zT3XFE74EhvqtMR0LXBWB9A3AuGzmd9gzBbQmPEX
WgVgSVJXee2kWbmJ9JVcZIPpsfqcq1d7unoFuD4dMkHh+yOu/sIJ5UjpERch8y5sBaIMvj6nVrM3
Gc7NAqJScwstJKO8gzxaK0X9T5VgZESiqbLIcdcG+7WdzR09Qb1Bfibet5w95X7sK4ZZUPtHCedq
ADQJ0U8rol5SpK1E7yugh/LIBe+ZuqsPmPE3Jl6aWFA9ifm/+zjrxPAwdiuZ1g3rFo0HiXMefZrO
rRJDLL9CR6ghmvzFipocYRrQCdNFwU96Z+sBT4+SvHoPeWmQAE5yhMKk1I60p5BHBrn4Y9SflOS6
o9kEOFtOZhBr37UwklafrAUhBC81y/AUVA6n9Tx61j7Ixu4HPjxNJkmT89dlrzdZjGQyE5V39UXT
SbMM91SsmFmO3C+Ta2Wn75GDOYARunEpEPeKadj1kOhWbgWAHLNNE1SByZ2K3h6omYUblrnb4b4A
mkITaqD4rFBM6cC/I7UskSlC8Kswv2EWnyMDM18eq0aPGFETmvDxLNEa8p220PaqDFmpl7uTITeG
pTzwMERE6UXqKxmJ1Ob5JEgMqsbaszHl6QiPZIgQO1ZaUcSqF5TEU+wj1JSvRe2rcEBgzFiSX1mC
NVObGkGzE5PQNtgDy6BKaeBk5I9SN56W3u8Spue21MBU1HdthYmpIJFK9gHlI3CPNcOMJ8FmCjBj
GvBM4kPrGhRI1nw2jOkrSGlJ8kY8eC89r0nA1Qqza9ulmth24Ooy4ssoJTA5oJZ2ceyvGWys6HYb
HlFWBM+6zQKf7SDhF3G/2JsMsyFe1ykpdb+q64q1Rs5r86h64IEQQH+Hp6h2lZundHjiEOjUnS4N
DwkHIOeTka5HuDFpdMtIWCLHPuTGy8K0RnRXTIwvE9lb8cRr6x51ElI+4IQgmLcMHW0YyiJtnSdM
As1KsZ1zZiZ7LFHK2So1BiXkj4m0u+ixJk/KKQsz8V1IV9RpL41BGNlBLofwAo5rCBWMKfwAQufy
3JhuoYSZreJSPnhX5R3gpdr4h1lAfPQZg/VB1DGs2gkMj/iTJbMRGTadrnrBd3JLzRumi8cSIfhG
ApqXXjqeaYjBJ8TAiGUIpJ/Squxz67V1jDUFEVapRAWj0BO15HUmhJ+QONTHvhuCzGRZo0HP8YRn
lp940M9JZovci+o8EK4w8ilUn2qaj5PgIH/a8h7vI533yXm85FFED6aDqBA75QsJkVkTdvJYMZlA
YZOytwd2CnnTTF6cNWM1PeQf9L0T9LtUQFEsCQVMINRWn8/qL34GDoAsgaHaSMbFC+ke1Vjt9GyW
5HQ6ve3uyNiWwOfC5AF8fZu27jNNkvTeZfzbKJyapEITl3SqYYwanh2K7BMScOTSy2HgyJ+cEnMn
Ng43bVJ1xjXeDoNFaqlUlSBZHV2yDc6S4roz88PTc0KZB3VTYSJGAYTVKmbrD62jXmO6JwPsyJn0
CF2Wj70vLEAx4bPmnU7KZ3GFJG6czuLYCiM9G6lSKsNnlF+70hD4fpLQ9jdrW6fj+rVdwud2+FEt
ceCTFMwGX9EX7+Sr/9OLUvGhTTt5XnLiYeGNG8ITe7CU1aapPfX08qfYFil+rgXEls8npNL5Cjny
uA/di3FBceUBb7HYFrZsHwf08jFOKBFYN/en2H75rOW9BINwGMyQxg7sUWY8Gsmo2aTrvFzV+ob8
geiBdwSudl/QNpnqcWxc1OAL84yZxJaezW7sjSYZAKgY1ICeIE9eJkfHbCYU8+Wtvy+eogRZwLtR
8fbI50Yx/+SD9dur6845oX843yQkTXCJ+1rTRMZ1Nx7QZhTHIeMDAq9YLLi6mFsYT1VQ6orGTsF9
Ow2I3wyoMfHTGzHMr1TOwiA60vXoNdq6nyfONG3mFYfioVAaKIQsoqOWOUTJ3Nfmrm8e/EM1WS70
QZyGJY7HGtVBI44y7ei5cpN/wxZP2IWP0dxHmRYdUxDRdnspDItPUm5N87sSEWqiyQa8C/QvbUrt
3AqDxkcBpgMyxcxkBxYapaQLXp2pPMDe0My0iNJMoa0lMce0c0XodkjSRFPZmRioUBGKyMDDRW3Z
A9SgvwInD5wOnYj2k4dHVk6eOIhbdkpcJR09RaY4DnfWtEY2V8GuzLGC/8pp70X2XUMjFqBj6hM4
rhthCHmpEUvXZah/ts66/rwSIwSLmcHeb6BynmlMkq/Gg194kCFjauIHTdEREaC7MJr0JEJCymO/
OtqglUVfZkOPMofrLKYm3xSMXg47oIJFzRy1zpSCA92PQjRxTOo7Vasq4SuKVQvrJZ2HU8F/mOFm
IO2jV2YDm8K80GmgZDCgXzpZBviATognOGPIUcCXlBv/EkrPIqPGdFz60nIeDzvZz0wBVonkQyxU
N3MRKV8mWU3NhE15z16w9tOQ2NpRQ1J67OHPyjTP2gysjGhvyxwvisy3rLOOdOskk5w0t68YjM3l
aWlFqEEdKbxMhamx602mUKsRcFswG+Yjffuwgj3BAjT0ymJPrT9vSQf8zADQLLpoWlqRSdWwV2DL
bTBAMWo4rllKRZ1RPgooIF5cjPRhqHOC9fHV4RzaJ+ThkTQmn9oqVqlepRBVaSc4Bc44Eb4SvNZZ
OqKbEDb5mK6VJB4wuKIaOjnXnubhXzf+2feO5kF1+t6BSAgaokgxEooBAPyQIfae7y7Pg8+Hmr62
eEgcCG2bvqIAkktNk9ldhIbauywa7IexhV9/TyXIKVBNbhfyZP4Zyjm4fmtSs2ADcsGvGXCUQvj2
EVpN5oaAou9GMrOgG7n9T1vVpHRiri3W8E9gS2whI6OIDVsM8WX9rD6muct7SGsN94Xw+FMAVQ5t
FHjAvBm66S6mwsN+9+CUTZmzyyU7J6VSDX2HhM2ZZFmmdLc9TenOpOItv8Q0vGtF2hTLnzEN6Vlv
OmbGjAt9lMDflBo6TUdJFrks835Mr9hIjJnEgZDj1Ho0r4JD+3z9J3n8eWhDUGojIKMXkY+74MXH
mbWeBtZh8redLT48T9i7EOCWT9dZqz8qTcxSbb1KyunjnGmDwTpF3zejfxFIlafgNP2N7QLWMqi2
RUE8GxRNw1LauEynhEfUi3ccIgCZONxKujgN73noxQoJEPRv0gzKXksGWxPObhgn6xuHdDW25yxK
u3Cuc6K4LBAD7H/Lyfwx+TRkO5O0blK3k2AiySbjCqWozyLyXCMJVECfJdeJedmyBZ3W7EsRFyMi
KUVsDabpDf9v+jkuJrCImU8nQ4ZefuPDdFy0lwOdpf8QrMKTEBiFUiO8NjCZPvJ6csUQXW7AVMir
lBt0ojVbeZimqhCn3p3cLupMARdl6IImNSU3ld90vjFisAkMcAH1YxBGmsIeUoNdIiAK46tueJSV
Le7CNJeoosYvpr88YiwhOo+J4LdUo7oe6LZL2SRLdGJIezJ5SKGlEYQ7dNSHKpBWF2+w0Qqxmrge
kmyStcCqpUoueE8nkVvKkkJUMozKcLvSc40+JGwTgwooVabiSwI0FOrdGjWhO3LqyEkXIzcRCFzY
IuGi4YPSL3hBaaYPE4O4ghgDaDqjETXBN1HVaKHlMFPuo0W6vzDSO/ZmEjYCrYNzufu6mBdcJNqv
NyZ22WEx07HCm6l3+Gnb+1cbFhGd2z0MrOyklo1tbEnCbQvNArY3SFeNWf0Z7FRLd3PpKgjHEdJx
CM6DABtgNLDeBJ+c3aXWQopWBwwcTlqnUQtjcaCtSIa5J7ImJvgcKH6CaLWEnu0psz2UliNXb+yQ
MQ0I8TdZsHDrdHlWf70hXqTMUYm+GMn0vXzQAEWwwmnkyg33FLcyZfZMoWirXJ4O5Ae5tkSDDa2i
2DuktFSfaqsi62M1xPSsYotzV2bz84sJtanWknmHX9BJGjB2EPs5YuWR056c7omRvjxEYxJlcJZQ
LHRXQuSRxU0Zck9g1IsJExCI/GSiRVNHi5qJYwvQIWzR1IpaDFwubyODGSKC0u0CnK+bLVvnOlCS
Fm/RvardqSBMB6JBUCpyNs1nBLwn9Qf4pc1zsCcv/B8h7l/STGnw/X68xHHJdZaVzVi7wDpt8u7d
rGnMFrvAU8LiNOTwwBdjvwod7ZGU7ehAUVsJmQFjn+gQTbJRnEOd0DlPCcw2DI4gPreklb6ErNbb
ENTM0hwxtLBDngMbNDPwxuVoyICPfDaJqnLk8G9wl6MO6IKnVvMNtCo65h3MjlY0wXMDC9NB97Ah
7uYZ8brUznOACp6qboGoqIVTn/eJuSb8OlgMqCHTz/k46gFmkO08Qizf1Zz9O4RLvSBiCYqOsMjv
V2kfnI3o6HLyXhLfMaQEcyWmAoIQ+MgTSt261O+uQN/ZiRoDrulmD0mxbpueHpuXWYK8TNz8qKGe
QudOshL1VyepaZnPdaCUwl7JJ0yVuT4CgpiYqHB0LkZxq0XHPF1pVjs4kmLIcMlhxHXb6LwXZvY0
Lr1XzMs8/lAx7j7hEjlIa1DSy6CkPyFP+JDmz/LQrfQiaZqQ/fnYVTFJvo1S+z3cFU94CARfcRpw
txTK7Hgb9Pn4i2r8O/fDnoWziymfavyh++gBlpq85n4GPDKoRbBp8lEQ12XQg3u6WCsScojfj4ry
0ai3fUUgTC2FUWbNN/N8S82Ix3Z5aoCyiFmoIPgWXTPKzObfAktVnecC3Qu0VwYhfBj1lEUZ3Tjz
FZXFmtBw5qvB27Pzbd1/s3djiMdUxZDTEWlGrs4lm5+GZHMikYeyTyGCGhlBPSyDBKiaPevgtZho
TY5gODmFnJaugSg/KZyV0iz0Il9ApMl0IJ/qLw8e6INlbMTF9ZnzSD1laEzqU/XjROpyNgu6TORa
TbpqmqNmmSTSeXpvbjsRVQncI4zoEYRzCfwdc77FZPoWKJ6pgDQcUc0DqFnVLSff+Chq3mgsiurr
S4tVPGbGm0DgzDoIPNvh2aY+y9wsTcO55fbTCTE2sd+EPIYEyymZTX/bPUVwGD28LVNk5MHnXR6Q
z4HQXqJIJVZJS+1ohjjxjLaIIjMeMZiKi+ch6DPzA2WKHkOtcQNOv0G3u88P4LeJemHyM+l7U5jc
qW82c4wXkftKcrcNnLOEwdS4YYzrsJLdT5OKEawzAIzaSd9i8VinqLyctzvVvLB4e5MlNM/2imxQ
xezcskLgT+ex07YadZDFyp7Ooof1yCJDsS+533iiARNQQltz8LMz+8vldu9l++mwu5Ma/z4TtlLm
Az3b1WfAUGzat+KRbsgWYxiw/8MH6++t6e88Gz9HwmmghDbVS37va3dPD51f/S/jL53Jfjb+XTX+
2P3ns/GnLwfVvBBRG1VrflRIhZl/O26/y46rxBzoSMDCBwCOsNZZCj1lDR5k3HhXAL/rI/+ImPbg
yCCmVfJbggCTcTOgz8SBydVYrchkgy+DDeFkkBafOrQgTFkQmWihWyXa0SkUiBmHVqsRX852Y/ox
AkkU3NTYrRPUe+TnJl1eX8QLdhgS7TmOjOguw3k+ks3RDdB14dYCr/EehowD7mIaEHQ0cLWFSBak
CwnqM4msbZ6LRmKLLBomSbBtFXK+SUWr0+WU1RSTdIoVjrBy0w7qzvf8zKJFy/0cAph/tWglgqzn
2Ju9pkKKe+ZWQHuL4NtkEJBpvVSJgeRc9oMY+OYAmp3Rt/c0osTqiLIkEZw4Y/KnQuG/aqOvSInp
65g2shxJDLVjT/u2F6U9gWcfYGUvjszt3MHAmO9zwNAAchSeNidcjccSxYxOZcHL1/eMqsx4+aSo
+eJsVRpdf72ml3luCwGQjyVDNhS+pr4A0bAkpcFAVAsBNx+MHVPQRDMDw0PtaJSIjF5Odg7e4EF9
+VmDOEeAwfZIXzhza8EnqkWuFk9fzQA4NavTj7KKHdHXhZyiccEDT3LcFCwbcmWKYNwORdwu42X1
kMmcE8+Sq5JQ/qu6JSvPNo9xfl+nAooGOXX7o+gqdt7Bjmxx/yDWRvUUbtz/bC0yGiJvFkrfjxqE
gE3Ar1kqXqYOgRKzmthp3QcFVA9IPjk5x/v6rH0ZbvMwH2EUpoEGp6GhExdvXO2+xHM8bgeu0JZh
I13Da8S/PEjYbpAMvbapF/FK2o3T1mkx9cC6bkEIdAeDMZDNbVzKo9TA0SSTkxTr5IFVwkxDfCIk
MMEpqc7XbyUlfQQ7GIDMbnXhiqpAXIJkOvfJCX80qEjqcAkliEslfHgkwJqR1wzNqGjWKKIb1Xuh
F2pCk80lSP/zTAnxyI455z0ECRFTjjVfXZROcP97qL7bYg7VGBc909qU7aTnhWGl4Sb2iuOYNT7V
zDqAmyZUDTN46dvWj7/Q+PhpG5RK5xxmqgGQgYvtIedGuBUvvj+o12433uYiFatLpbHiuOHS3afP
quSDy/JMdqLubDszXAL3HC4F2I2ttHHhhPR00BG2odTQqjMRRKBPU719HUCFRe5w0fctzWx8Epgq
TevzFPjzbHP0BJOgwLBTj4h1u75Eq6YnoOxmDohZvaKf4hmW3esPYNxV0yCpCAF48YIh/it1NuaT
LblWzFDoSw/dXkCZjSgxJJNfiGcFsewPC6XRRLIDqXlmQCZ1dxZOM9nOwJZsAsBQgqHcAg8fJJ5l
nI6hp8FmA9Zd/q5gZpq1V/KAxE+XUkSfEDWN2nVlqi4Tu4SFdsggFffxYKkug6WKkwWYerPDgKH/
L4B8NNVMhMWpSOLrRE7jkRWcTNnzxQBvyx/gugZORj7yofdfxh8wNbbNo2/n/KwdPfVIcYpmb4ee
dapbSrAno1bAovEWWOPyxnCtfqt9ePOp8XX6UW+dGujxg7U1TxhZvX1z7S5Q7d7AF+67v+d+vXrj
/q31VVmlV4cgKDb6z9xFPnWK7otqYfHy0pXzC4sLS/L2c40EuFp9e+FydX55oVq+tFgtXKyWLvIH
f7Z6e/Xdeyt3rlbXVqqb91Z/9d23bq6v371/dX5+fe7O6rx6il+sv/PuW9e/F71ybX7lOl/sr++s
3LqtruT/XF+7enP19t0b97+XWZC3rpffCxd2K3zxPNCWRrY35x6mnwJJcHd1f/3e2vvvXj9/ae78
5bmL5y8uX5unl8LP+PP6jM99pDIwn5n5xSlsTWESSsnoBLIFpp3eNg5UOlVIOI6R1/nIZjLmbP0j
2erM8s/fev9Xa/furKzfWnt//u4HN27feufm+yvn1n61em995dzKvZu3/umcX8Rz5y+du7j4Vmnd
q4uLAIjz9+pe9N+oOMqv/jLo2L/C7eYE/5u9w6VzF5cb7nCZ52F1pr9HyJb/H3XfkHJhNAEA
B64, OFFER_NEW_SHA256, 'offer HTML'); }

function bs_archive_html(): string { return bs_blob(<<<'B64'
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
lHZwTeVHSf40SNm/oAMPsej/BdYXrKvg4AAA
B64, ARCHIVE_SHA256, 'archive HTML'); }

function bs_desc_row(mysqli $db, string $table, int $id): ?array {
    $rows = bs_select($db,
        'SELECT information_id, language_id, title, description, meta_title, meta_description, meta_keyword'
        . ' FROM `' . $table . '` WHERE information_id = ? AND language_id = ?', 'ii', [$id, LANGUAGE_ID]);
    return $rows[0] ?? null;
}
function bs_desc_by_title(mysqli $db, string $table, string $title): ?array {
    $rows = bs_select($db,
        'SELECT information_id, language_id, title, description FROM `' . $table . '`'
        . ' WHERE language_id = ? AND title = ?', 'is', [LANGUAGE_ID, $title]);
    if (count($rows) > 1) bs_fail('Ambiguous information rows for title: ' . $title);
    return $rows[0] ?? null;
}
function bs_info_row(mysqli $db, string $table, int $id): ?array {
    $rows = bs_select($db, 'SELECT information_id, sort_order, status FROM `' . $table . '` WHERE information_id = ?', 'i', [$id]);
    return $rows[0] ?? null;
}
function bs_store_rows(mysqli $db, string $table, int $id): array {
    return bs_select($db, 'SELECT information_id, store_id FROM `' . $table . '` WHERE information_id = ?', 'i', [$id]);
}
function bs_has_default_store(array $rows): bool {
    foreach ($rows as $row) if ((int) $row['store_id'] === 0) return true;
    return false;
}
function bs_route_by_slug(mysqli $db, string $table): ?array {
    $rows = bs_select($db,
        'SELECT seo_url_id, store_id, language_id, `key`, value, keyword FROM `' . $table . '` WHERE keyword = ?',
        's', [ARCHIVE_SLUG]);
    if (count($rows) > 1) bs_fail('Archive SEO slug is ambiguous in ' . $table);
    return $rows[0] ?? null;
}
function bs_route_ok(?array $row, int $id): bool {
    return $row !== null
        && (int) $row['store_id'] === 0
        && (int) $row['language_id'] === LANGUAGE_ID
        && $row['key'] === 'information_id'
        && (string) $row['value'] === (string) $id;
}

function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));

    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found. Run this patch from ~/public_html.');

    bs_lint_self();
    require_once $config;

    // --- content gates, before any DB work -------------------------------
    $offerHtml   = bs_offer_html();
    $archiveHtml = bs_archive_html();
    bs_expect($offerHtml, '<h2>', 21, 'offer');
    bs_expect($offerHtml, 'Редакція від: <strong>07.08.2026</strong>', 1, 'offer');
    bs_expect($offerHtml, '<h2>7. Товари 3D-друку, декоративні та електричні вироби</h2>', 1, 'offer');
    bs_expect($offerHtml, 'publichna-oferta-arhiv-2026-07-24', 1, 'offer');
    bs_expect($offerHtml, 'publichna-oferta-arhiv-2026-05-26', 1, 'offer');
    bs_expect($archiveHtml, '<h2>', 19, 'archive');
    bs_expect($archiveHtml, 'Це архівна редакція Публічної оферти від 24 липня 2026 року.', 1, 'archive');
    // Back-link to the live offer. The bare URL also prefix-matches the
    // arhiv-2026-05-26 link in §19, so gate on the exact href attribute.
    bs_expect($archiveHtml, 'href="https://boostershop.website/information/publichna-oferta"', 1, 'archive');
    bs_expect($archiveHtml, 'publichna-oferta-arhiv-2026-05-26', 1, 'archive');
    bs_log('offer_h2', '21'); bs_log('archive_h2', '19');

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix    = (string) DB_PREFIX;
        $info      = bs_table($prefix, 'information');
        $desc      = bs_table($prefix, 'information_description');
        $infoStore = bs_table($prefix, 'information_to_store');
        $seo       = bs_table($prefix, 'seo_url');
        foreach ([$info, $desc, $infoStore, $seo] as $table) {
            if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        }
        bs_require_columns(bs_columns($db, $info), ['information_id', 'sort_order', 'status'], $info);
        $descColumns = bs_columns($db, $desc);
        bs_require_columns($descColumns, ['information_id','language_id','title','description','meta_title','meta_description','meta_keyword'], $desc);
        bs_require_columns(bs_columns($db, $infoStore), ['information_id', 'store_id'], $infoStore);
        $seoColumns = bs_columns($db, $seo);
        bs_require_columns($seoColumns, ['store_id', 'language_id', 'key', 'value', 'keyword'], $seo);

        $offer = bs_desc_row($db, $desc, OFFER_ID);
        if ($offer === null) bs_fail('Live offer row information_id=3 / language_id=4 not found');
        if ((string) $offer['title'] !== OFFER_TITLE) {
            bs_fail('information_id=3 title is "' . $offer['title'] . '", expected "' . OFFER_TITLE . '" — refusing to write');
        }
        $offerSha = hash('sha256', (string) $offer['description']);
        bs_log('live_offer_sha256', $offerSha);

        // Mirror reference: log the 26.05.2026 archive's meta pattern for review.
        $mirror = bs_desc_row($db, $desc, MIRROR_ID);
        if ($mirror !== null) {
            $mirrorMatches = (string) $mirror['meta_title'] === (string) $mirror['title']
                && (string) $mirror['meta_description'] === ''
                && (string) $mirror['meta_keyword'] === '';
            bs_log('mirror_id6_meta_pattern', $mirrorMatches ? 'meta_title=title, meta_desc="", meta_keyword="" (as expected)' : 'DIFFERS from the 2026-08-05 backup — new row still uses the documented pattern');
        } else {
            bs_log('mirror_id6', 'not found — new row uses the documented pattern');
        }

        $archive     = bs_desc_by_title($db, $desc, ARCHIVE_TITLE);
        $archiveId   = $archive === null ? 0 : (int) $archive['information_id'];
        $archiveInfo = $archiveId > 0 ? bs_info_row($db, $info, $archiveId) : null;
        $archiveStore= $archiveId > 0 ? bs_store_rows($db, $infoStore, $archiveId) : [];
        $route       = bs_route_by_slug($db, $seo);

        $offerDone   = $offerSha === OFFER_NEW_SHA256;
        $archiveDone = $archive !== null
            && hash('sha256', (string) $archive['description']) === ARCHIVE_SHA256
            && $archiveInfo !== null && (int) $archiveInfo['status'] === 1
            && bs_has_default_store($archiveStore)
            && bs_route_ok($route, $archiveId);
        $metaDone    = (string) $offer['meta_description'] === OFFER_META_DESC;

        if ($offerDone && $archiveDone && $metaDone) {
            bs_log('already_applied', 'yes');
            bs_log('archive_information_id', (string) $archiveId);
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }

        if (!$offerDone && $offerSha !== OFFER_PREV_SHA256) {
            bs_fail('Live offer body matches neither the expected 24.07.2026 edition (' . OFFER_PREV_SHA256
                . ') nor the new 07.08.2026 edition. Refusing to archive text that is not what is live. '
                . 'Re-diff the live row against handoffs/offer_html_20260724.html and regenerate this patch.');
        }
        if ($offerDone && $archive === null) {
            bs_log('warning', 'offer body is already the 07.08.2026 edition; the 24.07.2026 precondition could not be re-verified, archiving the embedded body');
        }
        if ($archive !== null && hash('sha256', (string) $archive['description']) !== ARCHIVE_SHA256) {
            bs_fail('An information row titled "' . ARCHIVE_TITLE . '" already exists with a different body — resolve manually');
        }
        if ($route !== null && $archiveId > 0 && !bs_route_ok($route, $archiveId)) {
            bs_fail('SEO slug ' . ARCHIVE_SLUG . ' is already routed elsewhere (value=' . $route['value'] . ')');
        }
        if ($route !== null && $archive === null) {
            bs_fail('SEO slug ' . ARCHIVE_SLUG . ' already exists but no archive page does — resolve manually');
        }

        bs_json_backup($backupDir, 'live_offer_before', [
            'table' => $desc, 'row' => $offer, 'description_sha256' => $offerSha,
        ]);
        bs_json_backup($backupDir, 'archive_before', [
            'information_table' => $info, 'description_table' => $desc,
            'information_to_store_table' => $infoStore, 'seo_table' => $seo,
            'archive_description_row' => $archive, 'archive_information_row' => $archiveInfo,
            'archive_store_rows' => $archiveStore, 'archive_seo_route' => $route,
            'mirror_id6_row' => $mirror,
        ]);

        $createdInformationId = 0;
        $createdSeoUrlId      = 0;

        $db->begin_transaction();
        try {
            if (!$offerDone || !$metaDone) {
                $stmt = $db->prepare('UPDATE `' . $desc . '` SET description = ?, meta_description = ?'
                    . ' WHERE information_id = ? AND language_id = ?');
                $id = OFFER_ID; $lang = LANGUAGE_ID; $meta = OFFER_META_DESC;
                $stmt->bind_param('ssii', $offerHtml, $meta, $id, $lang);
                $stmt->execute(); $stmt->close();
                bs_log('updated_offer', 'description+meta_description');
            }

            if ($archive === null) {
                $db->query('INSERT INTO `' . $info . '` (`sort_order`, `status`) VALUES (0, 1)');
                $createdInformationId = (int) $db->insert_id;
                if ($createdInformationId < 1) bs_fail('Archive information insert returned no id');

                $stmt = $db->prepare('INSERT INTO `' . $desc . '`'
                    . ' (`information_id`, `language_id`, `title`, `description`, `meta_title`, `meta_description`, `meta_keyword`)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?)');
                $lang = LANGUAGE_ID; $title = ARCHIVE_TITLE; $metaTitle = ARCHIVE_TITLE; $empty = '';
                $stmt->bind_param('iisssss', $createdInformationId, $lang, $title, $archiveHtml, $metaTitle, $empty, $empty);
                $stmt->execute(); $stmt->close();

                $stmt = $db->prepare('INSERT INTO `' . $infoStore . '` (`information_id`, `store_id`) VALUES (?, 0)');
                $stmt->bind_param('i', $createdInformationId);
                $stmt->execute(); $stmt->close();

                $withSort = isset($seoColumns['sort_order']);
                $sql = $withSort
                    ? 'INSERT INTO `' . $seo . '` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES (0, ?, \'information_id\', ?, ?, 0)'
                    : 'INSERT INTO `' . $seo . '` (`store_id`, `language_id`, `key`, `value`, `keyword`) VALUES (0, ?, \'information_id\', ?, ?)';
                $stmt = $db->prepare($sql);
                $value = (string) $createdInformationId; $slug = ARCHIVE_SLUG;
                $stmt->bind_param('iss', $lang, $value, $slug);
                $stmt->execute(); $stmt->close();
                $createdSeoUrlId = (int) $db->insert_id;

                $archiveId = $createdInformationId;
                bs_log('created_archive_information_id', (string) $createdInformationId);
                bs_log('created_archive_seo_url_id', (string) $createdSeoUrlId);
            } else {
                if ($archiveInfo === null) bs_fail('Archive description exists without its parent information row');
                if ((int) $archiveInfo['status'] !== 1) {
                    $db->query('UPDATE `' . $info . '` SET status = 1 WHERE information_id = ' . $archiveId);
                    bs_log('archive_status', 'enabled');
                }
                if (!bs_has_default_store($archiveStore)) {
                    $stmt = $db->prepare('INSERT INTO `' . $infoStore . '` (`information_id`, `store_id`) VALUES (?, 0)');
                    $stmt->bind_param('i', $archiveId); $stmt->execute(); $stmt->close();
                    bs_log('archive_store_mapping', 'created');
                }
            }

            // --- verify inside the transaction -------------------------------
            $verifyOffer = bs_desc_row($db, $desc, OFFER_ID);
            if ($verifyOffer === null || hash('sha256', (string) $verifyOffer['description']) !== OFFER_NEW_SHA256) {
                bs_fail('Live offer SHA-256 verification failed after write');
            }
            if ((string) $verifyOffer['meta_description'] !== OFFER_META_DESC) bs_fail('Offer meta_description verification failed');
            if ((string) $verifyOffer['title'] !== OFFER_TITLE) bs_fail('Offer title changed unexpectedly');

            $verifyArchive = bs_desc_by_title($db, $desc, ARCHIVE_TITLE);
            if ($verifyArchive === null || hash('sha256', (string) $verifyArchive['description']) !== ARCHIVE_SHA256) {
                bs_fail('Archive SHA-256 verification failed after write');
            }
            $verifyId    = (int) $verifyArchive['information_id'];
            $verifyInfo  = bs_info_row($db, $info, $verifyId);
            $verifyStore = bs_store_rows($db, $infoStore, $verifyId);
            $verifyRoute = bs_route_by_slug($db, $seo);
            if ($verifyInfo === null || (int) $verifyInfo['status'] !== 1) bs_fail('Archive information row verification failed');
            if (!bs_has_default_store($verifyStore)) bs_fail('Archive store mapping verification failed');
            if (!bs_route_ok($verifyRoute, $verifyId)) bs_fail('Archive SEO route verification failed');

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_json_backup($backupDir, 'created_ids', [
            'note' => 'Rollback: delete exactly these rows, then restore id=3 from live_offer_before.json',
            'created_information_id' => $createdInformationId,
            'created_seo_url_id'     => $createdSeoUrlId,
            'archive_information_id' => $archiveId,
            'archive_slug'           => ARCHIVE_SLUG,
        ]);

        bs_log('offer_sha256', OFFER_NEW_SHA256);
        bs_log('archive_sha256', ARCHIVE_SHA256);
        bs_log('archive_information_id', (string) $archiveId);
        bs_log('noindex_open_item', 'not implemented — no meta_robots column and no controller hook on the SEO route; see patch header');
        bs_log('done', 'ok');
        bs_self_delete();
    } finally {
        $db->close();
    }
}

try {
    bs_run();
} catch (Throwable $e) {
    bs_log('error', $e->getMessage());
    bs_log('done', 'failed');
    exit(1);
}