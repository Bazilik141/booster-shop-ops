# TECH-013 — `curl -sI` header baselines (pre-WP1)

Captured: **2026-08-05 13:18 UTC** · by Claude Code, before any TECH-013 patch was written.
Purpose: the reference every TECH-013 deploy is compared against, per handoff §2.2, §7 and §10.

**Nothing on production had been modified when these were taken.** WP1 does not touch
`.htaccess`, so these must stay unchanged through WP1, WP4 and WP2 as well as WP3.

## How to compare

Re-run and diff **ignoring `Date`, `Keep-Alive` and `alt-svc`** — those vary per request and
are not part of the acceptance bar. Everything else must match byte for byte.

```bash
curl -sI https://boostershop.website/sitemap-full.xml | grep -vE '^(Date|Keep-Alive|alt-svc):'
```

```bash
curl -sI https://boostershop.website/robots.txt | grep -vE '^(Date|Keep-Alive|alt-svc):'
```

---

## PROTECTED — must be byte-identical after every deploy

### `/sitemap-full.xml`

```
HTTP/1.1 200 OK
Connection: Keep-Alive
Keep-Alive: timeout=5, max=100
Content-Type: application/xml; charset=UTF-8
Last-Modified: Wed, 05 Aug 2026 01:15:01 GMT
Accept-Ranges: bytes
Content-Length: 20891
Date: Wed, 05 Aug 2026 13:18:42 GMT
Server: LiteSpeed
alt-svc: h3=":443"; ma=2592000, h3-29=":443"; ma=2592000, h3-Q050=":443"; ma=2592000, h3-Q046=":443"; ma=2592000, h3-Q043=":443"; ma=2592000, quic=":443"; ma=2592000; v="43,46"
```

Invariants: **no `Cache-Control`**, **no `Expires`**, **no `Content-Encoding`**,
`Content-Type: application/xml; charset=UTF-8` (set by the frozen `.htaccess:2–5`
`<FilesMatch>` block), `Content-Length: 20891`.

Any `Cache-Control` or `Expires` appearing here after WP3 means the new block leaked onto
`*.xml` → **revert WP3 immediately** (handoff §2.2).

### `/robots.txt`

```
HTTP/1.1 200 OK
Connection: Keep-Alive
Keep-Alive: timeout=5, max=100
Content-Type: text/plain
Last-Modified: Fri, 03 Jul 2026 17:07:30 GMT
Accept-Ranges: bytes
Content-Length: 467
Date: Wed, 05 Aug 2026 13:18:42 GMT
Server: LiteSpeed
alt-svc: h3=":443"; ma=2592000, h3-29=":443"; ma=2592000, h3-Q050=":443"; ma=2592000, h3-Q046=":443"; ma=2592000, h3-Q043=":443"; ma=2592000, quic=":443"; ma=2592000; v="43,46"
```

Invariants: **no `Cache-Control`**, **no `Expires`**, `Content-Type: text/plain`,
`Content-Length: 467`. `.txt` must not be in WP3's extension list.

---

## WP3 target assets — "before" record

These are the three assets named in handoff §7. They are **expected to change** after WP3
(and only after WP3).

| Asset | `Cache-Control` now | `Content-Length` | §7 target |
|---|---|---|---|
| `jquery-3.7.1.min.js` | **absent** | 132,165 | `max-age=2592000`+ |
| `bootstrap.css` | `public, max-age=604800` | 270,584 | `max-age=2592000`+ |
| `fa-solid-900.woff2` | **absent** | 158,224 | `max-age=2592000`+ |

### `jquery-3.7.1.min.js`
```
Content-Type: text/javascript
Last-Modified: Mon, 24 Mar 2025 22:42:26 GMT
Content-Length: 132165
```
No `Cache-Control`. No `Expires`.

### `bootstrap.css`
```
Cache-Control: public, max-age=604800
Expires: Wed, 12 Aug 2026 13:18:42 GMT
Content-Type: text/css
Last-Modified: Mon, 24 Mar 2025 22:42:26 GMT
Content-Length: 270584
```

### `fa-solid-900.woff2`
```
Content-Type: font/woff2
Last-Modified: Mon, 24 Mar 2025 22:42:26 GMT
Content-Length: 158224
```
No `Cache-Control`. No `Expires`.

---

## Findings from the baseline

1. **§2A's "selective, not global" TTL claim is confirmed, and now precisely scoped.**
   `bootstrap.css` carries `public, max-age=604800` (7 days); `.js` and `.woff2` carry
   nothing at all. Since orientation established `.htaccess` has **no** cache policy
   (§5A.3), this 7-day TTL is applied at the LiteSpeed/cPanel server level and appears to
   be **`text/css` only**. WP3's block will therefore *override* an existing server value
   for CSS while *creating* one for JS/fonts — worth an explicit `curl -sI` re-check on
   `bootstrap.css` after WP3, not just on the two assets that currently lack a header.

2. **`Content-Length` equals on-disk size on every asset** (270,584 / 158,224 / 132,165),
   because `curl -sI` sends no `Accept-Encoding`. These are **not** evidence about
   compression. Do not read them as a gzip regression signal.

3. **`fa-solid-900.woff2` is 158,224 B with no caching at all** — re-downloaded on every
   visit. Combined with its `font-display:block` (WP4), this single file is the most
   expensive font on the site.

4. `Server: LiteSpeed` on all responses, consistent with the LSCACHE block at
   `.htaccess:16–45`.

---

_Read-only `HEAD` requests only. No production file was modified. Nothing committed._
