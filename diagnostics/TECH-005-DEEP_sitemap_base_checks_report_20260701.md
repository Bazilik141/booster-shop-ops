# Codex Report — TECH-005-DEEP: базові причини помилки sitemap

Date: 2026-07-01

## Scope

Read-only перевірка поширених причин `Couldn't fetch / Couldn't read sitemap`.
Сайт, БД, `.htaccess`, `robots.txt`, sitemap і GSC не змінювалися.

Новий screenshot показує `Last read: 01.07.26`, `Found pages: 0` і
`Не вдалося прочитати файл Sitemap`. Це нова спроба GSC, а не старий статус
від 15 червня.

## Result

| Перевірка | Результат | Доказ |
|---|---|---|
| HTTP status / Content-Type | PASS | точний HTTPS URL → `200 OK`; `Content-Type: application/xml; charset=UTF-8` |
| `X-Robots-Tag: noindex` | PASS | заголовок відсутній; `robots.txt` не блокує sitemap |
| UTF-8 BOM | PASS | перші байти `3c 3f 78 6d 6c` = `<?xml`; BOM `EF BB BF` відсутній |
| Символи перед XML / null bytes | PASS | `<?xml` починається з offset 0; null bytes = 0; заборонені XML control bytes = 0 |
| Розмір / кількість URL | PASS | 14,318 bytes; 57 унікальних URL; ліміти 50 MB / 50,000 URL |
| SSL / TLS | PASS | hostname verified; TLS 1.3; valid Let's Encrypt certificate до 2026-09-02 |
| Redirect на submitted URL | PASS | `https://boostershop.website/sitemap-full.xml` одразу повертає `200`, без redirect |
| Namespace | PASS | root = `{http://www.sitemaps.org/schemas/sitemap/0.9}urlset` |
| `lastmod` | PASS | 40/40 timestamps парсяться як timezone-aware W3C/RFC3339; future dates = 0 |

## Raw XML checks

```text
bytes=14318
first_32_hex=3c3f786d6c2076657273696f6e3d22312e302220656e636f64696e673d225554
utf8_bom=False
starts_exact_xml_decl=True
bytes_before_xml_decl=0
null_bytes=0
forbidden_xml_control_bytes=0
crlf_count=0
lf_count=334
bare_cr_count=0
xml_declaration=<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
url_nodes=57
unique_locs=57
lastmod_nodes=40
invalid_lastmod=[]
future_lastmod=[]
max_lastmod=2026-06-30T15:13:44+03:00
```

LF-only line endings are valid XML. Offset `+03:00` у `lastmod` також валідний;
W3C datetime не вимагає саме `+00:00`.

## Compression checks

### Identity

```text
HTTP/1.1 200 OK
Content-Type: application/xml; charset=UTF-8
Content-Length: 14318
```

### Gzip / Googlebot UA

```text
Content-Encoding: gzip
Vary: Accept-Encoding
Content-Length: 2167
decoded_bytes=14318
decoded_equals_identity=True
```

### Brotli

```text
Content-Encoding: br
Vary: Accept-Encoding
Content-Length: 1832
decoded_bytes=14318
decoded_equals_identity=True
```

SHA-256 декодованого identity/gzip/Brotli однаковий:

```text
e2a449a473b80af373711a5ac30f31dd7e70182cb35fa5208b971bb3cb0fbecd
```

Стара помилка «Brotli body без `Content-Encoding`» зараз не відтворюється.

## Redirect checks

```text
https://boostershop.website/sitemap-full.xml
→ 200 OK, redirect count 0

http://boostershop.website/sitemap-full.xml
→ 301 https://boostershop.website/sitemap-full.xml

https://www.boostershop.website/sitemap-full.xml
→ 301 https://boostershop.website/sitemap-full.xml
```

GSC має submitted exact canonical HTTPS URL, тому HTTP/www redirects не стоять
на його шляху.

## TLS checks

```text
hostname_verified=true
TLS=TLSv1.3
cipher=TLS_AES_256_GCM_SHA384
subject=boostershop.website
issuer=Let's Encrypt YR2
valid_from=2026-06-04 16:58:12 GMT
valid_to=2026-09-02 16:58:11 GMT
SAN=*.boostershop.website, boostershop.website
```

Успішний default trust-store handshake підтверджує валідний certificate chain.

## Conclusion

Жодна з восьми поширених причин не пояснює нову помилку GSC. Файл коректний
на рівні байтів, XML, headers, compression, redirect і TLS.

Оскільки GSC уже змінив `Last read` на 01.07.26, наступний вирішальний доказ —
точний access-log рядок його нової спроби. Backup від 10:11 був створений до
цієї спроби або не містить її, тому він не показує новий fetch.

## Next diagnostic step

Потрібен вузький server log, без нового повного backup:

```bash
grep -h 'sitemap-full.xml' \
  ~/logs/boostershop.website-ssl_log \
  ~/logs/boostershop.website 2>/dev/null | tail -n 50
```

Якщо рядок GSC має:

- `200 14318` — identity XML дійшов повністю;
- `200 2167` — gzip;
- `200 1832` — Brotli;
- інший status/size — це буде конкретний серверний симптом.

Після log read:

1. перевірити свіжий HTTP/2 шлях;
2. за потреби окремо перевірити HTTP/3/QUIC на hosting side;
3. якщо Google отримав `200` із правильним розміром — розбирати GSC
   submission/property/parser state, а не XML-файл.

## Files touched

```text
diagnostics/TECH-005-DEEP_sitemap_base_checks_report_20260701.md
```

## Rollback

Не потрібен: лише read-only перевірки та локальний звіт.

## Risks

SEO-risky zone. Не змінювати `.htaccess`, sitemap URL, compression або
`robots.txt` до отримання нового access-log рядка.
