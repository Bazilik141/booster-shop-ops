# HANDOFF TO CODEX — TECH-010 + TECH-012 (Noindex hygiene + Canonical policy)
_Date: 2026-06-09 · Prepared by: Claude · Recipient: Codex + Owner_
_Source backup: `backup-6.3.2026_21-17-59_boosters.tar.gz` · Live analysis: `sitemap-analysis-20260608.tar.gz`_
_Recommended model: **Opus + extended thinking** (wrong noindex = pages disappear from Google; wrong canonical = duplicate PageRank dilution)_

---

## SEO RISK GATE (preflight)

**Risk: HIGH.**

- noindex on wrong route → pages fall out of Google index silently, no 404, no signal.
- missing noindex on checkout/account → Google indexes private user flow pages (privacy + UX signal issue).
- wrong canonical or naked `?route=` in sitemap → PageRank split between clean and raw URLs.
- `.htaccess` edit (Sub-task C) → HIGH, must not break existing redirects. Owner approval required.

**Owner approval required: YES for Sub-task C (`.htaccess` edit). All other sub-tasks = Codex-safe with staging review.**

---

## 0. Gate status — sitemap stable ✓

TECH-005-DEEP is cleared. Confirmed 2026-06-09 via `sitemap-analysis-20260608.tar.gz`:

```
HTTP/2 200
content-type: application/xml; charset=UTF-8
content-length: 5006  ← real file, not compressed
```

No `content-encoding` header = identity response. `xmllint` = VALID. Gate condition "після sitemap stable" is met. TECH-010 + TECH-012 are unblocked.

---

## 1. Task IDs

| ID | Name | Scope |
|---|---|---|
| TECH-010 | Noindex hygiene | Add `<meta robots noindex>` in Twig for non-indexable routes |
| TECH-012 | Canonical & duplicate URL policy | 3 sub-tasks: manufacturer sitemap URLs, mobile menu raw routes, é-slug 301 |

---

## 2. Context

### robots.txt current state (live, from backup)

```
user-agent: *
Disallow: /*?page=$
Disallow: /*&page=$
Disallow: /*?sort=
Disallow: /*&sort=
Disallow: /*?order=
Disallow: /*&order=
Disallow: /*?limit=
Disallow: /*&limit=
Disallow: /*?filter_name=
Disallow: /*&filter_name=
Disallow: /*?filter_sub_category=
Disallow: /*&filter_sub_category=
Disallow: /*?filter_description=
Disallow: /*&filter_description=
Disallow: /*?filter_group=
Disallow: /*&filter_group=
Sitemap: https://boostershop.website/uk-ua/sitemap.xml
```

What's missing: no `Disallow: /index.php` (raw route URLs reachable by crawlers), `Sitemap:` points to dynamic route instead of `sitemap-gsc.xml`.

**robots.txt is a protected zone — Codex does NOT edit it. Owner handles robots.txt manually (see Section 7, Manual steps).**

### TECH-012 sub-items — known issues from 2026-06-06 analysis

**B1 — Manufacturer URLs in sitemap with `?route=` tail:**
```
https://boostershop.website/pokemon-company?route=product/manufacturer.info
https://boostershop.website/Bandai?route=product/manufacturer.info
https://boostershop.website/Konami?route=product/manufacturer.info
https://boostershop.website/Wizards-of-the-Coast?route=product/manufacturer.info
```
Source: `ps_google_sitemap` extension, `feed_ps_google_sitemap_manufacturer = 1` (enabled in DB). The model generates manufacturer URLs by appending `?route=product/manufacturer.info` to the clean alias, creating URLs Google may have indexed alongside the clean versions.

**B2 — Mobile burger menu raw `index.php?route=...` links:**
Mobile navigation links to `index.php?route=product/special`, `index.php?route=product/category&path=59`, etc. Footer uses clean SEO URLs via `$this->url->link()`. Google has indexed the raw route versions. Clean URLs and raw URLs coexist in the index → duplicate signals.

**B3 — Non-ASCII é-slug:**
`https://boostershop.website/product/Pokémon-Trainer-Box-SVP` (URL-encoded: `Pok%C3%A9mon-Trainer-Box-SVP`) was indexed by Google. The product now lives at `https://boostershop.website/product/Pokemon-Trainer-Box-SVP` (ASCII, no é). A 301 is needed to signal Google the correct canonical.

**B4 — 9 product slug renames (2026-06-06) — already done in DB:**
Slugs renamed from SKU-style to descriptive. Confirmed in `sitemap-full.xml` (lastmod 2026-06-07). GSC manual submission is the remaining action (see `gsc-indexing-checklist_2026-06-06.md`). No Codex work required.

### DB settings confirmed (from live DB query, 2026-06-08):
```
config_compression = 0
feed_ps_google_sitemap_manufacturer = 1   ← manufacturers included in dynamic sitemap
feed_ps_google_sitemap_product = 1
feed_ps_google_sitemap_category = 1
feed_ps_google_sitemap_information = 1
feed_ps_google_sitemap_status = 1
```

---

## 3. What to change

### Sub-task A — TECH-010: noindex meta tags

**Goal:** Add `<meta name="robots" content="noindex, nofollow">` in `<head>` for routes that should never be indexed.

**Step 0 — DB check before editing:**
```sql
SELECT * FROM ocp5_theme WHERE filename IN (
  'common/header.twig',
  'common/head.twig'
);
```
If rows exist → edit the twig content stored in DB (not the file). If no rows → edit the file directly at `catalog/view/template/common/header.twig`.

**Step 1 — Identify head template:**
In OpenCart 4, the `<head>` block may be in `header.twig` or a separate `head.twig`. Locate where the existing `<meta charset>` and `<title>` tags are rendered. That is the file to edit.

**Step 2 — Add noindex block:**

Find the location just after the `<meta charset="UTF-8">` tag or just before `</head>`. Insert:

```twig
{% if route in [
  'checkout/cart',
  'checkout/checkout',
  'checkout/confirm',
  'checkout/success',
  'account/login',
  'account/register',
  'account/account',
  'account/logout',
  'account/order',
  'account/order/info',
  'account/address',
  'account/address/add',
  'account/address/edit',
  'account/password',
  'account/wishlist',
  'account/edit',
  'account/newsletter',
  'product/search',
  'product/compare',
  'information/contact'
] %}
<meta name="robots" content="noindex, nofollow">
{% endif %}
```

**Step 3 — Verify `route` variable is available in scope:**
In OpenCart 4, `route` is passed to the Twig context by the base controller. Confirm by searching the template for existing usage of `route` (there may already be conditional logic using it). If `route` is not available in the head template directly, pass it from the `common/header.php` controller via `$data['route']`.

**Step 4 — Cache bust in header.twig:**
If editing `header.twig`, update the stylesheet link version string:
```twig
{# Find: ?v=rd09-trust-strip-20260609 (or current version) #}
{# Change to: ?v=tech010-noindex-20260609 #}
```

---

### Sub-task B1 — TECH-012: Fix manufacturer URLs in ps_google_sitemap

**File to inspect:**
```
/home2/boosters/public_html/extension/ps_google_sitemap/catalog/model/feed/ps_google_sitemap.php
```

**Step 0 — Read the model file in full.** Find the section that generates manufacturer URLs. Look for code similar to:
```php
$data['manufacturers'][] = array(
    'loc' => $this->url->link('product/manufacturer.info', 'manufacturer_id=' . $manufacturer['manufacturer_id'])
);
```

Or it may manually build the URL as:
```php
$url = $manufacturer['keyword'] . '?route=product/manufacturer.info';
```

**Step 1 — Fix based on what you find:**

Option A (if `$this->url->link()` generates clean URL but appends route): check if the model is calling `$this->url->link()` and then concatenating `?route=...` separately. Remove the manual concatenation.

Option B (if the model builds URLs manually from keyword + route): replace with:
```php
$loc = $this->url->link('product/manufacturer.info', 'manufacturer_id=' . $manufacturer['manufacturer_id']);
```

The correct output should be `https://boostershop.website/pokemon-company` (clean alias only, no `?route=` tail).

**Step 2 — Verify clean URL output:**
After fix, fetch `https://boostershop.website/uk-ua/sitemap.xml` and check that manufacturer `<loc>` entries no longer contain `?route=product/manufacturer.info`.

**Note:** Do NOT disable `feed_ps_google_sitemap_manufacturer`. The goal is clean URLs in the feed, not removal of manufacturers from the sitemap.

---

### Sub-task B2 — TECH-012: Fix mobile menu raw route URLs

**Step 0 — Locate the mobile menu template:**
```bash
grep -rn "index\.php?route=" /home2/boosters/public_html/catalog/view/template/common/ 2>/dev/null
grep -rn "index\.php?route=" /home2/boosters/public_html/catalog/view/template/common/nav.twig 2>/dev/null
# Also check theme overrides:
SELECT filename, code FROM ocp5_theme WHERE filename LIKE '%common%' OR filename LIKE '%nav%';
```

**Step 1 — Check what raw routes are hardcoded:**
Expected to find URLs like:
- `index.php?route=product/special`
- `index.php?route=product/category&path=59`

**Step 2 — Fix approach:**
In OpenCart 4 Twig, URLs should be passed from the controller as `{{ specials }}`, `{{ categories }}`, etc. — not built raw in the template. Two possible fixes:

Option A (if the controller already passes clean URL variables): replace the hardcoded `index.php?route=...` strings with the correct Twig variable (e.g. `{{ special }}`). Inspect what the header/nav controller passes as `$data`.

Option B (if no clean URL variable exists for that link): add the variable in the controller:
```php
// In catalog/controller/common/header.php (or nav.php)
$data['url_special'] = $this->url->link('product/special');
```
Then use `{{ url_special }}` in the template.

**Note on mobile nav context:** The "burger menu" is part of the mobile-responsive layout. It may reuse the same `nav.twig` with different CSS visibility, or it may be a separate `mobile-menu.twig` block. Codex must identify the correct file via the grep step before editing.

**Critical:** Do not break the desktop navigation while fixing mobile. Check that existing `{{ category.href }}` and similar variables are preserved.

---

### Sub-task C — TECH-012: é-slug 301 redirect (OWNER APPROVAL REQUIRED)

**The problem:**
`https://boostershop.website/product/Pokémon-Trainer-Box-SVP` (URL-encoded: `Pok%C3%A9mon-Trainer-Box-SVP`) may be indexed by Google. The current live URL is `https://boostershop.website/product/Pokemon-Trainer-Box-SVP` (ASCII, no é).

**Risk level: HIGH** — `.htaccess` edit. If the RewriteRule is malformed, it can break routing for all product pages.

**Owner must approve before Codex proceeds.**

**Fix — add ONE targeted rule to `.htaccess`, inside the existing rewrite block, BEFORE the catch-all `RewriteRule ^([^?]*)` line:**

```apache
# TECH-012C: 301 for non-ASCII é-slug, 2026-06-09
RewriteRule ^product/Pok%C3%A9mon-Trainer-Box-SVP$ /product/Pokemon-Trainer-Box-SVP [R=301,L]
```

**Placement:** Insert immediately before the line:
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^?]*) index.php?_route_=$1 [L,QSA]
```

**Backup before edit:**
```bash
cp /home2/boosters/public_html/.htaccess /home2/boosters/public_html/.htaccess.bak-tech012c-20260609
```

**Verify after:**
```bash
curl -sS -D- "https://boostershop.website/product/Pok%C3%A9mon-Trainer-Box-SVP" -o /dev/null | grep -i "location\|http/"
# Expected: HTTP/2 301 + Location: https://boostershop.website/product/Pokemon-Trainer-Box-SVP
```

---

## 4. Do NOT touch

- `sitemap-gsc.xml`, `sitemap.xml`, `robots.txt` — protected zones, no edits.
- Existing `RewriteRule` for `https://` redirect (L93–95) and OpenCart routing catch-all (L103–105) — do not move, do not duplicate.
- `checkout/*`, payment, fiscalization, Merchant feed, schema (JSON-LD) — zero scope.
- `ps_google_sitemap` extension DB settings — do NOT change. Only fix the model's URL generation.
- Do NOT add `noindex` to: home (`common/home`), product pages (`product/product`), category pages (`product/category`), information pages (`information/information`).
- `boostershop-ds.css` — no changes in this task.

---

## 5. Execution order

Run in this sequence. Do NOT skip ahead.

```
1. DB check (ocp5_theme for header/head twig overrides)          ← always first
2. Sub-task A — noindex meta in header/head twig                 ← Codex
3. Sub-task B1 — ps_google_sitemap manufacturer URL fix          ← Codex
4. Sub-task B2 — mobile menu raw route fix                       ← Codex (diagnose first)
5. Sub-task C — .htaccess é-slug 301                             ← Owner approves → Codex
6. Manual: Owner updates robots.txt (see Section 7)              ← Owner only
```

---

## 6. Acceptance criteria

**TECH-010:**
- `curl -sS "https://boostershop.website/index.php?route=checkout/cart" | grep -i "robots"` → returns `<meta name="robots" content="noindex, nofollow">`
- Same check for `?route=account/login` and `?route=product/search` → noindex present.
- `curl -sS "https://boostershop.website/" | grep -i "robots"` → **no** noindex meta (home must remain indexable).
- `curl -sS "https://boostershop.website/catalog/Pokemon" | grep -i "robots"` → **no** noindex meta.

**TECH-012/B1:**
- `curl -sS "https://boostershop.website/uk-ua/sitemap.xml" | grep manufacturer` → zero occurrences of `?route=product/manufacturer.info`. Manufacturer `<loc>` entries show clean alias URLs only.

**TECH-012/B2:**
- View `https://boostershop.website` on mobile (DevTools 390px). Open burger menu. Right-click any category or special link → copy URL → no `index.php?route=` in the href.

**TECH-012/C:**
- `curl -sS -D- "https://boostershop.website/product/Pok%C3%A9mon-Trainer-Box-SVP" -o /dev/null` → `HTTP/1.1 301` or `HTTP/2 301`, `Location: https://boostershop.website/product/Pokemon-Trainer-Box-SVP`.
- Follow redirect: `curl -sS -L "https://boostershop.website/product/Pok%C3%A9mon-Trainer-Box-SVP" | grep -i "<title>"` → returns the product page title (not 404).

---

## 7. Manual steps (owner only, not Codex)

After Codex execution:

**robots.txt — 2 edits (owner edits file directly via cPanel File Manager or SFTP):**

1. Change `Sitemap:` line:
   ```
   # Before:
   Sitemap: https://boostershop.website/uk-ua/sitemap.xml
   # After:
   Sitemap: https://boostershop.website/sitemap-gsc.xml
   ```
   Reason: `sitemap-gsc.xml` is the static, GSC-ready file. The dynamic `uk-ua/sitemap.xml` served as the source for its construction and is a fallback. GSC should read the static version.

2. Add after the last `Disallow:` line:
   ```
   Disallow: /index.php
   ```
   Reason: blocks raw `index.php?route=...` URLs from crawl. Does not affect clean SEO URLs (the catch-all rewrite handles routing invisibly).

**GSC re-submit after all fixes:**
- Re-submit `sitemap-gsc.xml` in GSC Sitemaps.
- Use the GSC URL Inspection tool to request re-indexing for the 4 manufacturer pages.
- Submit `https://boostershop.website/product/Pokemon-Trainer-Box-SVP` (ASCII version) after the 301 is live.

---

## 8. QA checklist

Run all curl checks from Section 6, then:

- [ ] Home page: `<meta robots>` absent (indexable).
- [ ] `/catalog/Pokemon`: `<meta robots>` absent (indexable).
- [ ] `/product/Pokemon-boosters-White-Flare`: `<meta robots>` absent (indexable).
- [ ] `/?route=checkout/cart`: `<meta robots noindex, nofollow>` present.
- [ ] `/?route=account/login`: `<meta robots noindex, nofollow>` present.
- [ ] `/?route=product/search`: `<meta robots noindex, nofollow>` present.
- [ ] Dynamic sitemap (`/uk-ua/sitemap.xml`) manufacturer `<loc>` = clean aliases, no `?route=` tail.
- [ ] Desktop navigation links unchanged (no regression in desktop menu URLs).
- [ ] Mobile burger menu: all category/special links = clean SEO URLs.
- [ ] é-slug 301 resolves to ASCII product page (no redirect loop, no 404).
- [ ] Main `RewriteRule ^([^?]*)` catch-all still works — `/catalog/Pokemon` returns 200.
- [ ] `https://` redirect still works — `http://boostershop.website` → 301 → `https://`.
- [ ] Checkout flow: add item to cart → proceed to checkout → no 404, no redirect loop.

---

## 9. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| noindex on wrong route (e.g., `product/product`) | **Critical** — product pages drop from Google | Whitelist approach: only named routes get noindex; all others default to indexable |
| Route variable not available in head template | Noindex block silently ignored | Step 3 in Sub-task A verifies `route` is in scope before merging |
| `.htaccess` RegExp error in é-slug rule | All product pages broken | Rule is self-contained with `[L]` flag; backup mandatory; test on staging or verify with `curl` immediately |
| ps_google_sitemap model edit breaks dynamic sitemap | 0 URLs returned | Validate dynamic sitemap XML after fix; rollback = restore model from backup tar |
| Mobile menu fix breaks desktop navigation | Desktop nav returns 404 or wrong URLs | Grep desktop nav template separately; confirm it uses `{{ category.href }}` variables, not hardcoded routes |
| Codex cannot find mobile menu template | B2 stays incomplete | Acceptable: B2 is a crawl-deduplication fix, not a critical ranking factor. Can defer to separate task. |

---

## 10. Rollback

- Sub-task A (Twig): restore `ocp5_theme` row (if DB-stored) or restore twig file from backup tar. No DB data changed.
- Sub-task B1 (model): restore `extension/ps_google_sitemap/catalog/model/feed/ps_google_sitemap.php` from backup tar.
- Sub-task B2 (nav twig): restore from `ocp5_theme` row or backup tar.
- Sub-task C (.htaccess): `cp .htaccess.bak-tech012c-20260609 .htaccess`.

Source for all rollbacks: `backup-6.3.2026_21-17-59_boosters.tar.gz`.

---

## 11. Status after execution

- Codex execution complete + curl checks passing → flip both **TECH-010** and **TECH-012** to **"На перевірці"**.
- GSC re-crawl confirms noindex pages absent from index + manufacturer clean URLs appear → **Done**.
- Do NOT flip to Done on same day as execution; allow 24–48h for GSC to re-crawl.

---

_Confirmed gate: TECH-005-DEEP cleared 2026-06-09 (static_headers.txt: HTTP/2 200, content-length: 5006, no Content-Encoding, VALID XML). Both tasks are unblocked._
_No bs-checkout-smoke needed (checkout routes are being noindex-tagged, not modified functionally). bs-seo-risk-gate: HIGH — treat accordingly._
