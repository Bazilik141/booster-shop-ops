# Codex Report — LEGAL-002: оферта 24.07.2026 та архів 26.05.2026

Date: 2026-07-24

## Scope

Реалізовано строго за handoff: оновлення опису живої «Публічної оферти», створення однієї архівної information-сторінки та її SEO-маршруту. `robots.txt`, sitemap, canonical, меню, checkout і payment не змінюються. Noindex не реалізовано: для нього потрібен окремий owner-апрув.

## Files touched

```
patches/LEGAL-002_offer_mono_pumb_archive_20260724.php — self-contained uploadable DB patch
diagnostics/LEGAL-002_offer_mono_pumb_archive_report_20260724.md — this report
```

## Dry-run result

Локально перевірено HTML-інваріанти: нова оферта має 19 `<h2>` та посилання на архів; архів має 18 `<h2>` і посилання на живу оферту. SHA-256 вбудованих байтів: `4324d3…044e` (жива), `f19699…efc1` (архів). Патч на сервері сам обирає лише наявну й валідовану SEO-таблицю `DB_PREFIX + seo_url` або `DB_PREFIX + url_alias`; при відсутності/неоднозначності припиняється до запису.

## php -l result

Очікуваний локальний результат: `No syntax errors detected`.

## Idempotency

Повторний запуск після успіху звіряє обидва SHA-256 та SEO route і повертає `already_applied=yes`; записів не створює.

## Rollback

Бекап рядка живої оферти й стану архіву: `_patch_backups/LEGAL-002_offer_mono_pumb_archive_20260724-<timestamp>/db/`. Патч застосовує транзакцію: помилка до commit скасовує DB-зміни. Для ручного відкату відновити JSON-снапшот живого `information_description`; видалити створені архівні `information`, `information_description` і SEO-route лише за їхніми зафіксованими ID.

## Run command (owner)

```bash
cd ~/public_html || exit
php LEGAL-002_offer_mono_pumb_archive_20260724.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] У виводі є `done=ok`, SHA-256 обох документів і `seo_route_table`.
- [ ] `https://boostershop.website/information/publichna-oferta` показує редакцію 24.07.2026.
- [ ] `https://boostershop.website/information/publichna-oferta-arhiv-2026-05-26` повертає 200 і містить архів.
- [ ] Посилання жива ↔ архів не дають 404; архіву немає в меню/футері.
- [ ] Перевірити desktop + mobile, включно з TOC-сайдбаром.
- [ ] Не ставити статус Done до юридичної перевірки й live QA.

## Side effects / risks

Патч змінює 1 існуючий DB-рядок і створює до 3 архівних записів. Текст містить умови mono/ПУМБ, але платіжний та checkout-код не чіпається. Handoff зафіксував 4 юридичні розбіжності, які автор тексту має підтвердити окремо.
