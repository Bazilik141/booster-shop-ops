#!/bin/bash
# OPS-CLEANUP — Booster Shop server cleanup, approved scope Tier 1 + Tier 4.
# Run in cPanel Terminal. Owner-executed only.
#
# Safety design:
#   - MODE=dryrun by default: prints what would be removed and the reclaimed size.
#   - Nothing is removed until MODE=apply is set explicitly on the command line.
#   - Phase 4 tars the web-root files to ~/ops-cleanup-webroot-<ts>.tar.gz before removal.
#   - The 2026-08-05 backup is never matched: delete patterns are pinned to backup-4.* / backup-5.*
#
# Usage:
#   bash ops-cleanup.sh            # dry run, safe, prints only
#   MODE=apply bash ops-cleanup.sh # performs removal

# NOTE: `set -u` is deliberately NOT used. On bash 4.2 (still shipped on
# CloudLinux 7) an empty array expansion under `set -u` aborts the script,
# which would silently skip phases. Every array below is explicitly initialised
# instead.
set -o pipefail

HOME_DIR="/home2/boosters"
PH="$HOME_DIR/public_html"
MODE="${MODE:-dryrun}"
TS="$(date +%Y%m%d-%H%M%S)"

say()  { printf '\n=== %s ===\n' "$1"; }
size() { du -sch "$@" 2>/dev/null | tail -1 | awk '{print $1}'; }

kill_list() {                      # kill_list <label> <path>...
  local label="$1"; shift
  local existing=()
  for p in "$@"; do [ -e "$p" ] && existing+=("$p"); done
  if [ ${#existing[@]} -eq 0 ]; then
    printf '%-34s nothing found\n' "$label"
    return
  fi
  printf '%-34s %8s  (%d items)\n' "$label" "$(size "${existing[@]}")" "${#existing[@]}"
  if [ "$MODE" = "apply" ]; then
    rm -rf -- "${existing[@]}"
    printf '%-34s REMOVED\n' "$label"
  fi
}

say "PHASE 0 — verify environment"
cd "$HOME_DIR" || { echo "FATAL: $HOME_DIR not found"; exit 1; }
echo "home        : $(pwd)"
echo "mode        : $MODE"
echo "disk before :"; df -h "$HOME_DIR" | tail -1
echo
echo "cron jobs that must keep working (do not delete their targets):"
crontab -l 2>/dev/null | grep -v '^#' | grep -v '^$' || echo "  (crontab unreadable from here)"
echo
echo "protected files present?"
for f in "$HOME_DIR/sitemap-regen.sh" \
         "$PH/system/library/booster_async_queue_worker.php" \
         "$PH/system/library/booster_crm_sync.php" \
         "$PH/.htaccess" "$PH/robots.txt" "$PH/config.php" "$PH/merchant-feed.tsv"; do
  [ -e "$f" ] && echo "  OK   $f" || echo "  MISS $f  <-- investigate before continuing"
done
echo
echo "backups currently in home root (these are NOT touched):"
ls -lh "$HOME_DIR"/backup-*.tar.gz 2>/dev/null || echo "  none in home root"

# ---------------------------------------------------------------------------
say "PHASE 1 — .trash  (expected ~3.07 GB)"
# All cPanel backups in .trash are 2026-04 / 2026-05. The current 2026-08-05
# backup lives in the home root and is not matched by these patterns.
echo "backups queued for deletion:"
ls -1 "$HOME_DIR"/.trash/backup-4.*.tar.gz "$HOME_DIR"/.trash/backup-5.*.tar.gz 2>/dev/null | sed 's#^#  #'
echo "guard check — any 2026-08 backup inside .trash? (must print nothing):"
ls -1 "$HOME_DIR"/.trash/backup-8.* 2>/dev/null | sed 's#^#  !! #' || true

kill_list ".trash old cPanel backups" "$HOME_DIR"/.trash/backup-4.*.tar.gz "$HOME_DIR"/.trash/backup-5.*.tar.gz

# Remaining .trash content: ~300 debug tarballs and numbered .twig/.php copies.
# In dry-run the figure below still INCLUDES the backups listed above (nothing
# was removed yet), so it is not an additional 3 GB. In apply mode the backups
# are already gone by this point and the figure drops to a few MB.
if [ -d "$HOME_DIR/.trash" ]; then
  printf '%-34s %8s%s\n' ".trash total (incl. above)" "$(size "$HOME_DIR/.trash")" \
    "$([ "$MODE" = "apply" ] && echo "" || echo "   <- dry-run: overlaps the line above")"
  if [ "$MODE" = "apply" ]; then
    find "$HOME_DIR/.trash" -mindepth 1 -delete 2>/dev/null
    printf '%-34s EMPTIED\n' ".trash remainder"
  fi
fi

# ---------------------------------------------------------------------------
say "PHASE 2 — patch backups older than 2026-07-01  (expected ~5.8 MB)"
# Five parallel folder names exist because the naming convention changed.
# Anything from 2026-07-01 onward is kept: those are rollback points for
# CHECKOUT-009 / ST-2c work that is still recent.
for dir in _patch_backups _booster_patch_backups booster_patch_backups \
           _bs_patch_backups _boostershop_patch_backups _patch_reports; do
  [ -d "$PH/$dir" ] || continue
  # No process substitution here: CageFS/CloudLinux does not expose /dev/fd,
  # so `< <(...)` fails and would silently leave the array empty.
  tmp_old="$(mktemp)"
  find "$PH/$dir" -mindepth 1 -maxdepth 1 -type d ! -newermt "2026-07-01" > "$tmp_old" 2>/dev/null
  old=()
  while IFS= read -r d; do [ -n "$d" ] && old+=("$d"); done < "$tmp_old"
  rm -f "$tmp_old"
  keep=$(find "$PH/$dir" -mindepth 1 -maxdepth 1 -type d -newermt "2026-07-01" 2>/dev/null | wc -l)
  if [ ${#old[@]} -eq 0 ]; then
    printf '%-34s no pre-July dirs (keeping %s)\n' "$dir" "$keep"
    continue
  fi
  printf '%-34s %8s  delete %d, keep %d\n' "$dir" "$(size "${old[@]}")" "${#old[@]}" "$keep"
  [ "$MODE" = "apply" ] && rm -rf -- "${old[@]}" && printf '%-34s REMOVED\n' "$dir"
done

# ---------------------------------------------------------------------------
say "PHASE 3 — stale working directories and cPanel stats  (expected ~14 MB)"
kill_list "cPanel stats + pma cache" \
  "$HOME_DIR"/tmp/analog "$HOME_DIR"/tmp/webalizer "$HOME_DIR"/tmp/awstats \
  "$HOME_DIR"/tmp/pma_template_compiles_boosters

kill_list "home: old diagnostic dirs" \
  "$HOME_DIR"/rd13_site-snapshot_20260705_121237 \
  "$HOME_DIR"/tech005_proof_20260522-101026 \
  "$HOME_DIR"/_sm_analysis_20260608 \
  "$HOME_DIR"/bs_gsc_schema_debug_20260526_150420 \
  "$HOME_DIR"/_public_html_cleanup_20260520-213343 \
  "$HOME_DIR"/_public_html_cleanup_20260520-213522

kill_list "home: stray files" \
  "$HOME_DIR"/RD-13_checkout-reskin-round6_20260708.php \
  "$HOME_DIR"/bs-first15-error-tail.txt \
  "$HOME_DIR"/merchant-feed-build.php.bak-20260605-174550

kill_list "public_html: debug dirs" \
  "$PH"/booster-seo-crit-001-debug-20260607-080345 \
  "$PH"/rd04-verify \
  "$PH"/rd04-preorder-check \
  "$PH"/booster-tech030-031-debug \
  "$PH"/booster-order180-diagnostic-20260616-095116 \
  "$PH"/booster-crm-sync-files-20260611-111517 \
  "$PH"/booster-crm-sync-debug-20260611-110825

# NOT deleted on purpose:
#   ~/CHECKOUT-009-evidence-20260729-155027/  — CHECKOUT-009 is recent work
#   ~/sitemap-regen.sh                        — daily cron 04:15
#   ~/merchant-feed-build.php                 — manual Merchant feed tool
#   ~/products_structure.json                 — 1.34 MB export, owner call

# ---------------------------------------------------------------------------
say "PHASE 4 — web root  (security; ~0.17 MB)"
# Three of these execute live mutations on an anonymous GET:
#   booster_crm_sync_replay_20260616.php  rewrites system/library/booster_crm_sync.php
#   rd10_product_page_parity_20260611.php   writes templates + UPDATEs a DB row
#   rd10_product_page_parity_20260611b.php  same
# booster_crm_flush.php is token-gated but orphaned (nothing references it).

WEBROOT_FILES=(
  "$PH/booster_crm_sync_replay_20260616.php"
  "$PH/rd10_product_page_parity_20260611.php"
  "$PH/rd10_product_page_parity_20260611b.php"
  "$PH/booster_crm_flush.php"
  "$PH/PAY-001-INFO-1_preflight_20260726.php"
  "$PH/PAY-001-INFO-1_preflight_state_20260726.php"
  "$PH/.htaccess.bak-tech005-20260609"
  "$PH/.htaccess.bak-tech005deep-20260606"
  "$PH/robots.txt.bak-tech005-20260606"
  "$PH/robots.txt.bak-tech005-20260609b"
  "$PH/merchant-feed.tsv.bak-20260605-174550"
  "$PH/merchant-feed.tsv.bak-20260611-080654"
  "$PH/merchant-feed-preorder-patch-20260605-174550.log"
  "$PH/merchant-feed-rebuild-20260611-080654.log"
  "$PH/booster-404-files.txt"
  "$PH/rd131j_patch_backups.txt"
  "$PH/st2b6-confirm-callers.txt"
  "$PH/st2b6b-payment-state-matches.txt"
  "$PH/st2b6c-autoselect-matches.txt"
  "$PH/st2a8_guest_autosave_ref_gate_report_20260613.md"
  "$PH/st2a9_add_to_cart_cold_session_ux_report_20260613.md"
  "$PH/st2a8b_st2a9b2_dropdown_click_autosave_cart_probe_20260613-20260613-153443.md"
  "$PH/st2a8b_st2a9b2_dropdown_click_autosave_cart_probe_20260613-20260613-153443.json"
)

present=()
for f in "${WEBROOT_FILES[@]}"; do [ -e "$f" ] && present+=("$f"); done
printf 'found %d of %d listed files\n' "${#present[@]}" "${#WEBROOT_FILES[@]}"
for f in "${present[@]}"; do echo "  $(basename "$f")"; done

echo
echo "explicitly NOT in this list (verified needed):"
echo "  .htaccess  robots.txt  sitemap-full.xml  sitemap_index.xml"
echo "  merchant-feed.tsv  config.php  cron.php  index.php  php.ini"

if [ "$MODE" = "apply" ] && [ ${#present[@]} -gt 0 ]; then
  listfile="$(mktemp)"
  printf '%s\n' "${present[@]}" | sed "s#^$PH/##" > "$listfile"
  if tar -czf "$HOME_DIR/ops-cleanup-webroot-$TS.tar.gz" -C "$PH" -T "$listfile"; then
    rm -f "$listfile"
    echo "safety copy: $HOME_DIR/ops-cleanup-webroot-$TS.tar.gz"
    rm -f -- "${present[@]}"
    echo "REMOVED ${#present[@]} files from web root"
  else
    rm -f "$listfile"
    echo "STOP: safety archive failed — nothing removed from the web root."
  fi
fi

# ---------------------------------------------------------------------------
say "RESULT"
echo "disk after :"; df -h "$HOME_DIR" | tail -1
if [ "$MODE" != "apply" ]; then
  echo
  echo "This was a DRY RUN. Nothing was deleted."
  echo "To execute: MODE=apply bash $0"
fi
