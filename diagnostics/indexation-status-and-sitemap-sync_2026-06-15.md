# Indexation status + sitemap reconciliation — 2026-06-15
_Sources: GSC Coverage export 2026-06-15 · 10-day access-log analysis · cross-ref with dialog "Booster Shop unindexed pages"._

## Headline
- **Indexed pages: 38** (from ~8 at the start). Strong growth — **driven by manual URL-Inspection submissions + active crawl, NOT the sitemap.**
- Coverage breakdown (to 12.06): **0 "duplicate without canonical" (PASSED)**, 2 noindex (intended), 2 canonical-alternate, 7 crawled-not-indexed.
- **Googlebot is very active**: hundreds of hits/day (spike 1662 on 07.06 ≈ redesign deploy; steady 330–500/day since 10.06). It re-rendered the Hot-Wind-Arena product page (full JS render) on 14.06 → actively re-evaluating the not-indexed set.

## Sitemap reconciliation (important — syncs the two dialogs)
- **Canonical sitemap is now `https://boostershop.website/sitemap-full.xml`** (static, clean, daily cron-regenerated, robots points to it, GSC has only this one). Root cause of the old "couldn't read" was our own `.htaccess` `no-store/no-transform` block — removed (see TECH-005-DEEP).
- The "unindexed pages" dialog still referenced the **dynamic `/uk-ua/sitemap.xml`** (which still contains the 4 `?route=product/manufacturer.info` dupes). That is **no longer the advertised sitemap** — our cron strips those dupes from `sitemap-full.xml`. So the 2 "canonical-alternate" in GSC are leftovers Google already crawled; they will settle and are not re-fed.
- **As of 2026-06-15 Googlebot still has NOT fetched `sitemap-full.xml`** (reads robots.txt 120×, so it knows about it; its sitemap-processing queue is just slow after the long failure history). **This is not blocking indexation** — manual submission + internal-link crawl are doing the work.
- Lever to force a sitemap fetch (optional): GSC → Sitemaps → remove `sitemap-full.xml` → re-add it (resets Google's backoff).

## The 7 crawled-not-indexed URLs (from GSC export)
| URL | Type | Last crawl | Note |
|---|---|---|---|
| `/?route=product/special&language=uk-ua` | garbage route URL | Apr 2026 | From mobile/burger menu (TECH-012/B2). Correctly NOT indexed; wastes crawl budget. Fix: B2 menu + `Disallow: /index.php` in robots. |
| `/catalog/more-tcg/Yu-Gi-Oh` | category | 13.06 | Thin? few products. |
| `/product/Yu-Gi-Oh-boosters-World-Premiere-Pack-2024` | product (renamed) | 13.06 | New slug, niche TCG in UA. |
| `/product/Yu-Gi-Oh-boosters-Quarter-Century-Art-Collection` | product (renamed) | 13.06 | New slug, niche. |
| `/product/Magic-the-Gathering-Adventures-in-the-Forgotten-Realms` | product (renamed) | 13.06 | New slug, MTG less popular in UA. |
| `/product/Pokemon-boosters-Hot-Wind-Arena` | product | 13.06 (re-rendered 14.06) | KR product. |
| `/product/Pokemon-boosters-Mega-Brave` | product | 13.06 | In evaluation queue. |

6 of 7 were crawled only ~2 days before the export → "crawled-not-indexed" at 48h is **normal**, not a problem. Risk factor: thin/templated content on YGO/MTG + freshly-renamed slugs.

## Crawl-budget note
Top crawled URLs are dominated by **static assets** (CSS/JS/fonts/images), largely due to **redesign cache-bust churn** — the same file appears under many `?v=` (rd07, rd0607, rd10-breadcrumb, rd10-buyrow, bstlh1…). Each new version = a new URL Googlebot re-downloads. Dilutes crawl budget. → tighten asset versioning (TECH-002 / CWV family).

## Next actions (consolidated)
1. **Keep manual URL-Inspection submission** by priority (the proven driver of +30).
2. **Wait ~2 weeks (≈29.06)** on the 6 product/category pages; if still out → content audit of YGO/MTG/renamed-slug descriptions (length, uniqueness), then re-request.
3. **TECH-012/B2** — fix burger raw `index.php?route=` links + add `Disallow: /index.php` to robots → removes the `/?route=product/special` garbage URL.
4. **TECH-012/B1** — manufacturer sitemap generator still emits `?route=` dupes; now **cosmetic** (sitemap-full.xml strips them), low priority.
5. **TECH-010 tweak** — noindex is `noindex,follow`; handoff intended `noindex,nofollow`. Minor.
6. **Sitemap** — leave as-is (correct, not blocking); optionally remove/re-add in GSC to force fetch.
7. **TECH-002** — reduce asset-version churn to stop crawl-budget waste.

_Bottom line: indexation is healthy and growing without the sitemap; the sitemap is correct and will be picked up eventually. Focus stays on manual submission + content quality for the niche pages._
