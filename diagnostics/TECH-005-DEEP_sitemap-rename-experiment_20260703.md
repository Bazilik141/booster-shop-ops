# TECH-005-DEEP — Sitemap rename experiment (sitemap_index.xml)
_Date: 2026-07-03. Session log + evidence. Prior context: TECH-005-DEEP reports 2026-07-01/02, neutral situation handoff 2026-07-02._

## 1. Short conclusion

GSC Sitemaps processor makes **zero fetch attempts** on `/sitemap-full.xml` (confirmed via access logs 01–03 Jul), while InspectionTool, bingbot, and all third-party validators fetch 200. URL Inspection of the sitemap URL returns "Невідома Google URL-адреса" + persistent UI error. Stuck state appears keyed to the exact submitted URL → executed **filename-escape experiment**: serve identical sitemap under fresh URL `/sitemap_index.xml`, submit that to GSC, abandon the old URL in GSC.

## 2. Evidence collected 2026-07-03 (before the change)

- **GSC Coverage export (data through 06-30):** 52 indexed (peak, stable since 06-13; was 38 on 06-12). Not-indexed 36 = noindex 11 (search/tag, account — intentional) + robots 9 (sort/order params — intentional) + alt-canonical 9 (index.php?route dupes — correct) + crawled-not-indexed 7 (5 real: accessories ×4 crawled 07-01, Abyss-Eye booster box 06-15; 2 param URLs). **No real de-indexation. H2 closed.**
- **Manual Actions: clean. Security Issues: clean.** H5-penalty closed.
- **Enhancement "valid pages drop" (06-15→06-30)** happened while indexed count stayed 52 → reporting recalculation, not real loss. Confirmed benign.
- **Access logs 01–03 Jul:** no Googlebot (sitemap-processor) request to any sitemap URL. Only Google-InspectionTool (66.249.68.x, matches manual inspections), bingbot (5 fetches 02 Jul + 2 on 03 Jul after BWT submission — Bing processor working, verdict pending), ClaudeBot, third-party validators. Note: `Googlebot/2.1` UA from 77.239.160.165 = own audit script (spoofed UA), not Google.
- **ModSecurity A/B #3 (03 Jul 19:30–19:42 OFF):** no change in GSC behavior. WAF conclusively not involved.
- **GSC delete + resubmit sitemap-full.xml (19:35):** no processor fetch followed (watched 19:35–19:45+).

## 3. Change executed (2026-07-03 ~20:00, owner via SSH)

1. `cp public_html/sitemap-full.xml public_html/sitemap_index.xml` (chmod 644) — full copy, NOT an index wrapper (wrapper would inherit the stuck child URL).
2. `robots.txt` → `Sitemap: https://boostershop.website/sitemap_index.xml` (single line, replaced).
3. `sitemap-regen.sh` (repo) — added step 4b: mirror `sitemap-full.xml` → `sitemap_index.xml` after atomic publish. **Server copy of the script must be updated (FTP/scp) — pending.**
4. `sitemap-full.xml` left in place and serving (Bing is mid-processing it; do not touch BWT submission).
5. GSC: submit `/sitemap_index.xml`; old `sitemap-full.xml` entry deleted.

Verified post-change: robots.txt shows new line; `curl -sI /sitemap_index.xml` → HTTP/2 200, `application/xml`.

## 4. Rationale

Known community workaround for stuck GSC sitemap state ("Couldn't read" persisting for a technically valid file): fresh filename = fresh URL = fresh pipeline record. Community-circulated explanation ("failure loop", "aggressive caching") is folklore, but the workaround itself is legitimate and low-risk. Our case fits: per-URL stuck state, zero fetch attempts, "Невідома Google URL-адреса".

## 5. Pending QA (owner)

- [ ] Update `sitemap-regen.sh` on server from repo copy
- [ ] Morning after 04:15 cron: `cat ~/logs/sitemap-regen.log` → OK; both files fresh timestamp; `diff` identical
- [ ] Post-factum log check for Google fetches of `/sitemap_index.xml`: `zgrep -i "sitemap_index" ~/logs/boostershop.website-ssl_log-Jul-2026.gz; grep -i "sitemap_index" ~/access-logs/boostershop.website-ssl_log` — look for 66.249.x.x with Googlebot UA
- [ ] GSC Sitemaps status for `/sitemap_index.xml` in 24–72h
- [ ] Bing Webmaster verdict on `sitemap-full.xml`

## 6. Decision tree

- New sitemap → **Success** in GSC → stuck-state theory confirmed in practice; close sitemap saga.
- New sitemap → same "Couldn't read", **with** processor fetch in logs → parser-side nuance (H3): next test = true `sitemap-index` wrapper or serving-header variations.
- New sitemap → same error, **zero** fetch attempts → terminal H1 diagnosis (GSC-side, site clean). Stop spending time: indexation demonstrably works without the sitemap report (52/57). Shift focus to content/authority (H4): accessory page depth, YGO/MTG thin pages, internal links, off-page.

## 7. Risks / rollback

Risk: Low-Medium (additive change; robots.txt single-line edit). Rollback = restore `Sitemap:` line to sitemap-full.xml + delete sitemap_index.xml (2 min). No canonical/redirect/feed/checkout surface touched. Merchant feed (`/merchant-feed.tsv`) untouched.
