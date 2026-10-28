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
for rd18_dir in "$HOME/private" "$HOME/private/booster-pulls"; do
  [[ ! -L "$rd18_dir" ]] || stop linked_private_directory
  if [[ ! -e "$rd18_dir" ]]; then mkdir -m 700 -- "$rd18_dir"; fi
  [[ -d "$rd18_dir" ]] || stop private_directory_not_directory
done
rd18_archive="$HOME/private/booster-pulls/RD-18-source-20261027-r1.tar.gz"
rd18_manifest="$HOME/private/booster-pulls/RD-18-source-20261027-r1.manifest.txt"
[[ ! -e "$rd18_archive" && ! -L "$rd18_archive" && ! -e "$rd18_manifest" && ! -L "$rd18_manifest" ]] || stop artifact_exists_no_replay
set -C
rd18_files=(
catalog/controller/account/order.php
catalog/view/template/account/order_list.twig
catalog/view/template/account/order_info.twig
catalog/view/template/account/order_history.twig
system/library/acc007_order_status.php
catalog/model/account/order.php
extension/ukrainian/catalog/language/uk-ua/account/order.php
catalog/controller/account/shell.php
catalog/view/template/account/shell.twig
extension/ukrainian/catalog/language/uk-ua/account/account.php
catalog/controller/account/account.php
catalog/view/template/account/account.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
catalog/view/javascript/common.js
catalog/view/stylesheet/stylesheet.css
catalog/view/stylesheet/bootstrap.css
system/library/template/twig.php
system/engine/loader.php
catalog/controller/event/theme.php
catalog/model/design/theme.php
catalog/model/design/layout.php
catalog/controller/common/column_left.php
catalog/view/template/common/column_left.twig
extension/opencart/catalog/controller/module/account.php
extension/opencart/catalog/view/template/module/account.twig
catalog/controller/common/header.php
catalog/view/template/common/footer.twig
catalog/controller/common/pagination.php
catalog/view/template/common/pagination.twig
)
rd18_total=0
for rd18_file in "${rd18_files[@]}"; do
  rd18_walk=.
  IFS=/ read -r -a rd18_parts <<< "$rd18_file"
  for rd18_part in "${rd18_parts[@]}"; do
    rd18_walk="$rd18_walk/$rd18_part"
    [[ ! -L "$rd18_walk" ]] || stop linked_source_component
  done
  [[ -f "$rd18_file" ]] || stop required_source_missing
  rd18_size=$(stat -c %s -- "$rd18_file")
  [[ "$rd18_size" =~ ^[0-9]+$ ]] || stop bad_source_size
  (( rd18_size > 0 && rd18_size <= 2097152 )) || stop source_size_cap
  rd18_total=$((rd18_total + rd18_size))
  (( rd18_total <= 8388608 )) || stop total_source_cap
  # This is an obvious-secret screen, not a guarantee that source has no secrets.
  if grep -Eiq -- '-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|sk-ant-(api|oat)-[A-Za-z0-9_-]{20,}|sk-proj-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}' "$rd18_file"; then
    stop obvious_secret_signature
  else
    rd18_grep=$?
    [[ "$rd18_grep" == 1 ]] || stop secret_screen_failed
  fi
done
rd18_before=$(sha256sum -- "${rd18_files[@]}")
rd18_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
rd18_php=$(command -v php) || stop php_missing
[[ "$rd18_php" == /* && -f "$rd18_php" && -x "$rd18_php" ]] || stop php_path_invalid
rd18_version=$("$rd18_php" -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
[[ "$rd18_version" == 8.0 ]] || stop php_not_8_0
# Require config only within owner-run PHP; never export it or print credentials.
# Schema is inspected before fixed prepared SELECTs. No customer/business rows.
rd18_metadata=$("$rd18_php" -d display_errors=0 -d log_errors=0 <<'RD18_PHP'
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
    foreach (['account/order','account/order_list','account/order_info','account/order_history','account/shell','account/account','common/header','common/footer','common/pagination'] as $route) {
        if (!$s->execute() || !$s->bind_result($total, $enabled) || !$s->fetch()) { throw new RuntimeException(); }
        $meta['theme'][$route] = ['rows_all_stores' => (int)$total, 'enabled_all_stores' => (int)$enabled];
        $s->free_result();
    }
    $s->close();
    $s = $db->prepare('SELECT `store_id`,`layout_id`,`route` FROM `' . DB_PREFIX . 'layout_route` WHERE ? LIKE `route` ORDER BY `store_id`,`route` DESC LIMIT 101');
    if (!$s || !$s->bind_param('s', $route)) { throw new RuntimeException(); }
    foreach (['account/order','account/order.info'] as $route) {
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
RD18_PHP
) || stop metadata_failed
tar -czf - -- "${rd18_files[@]}" > "$rd18_archive"
rd18_after=$(sha256sum -- "${rd18_files[@]}")
[[ "$rd18_before" == "$rd18_after" ]] || stop source_drift
# Independently bind archive bytes to the source manifest without extraction.
rd18_index=0
while IFS= read -r rd18_line; do
  rd18_expected=${rd18_line:0:64}
  rd18_file=${rd18_files[$rd18_index]}
  [[ "$rd18_expected" =~ ^[0-9a-f]{64}$ && "${rd18_line:66}" == "$rd18_file" ]] || stop hash_format
  [[ "${rd18_line:64:2}" == '  ' || "${rd18_line:64:2}" == ' *' ]] || stop hash_format
  rd18_archived=$(tar -xzOf "$rd18_archive" -- "$rd18_file" | sha256sum)
  [[ "${rd18_archived:0:64}" == "$rd18_expected" ]] || stop archived_source_mismatch
  rd18_index=$((rd18_index + 1))
done <<< "$rd18_before"
[[ "$rd18_index" == "${#rd18_files[@]}" ]] || stop source_count_mismatch
rd18_archive_hash=$(sha256sum -- "$rd18_archive")
{
  printf 'task=RD-18-WP0\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$rd18_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#rd18_files[@]}" "$rd18_total"
  printf 'archive_sha256=%s\n' "${rd18_archive_hash:0:64}"
  printf 'metadata_json=%s\n' "$rd18_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$rd18_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$rd18_manifest"
printf 'task=RD-18-WP0\nsource_files=%s\narchive_sha256=%s\nmanifest_sha256=' "${#rd18_files[@]}" "${rd18_archive_hash:0:64}"
rd18_manifest_hash=$(sha256sum -- "$rd18_manifest")
printf '%s\ndone=ok\n' "${rd18_manifest_hash:0:64}"
