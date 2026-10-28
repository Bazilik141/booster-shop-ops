#!/usr/bin/env bash
# ACC-006 visual step WP0. Owner-run only. Read fixed public source files, two
# path/line inventories and theme-override counts. No source/DB/cache writes.
# Creates only three task-unique private pull artifacts.
set +x
set -euo pipefail
export LC_ALL=C
umask 077
stop() { printf 'STOP=%s\n' "$1" >&2; exit 1; }
cd "$HOME/public_html" || stop webroot_missing
[[ ! -L "$HOME/public_html" ]] || stop linked_webroot
[[ $(pwd -P) == "$HOME/public_html" ]] || stop unexpected_webroot
for a6_dir in "$HOME/private" "$HOME/private/booster-pulls"; do
  [[ ! -L "$a6_dir" ]] || stop linked_private_directory
  if [[ ! -e "$a6_dir" ]]; then mkdir -m 700 -- "$a6_dir"; fi
  [[ -d "$a6_dir" ]] || stop private_directory_not_directory
done
a6_base="$HOME/private/booster-pulls/ACC-006-visual-source-20261028-r1"
a6_archive="$a6_base.tar.gz"
a6_manifest="$a6_base.manifest.txt"
a6_inventory="$a6_base.inventory.txt"
for a6_out in "$a6_archive" "$a6_manifest" "$a6_inventory"; do
  [[ ! -e "$a6_out" && ! -L "$a6_out" ]] || stop artifact_exists_no_replay
done
set -C
a6_required=(
catalog/view/template/product/thumb.twig
catalog/view/template/product/product.twig
catalog/view/template/account/wishlist.twig
catalog/view/template/account/wishlist_list.twig
catalog/view/template/account/account.twig
catalog/view/template/account/shell.twig
catalog/view/template/common/header.twig
catalog/view/javascript/booster-wishlist.js
catalog/view/stylesheet/boostershop-ds.css
catalog/controller/account/wishlist.php
catalog/controller/account/shell.php
catalog/controller/account/account.php
catalog/controller/common/header.php
catalog/model/account/wishlist.php
extension/ukrainian/catalog/language/uk-ua/account/wishlist.php
extension/ukrainian/catalog/language/uk-ua/account/account.php
extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php
)
a6_optional=(
catalog/controller/product/thumb.php
catalog/controller/product/product.php
extension/ukrainian/catalog/language/uk-ua/common/header.php
extension/ukrainian/catalog/language/uk-ua/product/product.php
extension/ukrainian/catalog/language/uk-ua/product/thumb.php
extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php
)
a6_check() {
  local f=$1 walk=. part
  IFS=/ read -r -a a6_parts <<< "$f"
  for part in "${a6_parts[@]}"; do
    walk="$walk/$part"
    [[ ! -L "$walk" ]] || stop linked_source_component
  done
  [[ -f "$f" ]] || return 1
  local size
  size=$(stat -c %s -- "$f")
  [[ "$size" =~ ^[0-9]+$ ]] || stop bad_source_size
  (( size > 0 && size <= 2097152 )) || stop source_size_cap
  a6_total=$((a6_total + size))
  (( a6_total <= 8388608 )) || stop total_source_cap
  # Obvious-secret screen only, not a guarantee that source has no secrets.
  if grep -Eiq -- '-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|sk-ant-(api|oat)-[A-Za-z0-9_-]{20,}|sk-proj-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16}' "$f"; then
    stop obvious_secret_signature
  else
    local g=$?
    [[ "$g" == 1 ]] || stop secret_screen_failed
  fi
  return 0
}
a6_total=0
a6_files=()
for a6_f in "${a6_required[@]}"; do
  a6_check "$a6_f" || stop required_source_missing
  a6_files+=("$a6_f")
done
a6_absent=()
for a6_f in "${a6_optional[@]}"; do
  if a6_check "$a6_f"; then a6_files+=("$a6_f"); else a6_absent+=("$a6_f"); fi
done
a6_before=$(sha256sum -- "${a6_files[@]}")
a6_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
# Inventories: file paths, line numbers and the matched word only; no source lines.
{
  printf 'inventory_1=word_ulyublen_paths_lines\n'
  grep -rno --include='*.twig' --include='*.php' --include='*.js' -e 'улюблен' -e 'Улюблен' -- catalog extension/ukrainian extension/ps_enhanced_measurement 2>/dev/null | LC_ALL=C sort || true
  printf 'inventory_2=templates_referencing_product_thumb\n'
  { grep -rl --include='*.twig' --include='*.php' -- 'product/thumb' catalog/view/template catalog/controller 2>/dev/null; grep -rl --include='*.twig' -- 'product/thumb' extension/*/catalog/view/template 2>/dev/null; } | LC_ALL=C sort -u || true
  printf 'inventory_end\n'
} > "$a6_inventory"
a6_php=$(command -v php) || stop php_missing
[[ "$a6_php" == /* && -f "$a6_php" && -x "$a6_php" ]] || stop php_path_invalid
a6_version=$("$a6_php" -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
[[ "$a6_version" == 8.0 ]] || stop php_not_8_0
# Theme DB override counts only. Config is required inside PHP; never printed.
a6_metadata=$("$a6_php" -d display_errors=0 -d log_errors=0 <<'A6_PHP'
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
    $q = $db->query('SHOW COLUMNS FROM `' . DB_PREFIX . 'theme`');
    if (!$q) { throw new RuntimeException(); }
    $columns = [];
    while ($r = $q->fetch_assoc()) { $columns[] = $r['Field']; }
    $q->free();
    if (array_diff(['store_id','route','status'], $columns)) { throw new RuntimeException(); }
    if (!$db->query('START TRANSACTION READ ONLY')) { throw new RuntimeException(); }
    $meta = ['theme' => []];
    $route = '';
    $s = $db->prepare('SELECT COUNT(*), COALESCE(SUM(`status` = 1),0) FROM `' . DB_PREFIX . 'theme` WHERE `route` = ?');
    if (!$s || !$s->bind_param('s', $route)) { throw new RuntimeException(); }
    foreach (['product/thumb','product/product','account/wishlist','account/wishlist_list','account/account','account/shell','common/header'] as $route) {
        if (!$s->execute() || !$s->bind_result($total, $enabled) || !$s->fetch()) { throw new RuntimeException(); }
        $meta['theme'][$route] = ['rows_all_stores' => (int)$total, 'enabled_all_stores' => (int)$enabled];
        $s->free_result();
    }
    $s->close();
    $db->rollback();
    $db->close();
    $meta['php'] = PHP_VERSION;
    $json = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    ob_end_clean();
    echo $json;
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    fwrite(STDERR, "STOP=technical_metadata_failed_no_details\n");
    exit(1);
}
A6_PHP
) || stop metadata_failed
tar -czf - -- "${a6_files[@]}" > "$a6_archive"
a6_after=$(sha256sum -- "${a6_files[@]}")
[[ "$a6_before" == "$a6_after" ]] || stop source_drift
a6_index=0
while IFS= read -r a6_line; do
  a6_expected=${a6_line:0:64}
  a6_file=${a6_files[$a6_index]}
  [[ "$a6_expected" =~ ^[0-9a-f]{64}$ && "${a6_line:66}" == "$a6_file" ]] || stop hash_format
  [[ "${a6_line:64:2}" == '  ' || "${a6_line:64:2}" == ' *' ]] || stop hash_format
  a6_archived=$(tar -xzOf "$a6_archive" -- "$a6_file" | sha256sum)
  [[ "${a6_archived:0:64}" == "$a6_expected" ]] || stop archived_source_mismatch
  a6_index=$((a6_index + 1))
done <<< "$a6_before"
[[ "$a6_index" == "${#a6_files[@]}" ]] || stop source_count_mismatch
a6_archive_hash=$(sha256sum -- "$a6_archive")
a6_inventory_hash=$(sha256sum -- "$a6_inventory")
{
  printf 'task=ACC-006-VISUAL-WP0\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$a6_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#a6_files[@]}" "$a6_total"
  if (( ${#a6_absent[@]} > 0 )); then printf 'optional_absent=%s\n' "${a6_absent[*]}"; else printf 'optional_absent=none\n'; fi
  printf 'archive_sha256=%s\ninventory_sha256=%s\n' "${a6_archive_hash:0:64}" "${a6_inventory_hash:0:64}"
  printf 'metadata_json=%s\n' "$a6_metadata"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$a6_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$a6_manifest"
a6_manifest_hash=$(sha256sum -- "$a6_manifest")
printf 'task=ACC-006-VISUAL-WP0\nsource_files=%s\narchive_sha256=%s\ninventory_sha256=%s\nmanifest_sha256=%s\ndone=ok\n' "${#a6_files[@]}" "${a6_archive_hash:0:64}" "${a6_inventory_hash:0:64}" "${a6_manifest_hash:0:64}"
