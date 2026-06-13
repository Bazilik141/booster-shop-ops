# TECH-005-DEEP — session outcome + contingency host ticket
_Session 2026-06-06 (hands-on, owner + Claude). Server: LiteSpeed, OpenCart 4, /home2/boosters/public_html/, IP 45.94.156.222 (HostIQ / twinservers, NS hostiq.ua). No CDN._

## What we proved
- **No CDN in front.** Public A-record = hosting IP (45.94.156.222), NS `hostiq.ua`, no `cf-ray`/`x-qc`/`age` headers.
- **Origin serves the sitemap CORRECTLY** to HTTP/1.1 (external Windows curl) and HTTP/2 (server curl), even with `Accept-Encoding: br,gzip`: `200`, `application/xml`, `Content-Length 5006`, **no** `Content-Encoding`, valid XML. The host's compression-disable + `.htaccess` works for h1/h2.
- **Googlebot's crawl path works:** owner ran GSC URL Inspection on a never-indexed page `https://boostershop.website/product/pokemon-tcg-nabory-MBOX` — Google fetched it and offered indexing. So Google can fetch the site.
- **Residual:** some client paths (our external fetcher; suspected **HTTP/3/QUIC**, which `alt-svc` advertises heavily) still receive brotli-compressed-**without**-`Content-Encoding` binary **for `application/xml`**. `text/css` and `text/plain` decode fine. Could not test h3 directly (no h3-capable curl available: server curl 7.61 and Windows curl 8.13 both lack HTTP3). Given Google fetched a page fine, this is **likely not what Googlebot sees**.
- **The GSC "Не вдалось отримати" (last read 2026-05-25) is stale** — it predates the host's compression fix.

## Sitemap content decision
- **Static `sitemap-gsc-2.xml` is stale:** 22 URLs, dated May; **missing all newly-added products** (incl. MBOX).
- **Dynamic `/uk-ua/sitemap.xml` (ps_google_sitemap) is the better source:** 51 URLs, `lastmod` fresh through 2026-06-06, includes MBOX, valid XML, auto-updates with new products. Content QA: 31 product + clean `/catalog/...` categories + 5 info pages + home = canonical. **One wrinkle:** 4 manufacturer URLs carry a `?route=product/manufacturer.info` suffix (non-canonical) — logged as TECH-012 follow-up, non-blocking (manufacturer.php sets a clean canonical).

## Actions taken 2026-06-06
- `robots.txt`: `Sitemap:` line → `https://boostershop.website/uk-ua/sitemap.xml`. Backup `robots.txt.bak-tech005-20260606`; `diff` = only that line; verified live. `Disallow` rules untouched.
- `.htaccess`: backup `.htaccess.bak-tech005deep-20260606` made. **No `.htaccess` content change** — origin already serves correctly for h1/h2.

## Pending (owner, in GSC)
- Submit `uk-ua/sitemap.xml`; remove old `sitemap-gsc.xml` + `sitemap-gsc-2.xml` entries.
- Watch Sitemaps report: **Last read** advances to 2026-06-06+ and status = **Success**, discovered URLs > 0 (hours–days).

## Decision gate
- **If GSC reads the dynamic sitemap → TECH-005 / TECH-005-DEEP = Done.** The "після sitemap stable" gate clears → proceed to **TECH-012** (canonical) then **TECH-010** (noindex).
- **If GSC still "Не вдалось отримати" after a fresh re-read date → the residual QUIC/HTTP3 issue is real for Google → send the ticket below.**

## Follow-ups (not blocking the sitemap)
- **TECH-012:** the 4 manufacturer `?route=product/manufacturer.info` URLs in the dynamic sitemap are non-canonical duplicates — fix via ps_google_sitemap config or manufacturer URL handling.
- Confirm Yu-Gi-Oh / Magic-the-Gathering / Konami / Wizards-of-the-Coast sections are populated; if empty, exclude from sitemap (thin/empty pages).
- After dynamic sitemap confirmed in GSC, the unused static `sitemap-gsc.xml` / `sitemap-gsc-2.xml` can be removed.
- HTTP/1.1/2 are confirmed clean; consider whether HTTP/3 needs the host fix below regardless, for browser users.

---

## Contingency hosting ticket (send only if GSC re-read still fails)
> **Тема:** application/xml (sitemap) віддається стиснутим без заголовка Content-Encoding на окремому шляху (ймовірно HTTP/3/QUIC)
>
> Домен: boostershop.website (акаунт на uashared43, IP 45.94.156.222). Сервер LiteSpeed.
>
> Запит `GET https://boostershop.website/uk-ua/sitemap.xml` (а також `/sitemap-gsc-2.xml`) з `Accept-Encoding: br,gzip` для частини клієнтів повертає тіло, стиснуте Brotli, **без заголовка `Content-Encoding`**, через що клієнт/Google Search Console бачить binary і не може розпарсити XML.
>
> Перевірено: з самого сервера (curl HTTP/2) і з зовнішнього клієнта по HTTP/1.1 відповідь **коректна** — `200`, `application/xml`, `Content-Length: 5006`, без `Content-Encoding`, валідний XML. `text/css` і `text/plain` теж віддаються коректно (зі стисненням і правильним заголовком). Проблема специфічна для `application/xml` і, схоже, для шляху HTTP/3/QUIC (`alt-svc` рекламує h3). У `.htaccess` уже стоять `SetEnv no-brotli/no-gzip` для sitemap — для h1/h2 працює, але не для цього шляху.
>
> **Прохання:** забезпечити, щоб відповіді `application/xml` (sitemap) на ВСІХ протоколах, включно з HTTP/3/QUIC, віддавалися або без стиснення, або зі стисненням і коректним заголовком `Content-Encoding: br/gzip`. Як варіант — вимкнути Brotli для `application/xml` на рівні LiteSpeed/QUIC для цього vhost, або тимчасово вимкнути HTTP/3 для домену. Підтвердьте, будь ласка, чи попереднє «вимкнення стиснення» застосувалося до `application/xml` і до QUIC-шляху, а не лише до HTML.
