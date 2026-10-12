#!/usr/bin/env bash
# Owner-authorized cleanup of this RD-25 intake chain only. No site data/cache,
# product source, patch backups or older RD-25 WP1 exports are removed.
(
set -eu
rd25_home="$(cd "$HOME" && pwd -P)"

rm -f -- \
  "$rd25_home/RD-25-WP2-WP3_phase0_export_20261011.php" \
  "$rd25_home/RD-25-WP2-WP3_phase0_export-r3_20261012.php" \
  "$rd25_home/public_html/RD-25-WP2-WP3_phase0_export_20261011.php" \
  "$rd25_home/public_html/RD-25-WP2-WP3_phase0_export-r3_20261012.php"

# GNU find's default -P excludes top-level symlink directories. Targets are
# direct children of the resolved owner home, restricted to our export names.
# rm -r removes nested symlinks themselves, without following their targets.
find "$rd25_home" -mindepth 1 -maxdepth 1 -type d \
  \( -name 'RD25-WP2-WP3-phase0.*' -o -name 'RD25-WP2-WP3-export-*' \) \
  -print -exec rm -r -- {} +

find "$rd25_home" -mindepth 1 -maxdepth 1 -type f \
  -name 'RD-25-WP2-WP3-check-*.tar.gz' \
  ! -name 'RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz' \
  -print -delete

echo 'Очищення завершено; новий архів збережено.'
)
