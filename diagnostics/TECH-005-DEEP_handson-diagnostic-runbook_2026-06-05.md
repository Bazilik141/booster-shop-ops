# TECH-005-DEEP — Hands-on diagnostic runbook (sitemap still binary)
_Prepared 2026-06-05 by Claude · Session planned 2026-06-06 · Owner-driven, run interactively with Claude_

## Why we're doing this
Hosting support reportedly **already disabled compression**, an owner terminal check "looked OK", **but the sitemap is still served binary.** Live triage 2026-06-05 (client sending `Accept-Encoding: br,gzip`):

| URL | Result |
|---|---|
| `/sitemap-gsc.xml` (static, in robots.txt) | **binary** |
| `/sitemap-gsc-2.xml` (static, **the file GSC has submitted**) | **binary** |
| `/sitemap.xml` (static) | **binary** |
| `/uk-ua/sitemap.xml` (dynamic ps_google_sitemap) | **binary** |
| `/robots.txt` (text/plain) | readable ✓ |
| `/catalog/view/stylesheet/boostershop-ds.css` (compressed) | **decodes fine** ✓ |

So global compression is fine; **only `application/xml`/sitemap responses are compressed without a usable `Content-Encoding` header.** CSS proves compression *with* a correct header decodes normally.

## Cross-dialog reconciliation (second input, 2026-06-05)
Confirmed environment — use these exact facts/paths tomorrow: **LiteSpeed** (not Apache) · **OpenCart 4** · webroot `/home2/boosters/public_html/` · storage `/home2/boosters/ocartdata/storage/` · DB prefix `ocp5_`.

- **The sitemap GSC actually has submitted is `/sitemap-gsc-2.xml`** (last Google fetch 2026-05-25, still "Не вдалось отримати") — but **`robots.txt` advertises `/sitemap-gsc.xml`** (no `-2`). URL mismatch to standardize **after** serving is fixed (pick one, make robots + GSC agree). Confirmed live 2026-06-05: **both** files return binary.
- **Correction to the other handoff:** it states `/sitemap.xml` is "OK (200, application/xml, no Content-Encoding, CL 5270) — do not touch." That check was `curl -I` **without** `Accept-Encoding` (= identity). My live fetch 2026-06-05 **with `Accept-Encoding: br,gzip` returns binary for `/sitemap.xml` too** → it is **not** actually fixed; do **not** exclude it. This independently **re-confirms Hypothesis 1** (bug only appears when the client accepts compression).
- **Implication:** the prior `.htaccess` "fix" only neutralised compression for identity clients; Googlebot/browsers send `br,gzip` and still hit the broken path. The fix is correct only when the **`br,gzip`** request returns valid/decodable XML.
- **Run the matrix on BOTH files** (`sitemap-gsc.xml` and `sitemap-gsc-2.xml`) — swap the `URL=` in Blocks A/C.
- **LiteSpeed-specific (their step 5 — keep):** LiteSpeed can **ignore `.htaccess` for compression** and be governed by **WebAdmin → Tuning → Compression** (Enable Compression / Compressible Types) or `.user.ini` / `httpd_config`. If Block G (`.htaccess` removal) changes nothing, the lever is here — and on shared hosting that means the **hosting ticket** (template at the end), this time explicitly naming `application/xml` + `Accept-Encoding: br` + the `-2` file.

## Three hypotheses we will confirm or kill (in order)
1. **Accept-Encoding-conditional.** Your earlier terminal check used `curl` with no `Accept-Encoding` → got identity → looked OK. Real clients (browsers, Google, our fetch) send `br,gzip` → get the broken path. → **Block A.**
2. **The existing `.htaccess` "no-compression" block is the cause** (it half-disables compression: body stays compressed, header stripped by `no-transform`/`SetEnv`). → **Block G (Route 0).**
3. **A front CDN/proxy re-compresses** after the host's origin change (QUIC.cloud / Cloudflare / edge). The host disabled origin compression, but the edge still compresses and drops the header. → **Blocks D + E.**

These are not mutually exclusive. We run the matrix, read headers, and branch.

## What I need from your hands (access)
- **SSH/terminal to the server** (you already have it — used it last time). Best signal comes from inside the origin.
- **cPanel** access (LiteSpeed Cache manager + any "Optimize/Compression" panel).
- Ability to **edit `~/public_html/.htaccess`** (with backup) and to **purge LiteSpeed cache**.
- The exact text of the **last hosting-support reply** (what they say they changed — origin? path? type?).

## How we run it
You paste one block at a time into the terminal, paste the **full output** (including the `*` header lines) back to me. I read it, tell you what it means, and which block is next. Do destructive steps (Block G) only at a low-traffic moment.

---

## BLOCK 0 — snapshot & safety (do once)
```bash
cd /home2/boosters/public_html
cp .htaccess .htaccess.bak-tech005deep-20260606
date; echo "backup made"; ls -l .htaccess.bak-tech005deep-20260606
```
Goal: instant rollback for any `.htaccess` change today.

## BLOCK A — reproduce the contradiction (identity vs brotli)
```bash
URL=https://boostershop.website/sitemap-gsc.xml
echo "--- identity (no compression requested) ---"
curl -s -D - -H 'Accept-Encoding: identity' "$URL" -o /tmp/id.bin -w 'SIZE=%{size_download} TYPE=%{content_type}\n'
head -c 80 /tmp/id.bin; echo
echo "--- brotli+gzip (what browsers/Google send) ---"
curl -s -D - -H 'Accept-Encoding: br,gzip' "$URL" -o /tmp/br.bin -w 'SIZE=%{size_download} TYPE=%{content_type}\n'
file /tmp/br.bin; head -c 16 /tmp/br.bin | xxd | head -1
```
**Read:** in each header dump, is there a `content-encoding:` line? What's `SIZE`?
- `id.bin` starts with `<?xml` **and** `br.bin` is binary **with NO `content-encoding` header** → **Hypothesis 1 confirmed** (compressed-without-header, AE-conditional). This is the bug.
- `br.bin` header **has** `content-encoding: br` → it may actually be fine and curl just didn't decode. Verify: `curl -s --compressed "$URL" | xmllint --noout - && echo "decodes OK"`. If it decodes, the client-side bug is elsewhere (cache/transient) — tell me, we pivot.

## BLOCK B — working vs broken, header-by-header
```bash
for u in /sitemap-gsc.xml /catalog/view/stylesheet/boostershop-ds.css; do
  echo "===================== $u ====================="
  curl -s -D - -H 'Accept-Encoding: br,gzip' "https://boostershop.website$u" -o /dev/null
done
```
**Read:** compare the two header sets. The CSS (works) vs sitemap (broken). Look specifically for differences in: `content-encoding`, `vary`, `cache-control` (note `no-transform`), `x-litespeed-cache`, `content-type`. The delta is the root cause fingerprint.

## BLOCK C — full client matrix
```bash
URL=https://boostershop.website/sitemap-gsc.xml
for ae in 'identity' 'gzip' 'br' 'br,gzip'; do
  for proto in '--http1.1' '--http2'; do
    printf 'AE=%-8s %s -> ' "$ae" "$proto"
    curl -s $proto -H "Accept-Encoding: $ae" "$URL" -o /tmp/m.bin -w 'size=%{size_download} '
    ( head -c 5 /tmp/m.bin | grep -q '<?xml' ) && echo 'XML-OK' || echo 'BINARY'
  done
done
echo "--- as Googlebot / InspectionTool ---"
curl -s -A 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' -H 'Accept-Encoding: br,gzip' "$URL" -o /tmp/g.bin; head -c 5 /tmp/g.bin | grep -q '<?xml' && echo 'Googlebot: XML-OK' || echo 'Googlebot: BINARY'
curl -s -A 'Google-InspectionTool/1.0' -H 'Accept-Encoding: br,gzip' "$URL" -o /tmp/i.bin; head -c 5 /tmp/i.bin | grep -q '<?xml' && echo 'InspectionTool: XML-OK' || echo 'BINARY'
```
**Read:** which exact combinations break. Tells us whether the trigger is `br`, `gzip`, HTTP/2, or UA.

## BLOCK D — is there a CDN/proxy in front?
```bash
curl -s -D - https://boostershop.website/sitemap-gsc.xml -o /dev/null \
 | grep -iE 'server:|via:|cf-ray|cf-cache|x-qc|quic|x-litespeed|x-cache|age:|content-encoding|vary'
```
**Read:** `cf-ray`/`cf-cache-status` = Cloudflare; `x-qc-*`/`quic` = QUIC.cloud; `via:` = some proxy. If a CDN is present, the host's origin change won't fix the edge → the fix (or a ticket) must target the CDN.

## BLOCK E — from INSIDE the server (bypass the edge)  ← key step
```bash
# origin loopback with the real Host header
curl -s -D - -H 'Host: boostershop.website' -H 'Accept-Encoding: br,gzip' \
  http://127.0.0.1/sitemap-gsc.xml -o /tmp/lo.bin -w 'size=%{size_download}\n'
head -c 5 /tmp/lo.bin | grep -q '<?xml' && echo 'ORIGIN: XML-OK' || echo 'ORIGIN: BINARY'
# real bytes on disk (sanity) — both files GSC/robots reference
ls -l /home2/boosters/public_html/sitemap-gsc*.xml; head -c 40 /home2/boosters/public_html/sitemap-gsc-2.xml; echo
# pre-compressed siblings that might be served instead?
ls -la /home2/boosters/public_html/sitemap-gsc*.xml.br /home2/boosters/public_html/sitemap-gsc*.xml.gz 2>/dev/null || echo 'no .br/.gz siblings'
# cached compressed object?
find /home2/boosters/ -path '*lscache*' -iname '*sitemap*' 2>/dev/null | head
```
**Branch (the decisive one):**
- **ORIGIN: XML-OK** but public = BINARY → the **edge/CDN** is the culprit (Hypothesis 3). Go to the hosting/CDN ticket.
- **ORIGIN: BINARY** too → it's **origin LiteSpeed/.htaccess** (Hypothesis 2). Go to Block F then G.

## BLOCK F — LiteSpeed cache purge test
1. cPanel → **LiteSpeed Web Cache Manager / LSCache → Purge All** (or purge the sitemap URL).
2. Re-run **Block A**. If it now serves XML-OK → a stale compressed object was cached; we just need the right cache-exclude (confirm it sticks after a few minutes and a second fetch).
3. Cache-buster cross-check: `curl -s "https://boostershop.website/sitemap-gsc.xml?v=tech005" -H 'Accept-Encoding: br,gzip' -o /tmp/v.bin; head -c5 /tmp/v.bin`.

## BLOCK G — decisive `.htaccess` experiment (Route 0)  ← needs your hands + backup
Only if Block E shows **ORIGIN: BINARY**. Backup already made in Block 0.
1. Edit `~/public_html/.htaccess`; **comment out (or delete) the whole block** from `# BEGIN sitemap-no-compression` to `# END sitemap-no-compression` (and the early `<FilesMatch "sitemap.*\.xml$">` SetEnv/Header blocks at the top). Save.
2. Immediately re-run **Block A** (identity + br).
3. Interpret:
   - XML now decodes on `br` (with proper `content-encoding`) or serves identity → **the old block was the cause.** Keep it removed; later add ONE minimal, correct rule if needed.
   - No change → not `.htaccess`; **restore** `cp .htaccess.bak-tech005deep-20260606 .htaccess` and focus on server/CDN (Block E/D result).
4. Always end Block G by confirming the site still loads (home + one product) and **restore the backup if anything looks off.**

## BLOCK H — define & verify "stable"
Once a combination serves readable XML on `br,gzip` for default + Googlebot + InspectionTool:
```bash
curl -s --compressed https://boostershop.website/sitemap-gsc.xml | xmllint --noout - && echo "VALID XML"
curl -s --compressed https://boostershop.website/sitemap-gsc.xml | grep -c '<loc>'   # expect >0 (≈ real URL count)
```
Then: **GSC → Sitemaps → resubmit `/sitemap-gsc.xml`**, watch Last-read date advance + status **Success**, discovered URLs > 0 (allow GSC lag, hours–days). Regression guard: re-run Block B and confirm CSS still shows `content-encoding` (compression still on for assets).

---

## Decision table (symptom → meaning → next)
| Observation | Meaning | Action |
|---|---|---|
| identity = XML, br = binary, **no** `content-encoding` | compressed-without-header, AE-conditional | Block E → F/G |
| br has `content-encoding: br`, `--compressed` decodes | not actually broken server-side | re-check the client/GSC path; maybe transient — resubmit GSC |
| ORIGIN (127.0.0.1) OK, public binary | edge/CDN re-compresses | CDN setting / hosting ticket (template below) |
| ORIGIN binary, removing `.htaccess` block fixes it | the old "fix" block was the cause | keep removed; minimal correct rule |
| ORIGIN binary, `.htaccess` removal no help | LiteSpeed compressible-types/server config | hosting ticket (template below) |
| `*.br`/`*.gz` sibling exists & served | stale pre-compressed file served raw | delete sibling / fix static-file precompress setting |

## Hosting-support ticket template (use only if it's a server/CDN setting we can't reach)
> Subject: sitemap.xml served brotli-compressed WITHOUT Content-Encoding header
>
> `GET https://boostershop.website/sitemap-gsc.xml` with `Accept-Encoding: br,gzip` returns `Content-Type: application/xml` but the body is **brotli-compressed and the response has no `Content-Encoding` header**, so clients (and Google Search Console) cannot decode it. Requests with `Accept-Encoding: identity` return correct XML, and `text/css`/`text/plain` responses are served correctly with a valid `Content-Encoding`. The issue is specific to `application/xml`/sitemap responses.
>
> Please either (a) **exclude `.xml` sitemap responses from compression** at the LiteSpeed (and any QUIC.cloud/CDN) layer, **or** (b) ensure they are served with the correct `Content-Encoding: br/gzip` header. Please confirm whether QUIC.cloud/any CDN is in front and whether the previous "compression disabled" change applied to `application/xml` and to the edge, not just origin HTML.

## Guardrails (per SEO risk gate — HIGH)
- Do NOT change the sitemap URL, the sitemap XML content, robots.txt content, canonical, redirects, checkout, Merchant feed, schema.
- Do NOT disable site-wide compression (CSS/JS/HTML must stay compressed — Block B is the regression guard).
- Every `.htaccess` change is backed up (Block 0) and reversible in one command.
- Run destructive/edit blocks (F, G) at low traffic; verify home + a product page after each.

## After we find it
- Apply the minimal fix → set TECH-005-DEEP = "На перевірці" (await GSC re-read) → Done only when GSC Sitemaps = Success, discovered > 0.
- That clears the "після sitemap stable" gate → proceed to TECH-012 (canonical) then TECH-010 (noindex). Hand Codex the result for review (bs-codex-review).
