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
for rd17_dir in "$HOME/private" "$HOME/private/booster-pulls"; do
  [[ ! -L "$rd17_dir" ]] || stop linked_private_directory
  if [[ ! -e "$rd17_dir" ]]; then mkdir -m 700 -- "$rd17_dir"; fi
  [[ -d "$rd17_dir" ]] || stop private_directory_not_directory
done
rd17_archive="$HOME/private/booster-pulls/RD-17-checkout-source-20261026-r1.tar.gz"
rd17_manifest="$HOME/private/booster-pulls/RD-17-checkout-source-20261026-r1.manifest.txt"
[[ ! -e "$rd17_archive" && ! -L "$rd17_archive" && ! -e "$rd17_manifest" && ! -L "$rd17_manifest" ]] || stop artifact_exists_no_replay
set -C
rd17_files=(
catalog/controller/account/edit.php
catalog/view/template/account/edit.twig
catalog/controller/account/login.php
catalog/controller/checkout/checkout.php
catalog/controller/checkout/confirm.php
catalog/controller/checkout/receiver.php
catalog/controller/checkout/shipping_address.php
catalog/controller/checkout/payment_address.php
catalog/controller/checkout/register.php
catalog/controller/checkout/payment_method.php
catalog/controller/checkout/shipping_method.php
catalog/controller/checkout/success.php
catalog/view/javascript/checkout-reskin.js
catalog/view/javascript/checkout-state.js
catalog/view/javascript/acc009-checkout.js
system/library/booster_checkout_readiness.php
catalog/view/template/checkout/checkout.twig
catalog/view/template/checkout/register.twig
catalog/view/template/checkout/shipping_address.twig
catalog/view/template/checkout/payment_address.twig
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig
extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form.twig
extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form_register.twig
extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig
)
rd17_hash_files=(
catalog/controller/account/address.php
catalog/view/template/account/address.twig
catalog/view/template/account/address_list.twig
catalog/view/template/account/address_form.twig
extension/ukrainian/catalog/language/uk-ua/account/address.php
catalog/controller/account/shell.php
catalog/view/template/account/shell.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
catalog/view/javascript/booster-account-universal.js
catalog/view/javascript/booster-account-profile.js
system/library/booster_account_profile.php
system/library/booster_custom_field_guard.php
catalog/model/account/address.php
catalog/model/account/customer.php
catalog/model/account/custom_field.php
system/library/cart/customer.php
catalog/view/javascript/common.js
catalog/view/stylesheet/stylesheet.css
catalog/view/stylesheet/bootstrap.css
system/library/template/twig.php
system/engine/loader.php
catalog/controller/event/theme.php
catalog/model/design/theme.php
catalog/controller/common/header.php
catalog/view/template/common/footer.twig
extension/ukrainian/catalog/language/uk-ua/account/account.php
catalog/model/checkout/order.php
adminEvhenii/model/sale/order.php
adminEvhenii/controller/sale/order.php
adminEvhenii/view/template/sale/order_info.twig
)
rd17_total=0
for rd17_file in "${rd17_files[@]}"; do
  rd17_walk=.
  IFS=/ read -r -a rd17_parts <<< "$rd17_file"
  for rd17_part in "${rd17_parts[@]}"; do
    rd17_walk="$rd17_walk/$rd17_part"
    [[ ! -L "$rd17_walk" ]] || stop linked_source_component
  done
  [[ -f "$rd17_file" ]] || stop required_source_missing
  rd17_size=$(stat -c %s -- "$rd17_file")
  [[ "$rd17_size" =~ ^[0-9]+$ ]] || stop bad_source_size
  (( rd17_size > 0 && rd17_size <= 2097152 )) || stop source_size_cap
  rd17_total=$((rd17_total + rd17_size))
  (( rd17_total <= 8388608 )) || stop total_source_cap
  # This is an obvious-secret screen, not a guarantee that source has no secrets.
  if grep -Eiq -- '-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|sk-ant-(api|oat)-[A-Za-z0-9_-]{20,}|sk-proj-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}' "$rd17_file"; then
    stop obvious_secret_signature
  else
    rd17_grep=$?
    [[ "$rd17_grep" == 1 ]] || stop secret_screen_failed
  fi
done
# Hash-only identities: never archived or printed as source bodies.
for rd17_file in "${rd17_hash_files[@]}"; do
  rd17_walk=.
  IFS=/ read -r -a rd17_parts <<< "$rd17_file"
  for rd17_part in "${rd17_parts[@]}"; do
    rd17_walk="$rd17_walk/$rd17_part"
    [[ ! -L "$rd17_walk" ]] || stop linked_hash_component
  done
  [[ -f "$rd17_file" ]] || stop hash_source_missing
  rd17_size=$(stat -c %s -- "$rd17_file")
  [[ "$rd17_size" =~ ^[0-9]+$ ]] || stop hash_source_size
  (( rd17_size > 0 && rd17_size <= 2097152 )) || stop hash_source_size
done
rd17_dependencies_before=$(sha256sum -- "${rd17_hash_files[@]}")
rd17_before=$(sha256sum -- "${rd17_files[@]}")
rd17_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
rd17_php=$(command -v php) || stop php_missing
[[ "$rd17_php" == /* && -f "$rd17_php" && -x "$rd17_php" ]] || stop php_path_invalid
rd17_version=$("$rd17_php" -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
[[ "$rd17_version" == 8.0 ]] || stop php_not_8_0
# Require config only within owner-run PHP; never export it or print credentials.
# Schema is inspected before fixed prepared SELECTs. No customer/business rows.
rd17_metadata=$("$rd17_php" -d display_errors=0 -d log_errors=0 <<'RD17_PHP'
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
    foreach (['checkout/checkout','checkout/register','checkout/shipping_address','checkout/payment_address','extension/PintaNovaPoshtaCod/shipping/checkout_shipping_address_form','extension/PintaNovaPoshtaCod/shipping/js_checkout_shipping_address_form'] as $route) {
        if (!$s->execute() || !$s->bind_result($total, $enabled) || !$s->fetch()) { throw new RuntimeException(); }
        $meta['theme'][$route] = ['rows_all_stores' => (int)$total, 'enabled_all_stores' => (int)$enabled];
        $s->free_result();
    }
    $s->close();
    $s = $db->prepare('SELECT `store_id`,`layout_id`,`route` FROM `' . DB_PREFIX . 'layout_route` WHERE ? LIKE `route` ORDER BY `store_id`,`route` DESC LIMIT 101');
    if (!$s || !$s->bind_param('s', $route)) { throw new RuntimeException(); }
    foreach (['checkout/checkout'] as $route) {
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
RD17_PHP
) || stop metadata_failed
tar -czf - -- "${rd17_files[@]}" > "$rd17_archive"
rd17_after=$(sha256sum -- "${rd17_files[@]}")
[[ "$rd17_before" == "$rd17_after" ]] || stop source_drift
rd17_dependencies_after=$(sha256sum -- "${rd17_hash_files[@]}")
[[ "$rd17_dependencies_before" == "$rd17_dependencies_after" ]] || stop dependency_drift
# Independently bind archive bytes to the source manifest without extraction.
rd17_index=0
while IFS= read -r rd17_line; do
  rd17_expected=${rd17_line:0:64}
  rd17_file=${rd17_files[$rd17_index]}
  [[ "$rd17_expected" =~ ^[0-9a-f]{64}$ && "${rd17_line:66}" == "$rd17_file" ]] || stop hash_format
  [[ "${rd17_line:64:2}" == '  ' || "${rd17_line:64:2}" == ' *' ]] || stop hash_format
  rd17_archived=$(tar -xzOf "$rd17_archive" -- "$rd17_file" | sha256sum)
  [[ "${rd17_archived:0:64}" == "$rd17_expected" ]] || stop archived_source_mismatch
  rd17_index=$((rd17_index + 1))
done <<< "$rd17_before"
[[ "$rd17_index" == "${#rd17_files[@]}" ]] || stop source_count_mismatch
rd17_archive_hash=$(sha256sum -- "$rd17_archive")
{
  printf 'task=RD-17-SUPPLEMENT\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$rd17_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#rd17_files[@]}" "$rd17_total"
  printf 'archive_sha256=%s\n' "${rd17_archive_hash:0:64}"
  printf 'metadata_json=%s\n' "$rd17_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$rd17_before"
  printf 'hash_only_files=%s\ndependency_hashes_begin\n%s\ndependency_hashes_end\ndependency_pre_post=ok\n' "${#rd17_hash_files[@]}" "$rd17_dependencies_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$rd17_manifest"
printf 'task=RD-17-SUPPLEMENT\nsource_files=%s\narchive_sha256=%s\nmanifest_sha256=' "${#rd17_files[@]}" "${rd17_archive_hash:0:64}"
rd17_manifest_hash=$(sha256sum -- "$rd17_manifest")
printf '%s\ndone=ok\n' "${rd17_manifest_hash:0:64}"
