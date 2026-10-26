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
rd16_archive="$HOME/private/booster-pulls/RD-16-source-20261025-r1.tar.gz"
rd16_manifest="$HOME/private/booster-pulls/RD-16-source-20261025-r1.manifest.txt"
[[ ! -e "$rd16_archive" && ! -L "$rd16_archive" && ! -e "$rd16_manifest" && ! -L "$rd16_manifest" ]] || stop artifact_exists_no_replay
set -C
rd16_files=(
catalog/controller/account/account.php
catalog/controller/account/edit.php
catalog/controller/account/order.php
catalog/controller/account/wishlist.php
catalog/controller/account/logout.php
catalog/model/account/address.php
catalog/model/account/customer.php
catalog/model/account/custom_field.php
catalog/model/account/order.php
catalog/model/account/wishlist.php
system/library/cart/customer.php
system/library/acc007_order_status.php
system/library/booster_account_profile.php
system/library/template/twig.php
system/engine/loader.php
catalog/controller/event/theme.php
catalog/model/design/theme.php
catalog/model/design/layout.php
catalog/controller/common/column_left.php
catalog/view/template/common/column_left.twig
catalog/controller/common/header.php
catalog/view/template/common/header.twig
catalog/view/template/common/footer.twig
catalog/view/template/account/account.twig
catalog/view/template/account/edit.twig
catalog/view/javascript/booster-account-profile.js
catalog/view/javascript/booster-account-universal.js
catalog/view/stylesheet/boostershop-ds.css
catalog/view/stylesheet/stylesheet.css
catalog/view/stylesheet/bootstrap.css
extension/ukrainian/catalog/language/uk-ua/account/account.php
extension/ukrainian/catalog/language/uk-ua/account/edit.php
extension/opencart/catalog/controller/module/account.php
extension/opencart/catalog/view/template/module/account.twig
)
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
    foreach (['account/account','account/edit','common/header','common/column_left','common/footer','extension/opencart/module/account'] as $route) {
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
  printf 'task=RD-16-WP0\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$rd16_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#rd16_files[@]}" "$rd16_total"
  printf 'archive_sha256=%s\n' "${rd16_archive_hash:0:64}"
  printf 'metadata_json=%s\n' "$rd16_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$rd16_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$rd16_manifest"
printf 'task=RD-16-WP0\nsource_files=%s\narchive_sha256=%s\nmanifest_sha256=' "${#rd16_files[@]}" "${rd16_archive_hash:0:64}"
rd16_manifest_hash=$(sha256sum -- "$rd16_manifest")
printf '%s\ndone=ok\n' "${rd16_manifest_hash:0:64}"
