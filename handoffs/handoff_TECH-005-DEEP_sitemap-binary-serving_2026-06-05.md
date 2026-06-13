# HANDOFF TO CODEX — TECH-005-DEEP (sitemap served compressed/binary, no Content-Encoding)
_Date: 2026-06-05 · Prepared by: Claude · Recipient: Codex + Owner_
_Source backup: `backup-6.3.2026_21-17-59_boosters.tar.gz` · Live re-check performed 2026-06-05._
_Gate context: TECH-010 (noindex) and TECH-012 (canonical) are staged "після sitemap stable". This task is the blocker that must clear first._

---

## SEO RISK GATE (preflight — bs-seo-risk-gate)
- **Risk: HIGH.**
- **Affected assets:** `/sitemap-gsc.xml` (+ `/sitemap.xml`, `/sitemap.txt`), the `.htaccess` sitemap compression/cache blocks, and the **LiteSpeed server-level compression** config. Indirect: GSC Sitemaps report + whole-site indexation (`site:` ≈ 0).
- **Owner approval required: YES.** Staging + before/after fetch mandatory before going live.
- **Related smoke:** before/after `curl` matrix (Section 8). No checkout / Merchant / schema impact expected — but a **regression guard** for CSS/JS compression is included.

---

## 1. Task ID
**TECH-005-DEEP** — root-cause and fix the compressed/binary response for `/sitemap-gsc.xml` that has no usable `Content-Encoding` header. Continuation of TECH-005 (Order 01.1). Priority **High**. Per roadmap note: *"Якщо не виправити — site:boostershop.website продовжить повертати 0 результатів. Це блокер для всього органічного росту."*

## 2. Context (grounded — backup inspection + live re-fetch)

> **UPDATE 2026-06-05 (owner + live triage) — diagnosis refined.** Owner reports hosting support **already disabled compression**, and an owner terminal check "looked OK" — yet the response is **still binary**. Live triage 2026-06-05 (independent client sending `Accept-Encoding: br,gzip`):
> - `sitemap-gsc.xml` → **binary**; `sitemap.xml` → **binary**; **dynamic** `/uk-ua/sitemap.xml` (ps_google_sitemap) → **binary**.
> - **Controls that decode fine:** `robots.txt` (text/plain) → readable; `boostershop-ds.css` (compressed asset) → **decodes correctly** (carries a valid `Content-Encoding`).
> - **Inference:** the fault is **specific to `application/xml`/sitemap responses** — they are compressed **without a usable `Content-Encoding` header**, while text/css responses are compressed *with* the header and decode normally. Global compression is therefore NOT the issue; site-wide compression should stay on.
> - **New prime suspect:** the existing `# BEGIN sitemap-no-compression` `.htaccess` block (the prior "fix") may itself be the cause — half-disabling compression (stripping/forcing headers via `no-transform` + `SetEnv`) while the body stays compressed (cache/server layer). **Test by removing that block (Route 0).**
> - **Likely Accept-Encoding-conditional:** owner's terminal check probably used `curl` with no `Accept-Encoding` (→ identity → looks OK), while browsers/Google/this fetch send `br,gzip` (→ the broken path). Confirm first tomorrow.
> - **Also rule out a front CDN/proxy** (e.g. Cloudflare/QUIC.cloud) re-compressing after the host's change — check response headers for `cf-ray` / `x-qc-*` / `via` / `server`.
> See the hands-on runbook `diagnostics/TECH-005-DEEP_handson-diagnostic-runbook_2026-06-05.md` for the ordered execution plan.

- **Static file is valid — content is NOT the problem.** `sitemap-gsc.xml` = 5006 bytes, `XML 1.0 document, ASCII text`, `xml.dom.minidom` parse = OK. First bytes = `<?xml version="1...`. (`sitemap-gsc-2.xml` identical, 5006 bytes.)
- **Bug reproduced live today (2026-06-04).** Independent fetch of `https://boostershop.website/sitemap-gsc.xml` → `Content-Type: application/xml; charset=UTF-8`, body = **binary / undecodable as text**.
- **GSC (owner screenshot, `sitemap-gsc-2.xml`):** "Не вдалося прочитати файл Sitemap", **0 pages**, last read **25.05.2026**.
- **Historical signal (roadmap):** Google `66.249.73.*` GET `/sitemap-gsc.xml` → `200`, **249 bytes** vs real **5006**. 249 bytes ≈ Brotli of the repetitive 5 KB XML.
- **`.htaccess` already contains layered no-compression attempts that are NOT working** (block `# BEGIN sitemap-no-compression`, L63–89, plus L2–16):
  - `FilesMatch "sitemap.*\.xml$"` → `SetEnv no-gzip 1` / `no-brotli 1`
  - LiteSpeed `CacheDisable public /sitemap-gsc.xml` (twice)
  - `SetEnvIfNoCase Request_URI "^/sitemap(-gsc)?\.xml..." no-brotli=1 no-gzip=1`
  - `SetEnvIfNoCase User-Agent "Googlebot|Google-InspectionTool" no-brotli=1 no-gzip=1`
  - `RewriteRule ^sitemap(-gsc)?\.xml$ - [E=no-brotli:1,E=no-gzip:1]` (**duplicated line**)
  - `Header always set Cache-Control "...no-transform"` + `X-LiteSpeed-Cache-Control "no-cache,no-store,no-vary"`
- **Working conclusion:** response is still binary despite all of the above → compression is applied at the **LiteSpeed server layer**, which is not honoring the Apache-style `SetEnv no-gzip/no-brotli` switches, and the `Content-Encoding` header is **absent** (likely suppressed by `no-transform` / cache interaction). Clients receive raw Brotli bytes labelled `application/xml` and cannot decode → "can't read sitemap." **This must be confirmed by the Step 0 curl matrix before any edit** — do not assume the mechanism.

## 3. Goal
GSC can read the sitemap. `GET /sitemap-gsc.xml` returns **200**, `Content-Type: application/xml`, and a body that standard clients decode to the full readable XML (~5006 bytes) — **either** uncompressed, **or** compressed **with a correct matching `Content-Encoding` header**. Success = GSC Sitemaps shows **Success / N discovered URLs**, and `site:boostershop.website` begins returning pages.

## 4. What to change (DIAGNOSE FIRST — do not blind-patch)
Four overlapping `.htaccess` no-compression layers already failed. **Do not add a 5th block.**

**Step 0 — MANDATORY diagnosis (run the matrix in Section 8 first).** Establish: (a) is the body **gzip or brotli**? (b) is a `Content-Encoding` header present at all? (c) does it depend on `Accept-Encoding` / User-Agent / HTTP version? (d) is it a stale **LiteSpeed cache** object or **live** compression?

**Step 1 — pick the fix from Step 0 evidence:**
- **Route 0 — NEW prime suspect, test first (Codex/owner, fully reversible):** back up `.htaccess`, then **remove/comment the entire `# BEGIN sitemap-no-compression … # END` block** and re-run the matrix. Since `robots.txt`/CSS already serve correctly, plain LiteSpeed handling may serve the XML correctly too once these conflicting overrides are gone. If XML then behaves like CSS (compressed **with** `Content-Encoding`, decodes) or serves identity → done, keep it removed/minimal.
- **Route A — definitive if Route 0 insufficient (OWNER + HOSTING, not `.htaccess`):** disable compression for the sitemap response at the **LiteSpeed server / vhost** level — LiteSpeed Web Admin → Tuning → exclude `.xml` (or the sitemap path) from *Compressible Types*, or via cPanel/host support if the shared panel doesn't expose it. This is the cleanest fix because the failing layer is the server, not `.htaccess`.
- **Route B — Codex fallback (only if hosting cannot change server compression):** serve the sitemap through a tiny PHP passthrough that LiteSpeed will not statically compress, **keeping the same public URL** via an internal `.htaccess` rewrite (the sitemap URL must not change). The PHP reads the existing static `sitemap-gsc.xml`, disables output compression (`ini_set('zlib.output_compression','Off')`, no `ob_gzhandler`), and emits `Content-Type: application/xml; charset=UTF-8` + `Content-Length`, with either no compression or a correct `Content-Encoding`. Verify LiteSpeed does not re-compress the PHP output (Step 0 matrix re-run).
- **Route C — last resort (TECH-005 note "мінімальний sitemap"):** only helps if the response drops **below LiteSpeed's min-compress length** so it is served raw. Fragile, content-dependent; not recommended as the primary fix.

**Step 2 — consolidation (only after A or B verified on staging):** collapse the redundant sitemap `.htaccess` rules into **one** clearly-commented block (remove the duplicated `RewriteRule` at L73–74). Single source of truth.

## 5. Do not touch
- **Sitemap URL / path** — `/sitemap-gsc.xml` must stay (TECH-005-DEEP hard rule: "Не міняти URL sitemap").
- **Sitemap XML content** — it is valid. (The 2 stale 404 product URLs noted in TECH-005 are a *separate* cleanup, not this task.)
- **`robots.txt` content** — the `Sitemap:` line already points to `/sitemap-gsc.xml`; leave it.
- **Site-wide compression for CSS / JS / HTML** — do **not** disable global brotli/gzip; scope strictly to the sitemap path.
- **Canonical logic, redirects** (www/https L93–95), **OpenCart routing** (L103–105), **checkout / payment, Merchant feed/TSV, schema (JSON-LD), hreflang.**
- The dynamic `ps_google_sitemap` route (L99) — unless Step 0 proves it is the actually-served path.

## 6. Likely files / areas
| Area | Change | Confidence |
|---|---|---|
| `.htaccess` → `# BEGIN sitemap-no-compression` block (L63–89) | consolidate; (Route B) add internal rewrite to PHP passthrough | likely |
| **LiteSpeed server / vhost compression config** (cPanel / host) | exclude sitemap from *Compressible Types* | **likely — OWNER/HOSTING, must verify** |
| new `sitemap-gsc.php` (Route B only) | static-XML passthrough, compression off | conditional |
| OpenCart `ps_google_sitemap` generator | check `ob_gzhandler` **only if** Step 0 shows it is the served path | verify |

> Codex should verify against the **actual live server response** (Step 0) before editing. Do not assume LiteSpeed behavior from `.htaccess` alone.

## 7. Acceptance criteria (measurable)
- `curl -sS -D- "https://boostershop.website/sitemap-gsc.xml" -o /tmp/s.xml` → **HTTP 200**, `Content-Type: application/xml`; `xmllint --noout /tmp/s.xml` = **OK**.
- Decoded body size ≈ **5006 bytes** (the real file) — **not 249 / 713**.
- With `Accept-Encoding: br,gzip`: **if** compressed → a **matching `Content-Encoding`** header is present and the body decodes; **if** not compressed → no `Content-Encoding` and raw XML. No "compressed body without header" state.
- Same pass for `-A "Googlebot"` and `-A "Google-InspectionTool"`, on HTTP/1.1 **and** HTTP/2.
- **Regression guard:** `curl -s -D- -H 'Accept-Encoding: br' ".../catalog/view/stylesheet/boostershop-ds.css" | grep -i content-encoding` still shows `br`/`gzip` (site-wide compression intact).
- GSC → Sitemaps → resubmit `/sitemap-gsc.xml` → **Last read** advances **and** status = **Success**, discovered URLs **> 0** (allow GSC lag, days).
- Follow-up signal: `site:boostershop.website` > 0 results; GSC Pages indexed count rises above the current **8**.

## 8. QA / smoke test — curl matrix (run BEFORE and AFTER, on staging then live)
```bash
URL="https://boostershop.website/sitemap-gsc.xml"
# 1) default
curl -sS -D- "$URL" -o /tmp/a.bin; echo "bytes=$(wc -c </tmp/a.bin)"; file /tmp/a.bin
# 2) explicitly request brotli+gzip
curl -sS -D- -H 'Accept-Encoding: br,gzip' "$URL" -o /tmp/b.bin; file /tmp/b.bin
# 3) NO compression requested
curl -sS -D- -H 'Accept-Encoding: identity' "$URL" -o /tmp/c.bin; head -c 60 /tmp/c.bin
# 4) as Googlebot
curl -sS -D- -A 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' "$URL" -o /tmp/d.bin; file /tmp/d.bin
# 5) HTTP/2 + Google-InspectionTool
curl -sS -D- --http2 -A 'Google-InspectionTool/1.0' "$URL" -o /tmp/e.bin; file /tmp/e.bin
# decode checks
xmllint --noout /tmp/c.bin && echo "identity = valid XML"
brotli -d </tmp/b.bin 2>/dev/null | xmllint --noout - && echo "br body decodes to valid XML"
```
Read the `-D-` header dumps: look for `Content-Encoding:` and `Content-Length:` on each. The fix is correct when the decoded XML is valid for **every** row and there is never a compressed body lacking its `Content-Encoding` header. Then re-run rows for `sitemap.xml` / `sitemap.txt` if those are still referenced, and confirm home + one product page still return 200.

## 9. Rollback note
- **Before any edit:** `cp .htaccess .htaccess.bak-tech005deep-20260605`.
- **Route B:** the PHP passthrough + internal rewrite is **additive** — rollback = delete the rewrite line and `sitemap-gsc.php`; static-file serving returns to the prior state.
- **Route A:** the LiteSpeed/cPanel compression change is reversible in the same panel.
- No DB, no OpenCart cache, no checkout/payment touched → full rollback = restore `.htaccess.bak` (and remove the `.php` if added). Keep the original `sitemap-gsc.xml` untouched as the canonical source.

## 10. Recommended status after execution
- Step 0 done + fix applied + curl acceptance passing on live → **TECH-005-DEEP = "На перевірці"** (waiting on GSC re-read). Keep **TECH-005 = "На перевірці"**.
- Flip both to **Done** only after GSC Sitemaps = **Success** with discovered URLs > 0.
- Only then is the **"після sitemap stable"** gate met → unblock **TECH-012** (canonical) and **TECH-010** (noindex).

---
_References: bs-seo-risk-gate (HIGH, owner approval + staging). No bs-checkout-smoke / bs-merchant-schema-qa needed (no checkout/Merchant/schema in scope). Codex must verify live server response before editing — prior blind `.htaccess` stacking is exactly why TECH-005 did not close._
