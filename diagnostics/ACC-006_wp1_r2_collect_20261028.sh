#!/usr/bin/env bash
# ACC-006 visual step WP1 supplementary pull r2. Owner-run only. Read fixed public
# source files and two path/line inventories. No source/DB/cache writes, no DB read.
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
a6_base="$HOME/private/booster-pulls/ACC-006-visual-source-20261028-r2"
a6_archive="$a6_base.tar.gz"
a6_manifest="$a6_base.manifest.txt"
a6_inventory="$a6_base.inventory.txt"
for a6_out in "$a6_archive" "$a6_manifest" "$a6_inventory"; do
  [[ ! -e "$a6_out" && ! -L "$a6_out" ]] || stop artifact_exists_no_replay
done
set -C
a6_required=(
extension/ukrainian/catalog/language/uk-ua/account/address.php
extension/ukrainian/catalog/language/uk-ua/account/edit.php
extension/ukrainian/catalog/language/uk-ua/account/order.php
catalog/controller/account/order.php
catalog/view/template/account/order_list.twig
)
a6_optional=()
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
a6_before=$(sha256sum -- "${a6_files[@]}")
a6_started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
# Inventories: file paths, line numbers and the matched word only; no source lines.
{
  printf 'inventory_1=word_zakladk_paths_lines\n'
  grep -rno --include='*.twig' --include='*.php' --include='*.js' -e 'закладк' -e 'Закладк' -- catalog extension/ukrainian extension/opencart/catalog/view/template 2>/dev/null | LC_ALL=C sort || true
  printf 'inventory_2=extension_controllers_referencing_product_thumb\n'
  grep -rl --include='*.php' -- 'product/thumb' extension/*/catalog/controller 2>/dev/null | LC_ALL=C sort || true
  printf 'inventory_end\n'
} > "$a6_inventory"
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
  printf 'task=ACC-006-VISUAL-WP1-R2\ncollection_started_utc=%s\ncollection_finished_utc=%s\nsource_files=%s\nsource_bytes=%s\n' "$a6_started" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${#a6_files[@]}" "$a6_total"
  if (( ${#a6_absent[@]} > 0 )); then printf 'optional_absent=%s\n' "${a6_absent[*]}"; else printf 'optional_absent=none\n'; fi
  printf 'archive_sha256=%s\ninventory_sha256=%s\n' "${a6_archive_hash:0:64}" "${a6_inventory_hash:0:64}"
  printf 'source_hashes_begin\n%s\nsource_hashes_end\n' "$a6_before"
  printf 'source_pre_post=ok\narchived_source_hashes=ok\nproduction_writes=0\ndone=ok\n'
} > "$a6_manifest"
a6_manifest_hash=$(sha256sum -- "$a6_manifest")
printf 'task=ACC-006-VISUAL-WP1-R2\nsource_files=%s\narchive_sha256=%s\ninventory_sha256=%s\nmanifest_sha256=%s\ndone=ok\n' "${#a6_files[@]}" "${a6_archive_hash:0:64}" "${a6_inventory_hash:0:64}" "${a6_manifest_hash:0:64}"
