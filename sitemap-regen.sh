#!/bin/bash
# sitemap-regen.sh — regenerate clean static /sitemap-full.xml from the dynamic OpenCart feed.
# Pulls the always-current dynamic sitemap, strips ?route=product/manufacturer.info duplicates,
# validates, and atomically replaces the static file. Run daily via cron.
# Created 2026-06-09 (TECH-005-DEEP). Serving handled by static file (clean headers after .htaccess fix).
export PATH=/usr/local/bin:/usr/bin:/bin
ROOT=/home2/boosters/public_html
SRC="https://boostershop.website/uk-ua/sitemap.xml"
DST="$ROOT/sitemap-full.xml"
TMP="$ROOT/.sitemap-full.tmp.$$"
RAW="$(mktemp /tmp/sm-raw.XXXXXX)"
TS="$(date '+%Y-%m-%d %H:%M:%S')"

# 1) fetch current dynamic sitemap (decoded)
curl -s --compressed --max-time 30 "$SRC" -o "$RAW"
if [ ! -s "$RAW" ]; then echo "$TS FAIL empty-fetch"; rm -f "$RAW"; exit 1; fi

# 2) strip manufacturer ?route= duplicate <url> blocks
python3 - "$RAW" "$TMP" <<'PY'
import sys, re
s = open(sys.argv[1], encoding='utf-8').read()
s = re.sub(r'\s*<url>.*?</url>',
           lambda m: '' if 'route=product/manufacturer.info' in m.group(0) else m.group(0),
           s, flags=re.S)
s = re.sub(r'\n{3,}', '\n\n', s)
open(sys.argv[2], 'w', encoding='utf-8').write(s)
PY

# 3) validate before publishing (never clobber a good file with a broken one)
if ! xmllint --noout "$TMP" 2>/dev/null; then echo "$TS FAIL invalid-xml"; rm -f "$TMP" "$RAW"; exit 1; fi
LOCS=$(grep -c '<loc>' "$TMP")
if [ "${LOCS:-0}" -lt 10 ]; then echo "$TS FAIL too-few-locs:$LOCS"; rm -f "$TMP" "$RAW"; exit 1; fi

# 4) atomic publish
mv -f "$TMP" "$DST" && chmod 644 "$DST"
# 4b) mirror under fresh filename (TECH-005-DEEP 2026-07-03: escape stuck GSC Sitemaps state via new URL)
cp -f "$DST" "$ROOT/sitemap_index.xml" && chmod 644 "$ROOT/sitemap_index.xml"
echo "$TS OK locs=$LOCS"
rm -f "$RAW"
