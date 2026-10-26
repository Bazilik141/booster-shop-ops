#!/usr/bin/env bash
# Owner-run only. Read fixed public source files and technical metadata.
# No source/DB/cache writes. Creates only two task-unique private pull artifacts.
set +x
set -euo pipefail
export LC_ALL=C
umask 077
stop() { printf 'STOP=%s\n' "$1" >&2; exit 1; }
cd "$HOME/public_html" || stop webroot_missing
[[ ! -L "$HOME/public_html" ]] || stop linked_webroot
[[ $(pwd -P) == "$HOME/public_html" ]] || stop unexpected_webroot
for rd16_dir in "$HOME/private" "$HOME/private/booster-pulls"; do
  [[ ! -L "$rd16_dir" ]] || stop linked_private_directory
  if [[ ! -e "$rd16_dir" ]]; then mkdir -m 700 -- "$rd16_dir"; fi
  [[ -d "$rd16_dir" ]] || stop private_directory_not_directory
done
rd16_archive="$HOME/private/booster-pulls/RD-16-menu-20261025-r1.tar.gz"
rd16_manifest="$HOME/private/booster-pulls/RD-16-menu-20261025-r1.manifest.txt"
[[ ! -e "$rd16_archive" && ! -L "$rd16_archive" && ! -e "$rd16_manifest" && ! -L "$rd16_manifest" ]] || stop artifact_exists_no_replay
set -C
rd16_files=(
extension/ciaccountsidebar/catalog/controller/module/account_sidebar.php
extension/ciaccountsidebar/catalog/view/template/module/account_sidebar.twig
extension/ciaccountsidebar/catalog/view/stylesheet/accountdashboard/sidebar.css
)
# Current owner drop pins: check these 34 files, but never re-export them.
rd16_context_check() {
  while IFS= read -r rd16_context_line; do
    rd16_context_expected=${rd16_context_line:0:64}
    rd16_context_file=${rd16_context_line:66}
    rd16_context_walk=.
    IFS=/ read -r -a rd16_context_parts <<< "$rd16_context_file"
    for rd16_context_part in "${rd16_context_parts[@]}"; do
      rd16_context_walk="$rd16_context_walk/$rd16_context_part"
      [[ ! -L "$rd16_context_walk" ]] || stop linked_context_component
    done
    [[ -f "$rd16_context_file" ]] || stop context_source_missing
    rd16_context_hash=$(sha256sum -- "$rd16_context_file")
    [[ ${rd16_context_hash:0:64} == "$rd16_context_expected" ]] || stop context_source_drift
  done <<'RD16_CONTEXT'
58e9054bdc38281c2d8bdad2797e514cd9b172d4a048a19c6c92a79a98014443  catalog/controller/account/account.php
91a358589fa318df92c9fff8fea6ddd949479f008556a5c15f9750dc01b9dc54  catalog/controller/account/edit.php
05d7de4ef69585cb5422af9a0badc4f0fa5bef4cd0b33cbc272a39bd00078352  catalog/controller/account/order.php
8f5eea4d22f271ee2cd502cbcdadd650f1abb657d3ec1a8fa10214595c3e24c1  catalog/controller/account/wishlist.php
1dcf4b069ae0908026d6943727ba1ae28da2590592bd2d96cd106ed685682936  catalog/controller/account/logout.php
a10392f962ab4d92b67f9f97a6d56ec3341940432486ff012dd5e626027795f5  catalog/model/account/address.php
06f53290fffb7d86e3f696194912156456329f20a46cb49c51173282a5896797  catalog/model/account/customer.php
47f5334b0502f1967e7963bd03a2d3d764a6bceafe7b194634615cb5fa7ffe90  catalog/model/account/custom_field.php
2862fd9d2a5f756fd5c58d2ecfc71df705799a3bf3f2cef24a69af867c127930  catalog/model/account/order.php
bb4de1f2df67bf0ab96ea034084152975150675adfebb2075068eabd1bddb992  catalog/model/account/wishlist.php
4fa4c2d1b1939a69312ee0a53b06e1a8a93fc1671cba103aefe7c5756b47d173  system/library/cart/customer.php
9086eff917ea2003bdad786d0ddaa680f06cb702b9af1567c30c3916e6ca6885  system/library/acc007_order_status.php
5089e0044ac50d101c6fc07300abce625baf0303f35e17dfbb6dc0a200bbe597  system/library/booster_account_profile.php
92a6ac0af6ff88053ecafe0acd86e249bddaab664080a549c0a502179f537a69  system/library/template/twig.php
ea2098450ddc567747e2d89001d997ce921e966a313cafb680a308ee369a4723  system/engine/loader.php
0ea789a4553ea11ec82e3e5861a9693e826da682702aa63c80a70622d0eb0b33  catalog/controller/event/theme.php
32b306e1700b7e636617e0439208764c09d129103260fa96980f970c523f1044  catalog/model/design/theme.php
42ef7ab2d885063ec905f49b5d0863fe74d08174147cf177c8a06e735ea6831d  catalog/model/design/layout.php
7afddc2922cb0c8c8c231b5ce24450d7d64a427d33a861474fafaae1e2a61fa3  catalog/controller/common/column_left.php
60b914a9b2c483323883913a6050a805e55fdd05fa3a6bc46330993bc3e5b181  catalog/view/template/common/column_left.twig
64a96e2fe537887a2de906075bad8dd9862f04c9b965eeb79745cfacde01a6dd  catalog/controller/common/header.php
4f202948f7cd377424b40cae8682bf3b6f8f0802ee17775fd6c72dd42e5cbc56  catalog/view/template/common/header.twig
df5a5ca70bdfdd63a2f709a188593d88c3f1f7d087d9d09ae008ee0fd05f91db  catalog/view/template/common/footer.twig
8f8ae6e2b315b13def1e937f18b84975bfc4f6f2792637e4113b87752b857ed6  catalog/view/template/account/account.twig
69b27f6192de5b2dbe1d46604e69cce1f2ab0d9be855aa8fdb8a465b4645f476  catalog/view/template/account/edit.twig
6a36179518eb8e16eb105c052556570a373a581d072a187daf41fc88450150d9  catalog/view/javascript/booster-account-profile.js
2d9ca8f133bb5e8d9f7c938a8e92d3db86d01edcedb15261525a5353a08ac368  catalog/view/javascript/booster-account-universal.js
c5af44b72b362a8090b2f5699d41f06d0e2d1663ca9b82173c66d9d67413af0d  catalog/view/stylesheet/boostershop-ds.css
02135431f716403cfb4a9bafb37bf367c242d15a267b2de8268cb3b90546eacd  catalog/view/stylesheet/stylesheet.css
3e6b85215061cd33fc02676958ee706c0e96b877893c244bff473ce4fb9ecc57  catalog/view/stylesheet/bootstrap.css
f1933ecf6f491921945152e03fd390a53eddd36773f09d6d087c002afb3ae597  extension/ukrainian/catalog/language/uk-ua/account/account.php
710e41700410dbd8cb14a2985c3d2f85deb106478a53a72737c5c39fe66c6d79  extension/ukrainian/catalog/language/uk-ua/account/edit.php
afe050cdd7151042c3d11fddd16fc6fb10f7780a8e693ba3d14b456f43008964  extension/opencart/catalog/controller/module/account.php
241a214bc0c5b872c7fa1c308a90bf9629697675395488dd617ef116d3e7dcc0  extension/opencart/catalog/view/template/module/account.twig
RD16_CONTEXT
}
rd16_context_check
rd16_total=0
for rd16_file in "${rd16_files[@]}"; do
  rd16_walk=.
  IFS=/ read -r -a rd16_parts <<< "$rd16_file"
  for rd16_part in "${rd16_parts[@]}"; do
    rd16_walk="$rd16_walk/$rd16_part"
    [[ ! -L "$rd16_walk" ]] || stop linked_source_component
  done
  [[ -f "$rd16_file" ]] || stop required_source_missing
  rd16_size=$(stat -c %s -- "$rd16_file")
  [[ "$rd16_size" =~ ^[0-9]+$ ]] || stop bad_source_size
  (( rd16_size > 0 && rd16_size <= 2097152 )) || stop source_size_cap
  rd16_total=$((rd16_total + rd16_size))
  (( rd16_total <= 8388608 )) || stop total_source_cap
  # This is an obvious-secret screen, not a guarantee that source has no secrets.
  if grep -Eiq -- '-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|sk-ant-(api|oat)-[A-Za-z0-9_-]{20,}|sk-proj-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}' "$rd16_file"; then
    stop obvious_secret_signature
  else
    rd16_grep=$?
    [[ "$rd16_grep" == 1 ]] || stop secret_screen_failed
  fi
done
rd16_before=$(sha256sum -- "${rd16_files[@]}")
rd16_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
rd16_php=$(command -v php) || stop php_missing
[[ "$rd16_php" == /* && -f "$rd16_php" && -x "$rd16_php" ]] || stop php_path_invalid
rd16_version=$("$rd16_php" -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
[[ "$rd16_version" == 8.0 ]] || stop php_not_8_0
# Require config only within owner-run PHP; never export it or print credentials.
# Schema is inspected before fixed prepared SELECTs. No customer/business rows.
rd16_metadata=$("$rd16_php" -d display_errors=0 -d log_errors=0 <<'RD16_PHP'
<?php
ob_start();
set_error_handler(static function () { throw new RuntimeException('metadata_error'); });
try {
    if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 0) { throw new RuntimeException(); }
    if (is_link('config.php') || !is_file('config.php')) { throw new RuntimeException(); }
    require './config.php';
    if (!preg_match('/^[A-Za-z0-9_]+$/D', DB_PREFIX)) { throw new RuntimeException(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) DB_PORT);
    if ($db->connect_errno || !$db->set_charset('utf8mb4')) { throw new RuntimeException(); }
    foreach (['theme' => ['store_id','route','status'], 'layout_route' => ['store_id','route','layout_id'], 'layout_module' => ['layout_id','code','position','sort_order']] as $table => $needed) {
        $q = $db->query('SHOW COLUMNS FROM `' . DB_PREFIX . $table . '`');
        if (!$q) { throw new RuntimeException(); }
        $columns = [];
        while ($r = $q->fetch_assoc()) { $columns[] = $r['Field']; }
        $q->free();
        if (array_diff($needed, $columns)) { throw new RuntimeException(); }
    }
    if (!$db->query('START TRANSACTION READ ONLY')) { throw new RuntimeException(); }
    $meta = ['theme' => [], 'layout_candidates' => [], 'layout_modules' => [], 'limits' => 'all stores; route candidates only; default layout, events and active module settings not established'];
    $route = '';
    $s = $db->prepare('SELECT COUNT(*), COALESCE(SUM(`status` = 1),0) FROM `' . DB_PREFIX . 'theme` WHERE `route` = ?');
    if (!$s || !$s->bind_param('s', $route)) { throw new RuntimeException(); }
    foreach (['account/account','account/edit','common/header','common/column_left','common/footer','extension/opencart/module/account','extension/ciaccountsidebar/module/account_sidebar'] as $route) {
        if (!$s->execute() || !$s->bind_result($total, $enabled) || !$s->fetch()) { throw new RuntimeException(); }
        $meta['theme'][$route] = ['rows_all_stores' => (int)$total, 'enabled_all_stores' => (int)$enabled];
        $s->free_result();
    }
    $s->close();
    $s = $db->prepare('SELECT `store_id`,`layout_id`,`route` FROM `' . DB_PREFIX . 'layout_route` WHERE ? LIKE `route` ORDER BY `store_id`,`route` DESC LIMIT 101');
    if (!$s || !$s->bind_param('s', $route)) { throw new RuntimeException(); }
    foreach (['account/account','account/edit'] as $route) {
        if (!$s->execute() || !$s->bind_result($store, $layout, $pattern)) { throw new RuntimeException(); }
        $rows = [];
        while ($s->fetch()) {
            if (count($rows) >= 100 || !preg_match('~^[A-Za-z0-9_/%.-]{1,128}$~D', $pattern)) { throw new RuntimeException(); }
            $rows[] = ['store_id' => (int)$store, 'layout_id' => (int)$layout, 'route' => $pattern];
        }
        if ($s->errno) { throw new RuntimeException(); }
        $s->free_result();
        $meta['layout_candidates'][$route] = $rows;
    }
    $s->close();
    $ids = [];
    foreach ($meta['layout_candidates'] as $rows) { foreach ($rows as $r) { $ids[$r['layout_id']] = true; } }
    if (count($ids) > 32) { throw new RuntimeException(); }
    $s = $db->prepare('SELECT `code`,`position`,`sort_order` FROM `' . DB_PREFIX . 'layout_module` WHERE `layout_id` = ? ORDER BY `sort_order` LIMIT 101');
    $layout = 0;
    if (!$s || !$s->bind_param('i', $layout)) { throw new RuntimeException(); }
    foreach (array_keys($ids) as $layout) {
        if (!$s->execute() || !$s->bind_result($code, $position, $sort)) { throw new RuntimeException(); }
        $rows = [];
        while ($s->fetch()) {
            if (count($rows) >= 100 || !preg_match('~^[A-Za-z0-9_./-]{1,128}$~D', $code) || !in_array($position, ['column_left','column_right','content_top','content_bottom'], true)) { throw new RuntimeException(); }
            $rows[] = ['code' => $code, 'position' => $position, 'sort_order' => (int)$sort];
        }
        if ($s->errno) { throw new RuntimeException(); }
        $s->free_result();
        $meta['layout_modules'][(string)$layout] = $rows;
    }
    $s->close();
    $db->rollback();
    $db->close();
    $meta['php'] = PHP_VERSION;
    $meta['template_cache_directory'] = defined('DIR_CACHE') ? DIR_CACHE . 'template/' : 'UNDEFINED';
    $json = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    ob_end_clean();
    echo $json;
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    fwrite(STDERR, "STOP=technical_metadata_failed_no_details\n");
    exit(1);
}
RD16_PHP
) || stop metadata_failed
tar -czf - -- "${rd16_files[@]}" > "$rd16_archive"
rd16_after=$(sha256sum -- "${rd16_files[@]}")
[[ "$rd16_before" == "$rd16_after" ]] || stop source_drift
rd16_context_check
# Independently bind archive bytes to the source manifest without extraction.
rd16_index=0
while IFS= read -r rd16_line; do
  rd16_expected=${rd16_line:0:64}
  rd16_file=${rd16_files[$rd16_index]}
  [[ "$rd16_expected" =~ ^[0-9a-f]{64}$ && "${rd16_line:66}" == "$rd16_file" ]] || stop hash_format
  [[ "${rd16_line:64:2}" == '  ' || "${rd16_line:64:2}" == ' *' ]] || stop hash_format
  rd16_archived=$(tar -xzOf "$rd16_archive" -- "$rd16_file" | sha256sum)
  [[ "${rd16_archived:0:64}" == "$rd16_expected" ]] || stop archived_source_mismatch
  rd16_index=$((rd16_index + 1))
done <<< "$rd16_before"
[[ "$rd16_index" == "${#rd16_files[@]}" ]] || stop source_count_mismatch
rd16_archive_hash=$(sha256sum -- "$rd16_archive")
{
  printf 'task=RD-16-WP0-MENU\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$rd16_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#rd16_files[@]}" "$rd16_total"
  printf 'archive_sha256=%s\n' "${rd16_archive_hash:0:64}"
  printf 'metadata_json=%s\n' "$rd16_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$rd16_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\ncontext_sources=34\ncontext_pre_post=ok\nproduction_writes=0\ndone=ok\n'
} > "$rd16_manifest"
printf 'task=RD-16-WP0-MENU\nsource_files=%s\narchive_sha256=%s\nmanifest_sha256=' "${#rd16_files[@]}" "${rd16_archive_hash:0:64}"
rd16_manifest_hash=$(sha256sum -- "$rd16_manifest")
printf '%s\ndone=ok\n' "${rd16_manifest_hash:0:64}"
